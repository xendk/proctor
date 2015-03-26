<?php

namespace Proctor\Command;

use Exception;
use Proctor\Proctor;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Process\Process;

/**
 * Run tests.
 */
class Run extends ProctorCommand
{

    protected $config;
    protected $siteConfig;

    protected function configure()
    {
        $this->setDescription('Build a Drupal site for testing.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Behat.
        if (file_exists('tests/behat')) {
            if (!file_exists('vendor/bin/behat')) {
                throw new RuntimeException('Could not locate behat executable.');
            }
            $output->writeln("<info>Running Behat tests</info>");
            $exitCode = $this->passthrough('tests/behat', '../../vendor/bin/behat', $output);
            if ($exitCode) {
                return $exitCode;
            }
        }

        // Codeception.
        if (file_exists('tests/codecept')) {
            if (!file_exists('vendor/bin/codecept')) {
                throw new RuntimeException('Could not locate codecept executable.');
            }
            $output->writeln("<info>Running Codeception tests</info>");
            $exitCode = $this->passthrough('tests/codecept', '../../vendor/bin/codecept run', $output);
            if ($exitCode) {
                return $exitCode;
            }
        }
    }

    /**
     * Run a command in a directory, passing through output.
     */
    protected function passthrough($directory, $command, $output)
    {
        $process = new Process($command, $directory);
        return $process->run(function ($type, $buffer) use ($output) {
                if (Process::ERR === $type) {
                    $output->getErrorOutput()->write($buffer);
                } else {
                    $output->write($buffer);
                }
            });
    }
}
