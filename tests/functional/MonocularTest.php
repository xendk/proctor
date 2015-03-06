<?php

use Proctor\CoProcess;

class MonocularTest extends \Codeception\TestCase\Test
{
    /**
     * @var \FunctionalTester
     */
    protected $tester;

    protected function _before()
    {
    }

    protected function _after()
    {
    }

    // tests
    public function testMonocular()
    {
        $script = dirname(dirname(__DIR__)) . '/monocular/monocular.js';
        $commandLine = implode(' ', array('node', $script));
        $monocular = new CoProcess($commandLine);

        $monocular->start();

        $monocular->write(json_encode([
            'cmd' => 'init',
            'apiKey' => "e9GAkZ30biPxyDYv1OflTyklqIh4LpFkLf101tydzMiYQ110",
            'os' => php_uname('a'),
            'browser' => 'unnknown'
        ]) . "\n");

        $monocular->write(json_encode([
            'cmd' => 'open',
            'appName' => "MyApp",
            'testName' => "FirstTest5",
        ]) . "\n");

        $monocular->write(json_encode([
            'cmd' => 'image',
            'file' => "monocular/testfile.png",
            'tag' => "FirstTag",
        ]) . "\n");

        $monocular->write(json_encode([
            'cmd' => 'end',
        ]) . "\n");

        $exitCode = $monocular->wait();

        codecept_debug('Monocular exit code');
        codecept_debug(var_export($exitCode, true));
        $output = $monocular->getOutput();
        codecept_debug('Monocular stdout');
        codecept_debug($output);
        codecept_debug('Monocular stderr');
        codecept_debug($monocular->getErrorOutput());


        $this->assertEquals(0, $exitCode);
    }
}
