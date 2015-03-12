<?php

namespace Codeception\Module;

use Codeception\TestCase;
use Proctor\CoProcess;

class Eyes extends \Codeception\Module
{
    protected $monocular = null;

    protected $currentTest = null;

    protected $requiredFields = ['api_key', 'app_name'];

    protected $config = [
        'enabled' => true,
    ];

    /**
     * Run visual inspection on browser.
     */
    public function eyeball($tag)
    {
        if (!$this->config['enabled']) {
            return;
        }
        if (!$this->monocular) {
            $this->startMonocular();

            // Get the window dimensions from the browser.
            $webdriver = $this->getModule('WebDriver')->webDriver;
            $dimensions = $webdriver->manage()->window()->getSize();

            $this->send('open', [
                'appName' => $this->config['app_name'],
                'testName' => $this->currentTest,
                'height' => $dimensions->getHeight(),
                'width' => $dimensions->getWidth(),
            ]);
        }

        // @todo figure out if this is the proper way to get temp dir.
        $file = tempnam(sys_get_temp_dir(), 'monocular');
        $this->getModule('WebDriver')->_saveScreenshot($file);

        $this->send('image', ['file' => $file, 'tag' => $tag]) . "\n";
    }

    /**
     * Start the Monocular process.
     */
    protected function startMonocular()
    {
        $script = dirname(dirname(dirname(__DIR__))) . '/monocular/monocular.js';
        $commandLine = implode(' ', array('node', $script, '-d'));
        $this->monocular = new CoProcess($commandLine);

        $this->monocular->start();
        $webdriver = $this->getModule('WebDriver')->webDriver;
        $browser = $webdriver->execute('getCapabilities')['browserName'];
        // Initialize.
        $this->send('init', [
            'apiKey' => $this->config['api_key'],
            'os' => php_uname('a'),
            'browser' => $browser,
        ]);
        // throw new \Codeception\Exception\ModuleConfig(__CLASS__, "messge")
    }

    /**
     * Send message to Monocular.
     */
    protected function send($message, $data = [])
    {
        $data['cmd'] = $message;
        $this->debugSection('Sending to Monocular', $data);
        $this->monocular->write(json_encode($data) . "\n");
    }

    /**
     * Called before each test.
     *
     * Save the test name for later.
     */
    public function _before(TestCase $test)
    {
        $this->currentTest = $test->getName();
    }

    /**
     * Called after suite.
     *
     * End the Monocular session, if it was started, and parse the result.
     */
    public function _after()
    {
        if ($this->monocular) {
            // Shut down monocular and parse result.
            $this->send('end');
            $exitCode = $this->monocular->wait();

            $this->debugSection('Monocular exit code', var_export($exitCode, true));
            $output = $this->monocular->getOutput();
            $this->debugSection('Monocular stdout', $output);
            $this->debugSection('Monocular stderr', $this->monocular->getErrorOutput());

            $this->monocular = null;

            if ($exitCode === 0 && preg_match('/Result: OK /', $output)) {
                // Tests passed. Nothing to do.
                return;
            }

            if ($exitCode === 20 && preg_match('/Result: (NEW|FAIL) (.*)/', $output, $matches)) {
                $message = $matches[1] === 'NEW' ? 'New images' : 'Differences detected';
                $message .= ', please review at ' . $matches[2];
                // New or failed tests, figure out which.
                $this->fail($message);
            } else {
                $this->fail("Monocular failed. Try again with -d for debugging information");
            }
        }
    }
}
