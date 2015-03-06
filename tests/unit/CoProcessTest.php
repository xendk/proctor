<?php

use Proctor\CoProcess;

class CoProcessTest extends \Codeception\TestCase\Test
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected function _before()
    {
    }

    protected function _after()
    {
    }

    // Test basic function.
    public function testBasicFuncionallity()
    {
        $process = new CoProcess('cat');
        $process->start();

        $process->write("Line 1\n");
        // Give cat a chance to start.
        sleep(1);
        $this->assertEquals("Line 1\n", $process->getOutput());
        $this->assertTrue($process->isRunning());

        $process->write("Line 2\n");
        $this->assertEquals("Line 1\nLine 2\n", $process->getOutput());
        $this->assertTrue($process->isRunning());

        $process->write("Line 3\n");
        $process->wait();
        $this->assertFalse($process->isRunning());

        $this->assertEquals("Line 1\nLine 2\nLine 3\n", $process->getOutput());
        $this->assertEquals(0, $process->getExitCode());
        $this->assertTrue(0 === $process->getExitCode());
    }
}
