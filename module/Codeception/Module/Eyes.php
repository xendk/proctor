<?php

namespace Codeception\Module;

class Eyes extends \Codeception\Module
{
    protected $monocular = null;

    protected $monocularPipe = null;

    protected $previousTest = null;

    protected $currentTest = null;

    protected static $requiredFields = ['api_key', 'app_name'];

    public function eyeball($tag)
    {
        if ($this->previousTest !== $this->currentTest) {
            // First or new test. Open it.
            $this->send('open', [
                'appName' => $this->app_name,
                'testName' => $this->currentTest,
            ]);
            $this->previousTest = $this->currentTest;
        }
        // @todo figure out if this is the proper way to get temp dir.
        $file = tempnam(sys_get_temp_dir(), 'monocular');
        $this->getModule('WebDriver')->_saveScreenshot($file);

        $this->send('image', ['file' => $file, 'tag' => $tag]) . "\n";
    }

    protected function startMonocular()
    {
        $this->monocularPipe = fopen("php://temp/", 'r+');

        $script = dirname(dirnamd(dirname(__DIR__))) . '/monocular/monocular.js';
        $processBuilder = new ProcessBuilder(array('node', $script));
        $processBuilder->setInput($this->monocularPipe);

        $this->monocular = $processBuilder->getProcess();
        $this->monocular->start();

        // Initialize.
        $this->send('init', [
            'apiKey' => $this->api_key,
            'os' => php_uname('a'),
            // @todo browser: webdriver -> getCapabilities -> getBrowserName
            'broser' => 'unnknown'
        ]);
        // throw new \Codeception\Exception\ModuleConfig(__CLASS__, "messge")
    }

    protected function send($message, $data)
    {
        $data['cmd'] = $message;
        fwrite($this->monocularPipe, json_encode($data) . "\n");
    }

    public function _before(TestCase $test)
    {
        if (!$this->monocular) {
            $this->startMonocular();
        }

        $this->currentTest = $test->getName();
    }

    public function _afterSuite()
    {
        // shut down monocular and parse result
        fclose($this->monocularPipe);
        $exitCode = $this->monocular->wait();

        $output = $this->monocular->getOutput();
        $this->debugSection('Monocular outout', $output);

        $this->monocular = null;

        if ($exitCode === 0) {
            // Tests passed. Nothing to do.
            return;
        }

        if ($exitCode === 20 && preg_match('Result: (NEW|FAIL) (.*)', $output, $matches)) {
            $message = $matches[1] === 'NEW' ? 'New images' : 'Test failed';
            $message = ', please review at ' . $matches[2];
            // New or failed tests, figure out which.
            $this->fail($message);
        } else {
            $this->fail("Monocular failed. Try again with -d for debugging information");
        }
    }
}
