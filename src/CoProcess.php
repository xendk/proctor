<?php

namespace Proctor;

/**
 * Start a co-process that can be fed data on STDIN.
 *
 * Tries to mimic Symfony Process.
 */
class CoProcess {
    const STATUS_READY = 'ready';
    const STATUS_STARTED = 'started';
    const STATUS_TERMINATED = 'terminated';

    private $status = self::STATUS_READY;
    protected $commandLine;
    protected $cwd;

    protected $inputBuffer;
    protected $inputClosed = false;

    protected $output = '';
    protected $errorOutput = '';

    protected $pipes;

    public function __construct($commandLine, $cwd = null)
    {
        if (!function_exists('proc_open')) {
            throw new RuntimeException('The CoProcess class relies on proc_open, which is not available on your PHP installation.');
        }

        $this->commandLine = $commandLine;
        $this->cwd = $cwd;
    }

    public function start()
    {
        if ($this->isRunning()) {
            throw new RuntimeException('Process is already running');
        }

        $descriptors = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );

        $this->process = proc_open($this->commandLine, $descriptors, $this->pipes, $this->cwd);

        if (!is_resource($this->process)) {
            throw new RuntimeException('Unable to launch a new process.');
        }

        foreach (range(0, 2) as $fileId) {
            stream_set_blocking($this->pipes[$fileId], 0);
        }

        $this->status = self::STATUS_STARTED;
    }

    public function isRunning()
    {
        if (self::STATUS_STARTED !== $this->status) {
            return false;
        }

        $this->updateStatus(false);

        return $this->processInformation['running'];
    }

    protected function updateStatus($blocking = false)
    {
        if (self::STATUS_STARTED !== $this->status) {
            return;
        }

        $this->processInformation = proc_get_status($this->process);
        if (isset($this->processInformation['exitcode']) && -1 != $this->processInformation['exitcode']) {
            $this->exitcode = $this->processInformation['exitcode'];
        }

        $this->doIO($blocking);

        if (!$this->processInformation['running']) {
            $this->close();
        }
    }

    protected function doIO($blocking = false)
    {
        if (empty($this->pipes)) {
            return;
        }
        $read = $this->pipes;
        unset($read[0]);
        $write = isset($this->pipes[0]) ? array($this->pipes[0]) : null;
        $except = null;

        if (false === $number = stream_select($read, $write, $except, $blocking ? 500 : 0)) {
            throw new RuntimeException('Error communicating with process.');
        }

        if ($number === 0) {
            // Nothing to read or write.
            return;
        }

        if ($read) {
            foreach ($read as $pipe) {
                $fileId = array_search($pipe, $this->pipes);

                $data = '';
                while ('' !== $dataread = (string) fread($pipe, 2 << 18)) {
                    $data .= $dataread;
                }

                if ('' !== $data) {
                    if ($fileId === 1) {
                        $this->output .= $data;
                    } else {
                        $this->errorOutput .= $data;
                    }
                }
            }
        }

        if ($write) {
            // We can assume which pipe here.
            while (strlen($this->inputBuffer)) {
                $written = fwrite($write[0], $this->inputBuffer, 2 << 18); // write 512k
                if ($written > 0) {
                    $this->inputBuffer = (string) substr($this->inputBuffer, $written);
                } else {
                    break;
                }
            }
            fflush($write[0]);
        }

        if ('' === $this->inputBuffer && $this->inputClosed && isset($this->pipes[0])) {
            fclose($this->pipes[0]);
            unset($this->pipes[0]);
        }
    }

    private function close()
    {
        foreach ($this->pipes as $pipe) {
            fclose($pipe);
        }

        if (is_resource($this->process)) {
            $exitcode = proc_close($this->process);
        } else {
            $exitcode = -1;
        }

        $this->exitcode = -1 !== $exitcode ? $exitcode : (null !== $this->exitcode ? $this->exitcode : -1);
        $this->status = self::STATUS_TERMINATED;

        if (-1 === $this->exitcode && $this->processInformation['signaled'] && 0 < $this->processInformation['termsig']) {
            // if process has been signaled, no exitcode but a valid termsig, apply Unix convention
            $this->exitcode = 128 + $this->processInformation['termsig'];
        }

        return $this->exitcode;
    }

    public function wait()
    {
        $this->inputClosed = true;
        $this->updateStatus(true);
        while ($this->isRunning()) {
            usleep(1000);
            $this->updateStatus(true);
        }

        return $this->exitcode;
    }

    public function write($string)
    {
        $this->inputBuffer .= $string;
        $this->doIO();
    }

    public function getOutput()
    {
        $this->updateStatus();
        return $this->output;
    }

    public function getErrorOutput()
    {
        $this->updateStatus();
        return $this->errorOutput;
    }

    public function getExitCode()
    {
        return $this->exitcode;
    }
}
