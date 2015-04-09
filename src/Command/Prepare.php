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

/**
 * Prepare for testing by starting Selenium server.
 */
class Prepare extends ProctorCommand
{
    protected function configure()
    {
        $this->setDescription('Prepare Selenium Server for testing.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> starts up Selenium Server:

  <info>%command.full_name%</info>

The Selenium JAR to use can be configured in the global configuration file.
EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->requireConfig();

        if (empty($this->config['selenium-server']) ||
            !file_exists($this->config['selenium-server'])) {
            throw new RuntimeException("Could not find Selenium jar file.\nPlease download Selenium Server from http://www.seleniumhq.org/download/ and add the location of the jar file to ~/.proctor.yml ", 1);
        }

        $java = $this->getCommand('java');
        $java = trim(shell_exec('which ' . $java));

        $command = $java . ' -jar ' . $this->config['selenium-server'];
        $args = array('-jar', $this->config['selenium-server']);
        $output->writeln('<info>Starting Selenium server</info>');
        $pid = $this->startProcess($command);
        if ($pid) {
            $output->writeln('<info>Server started, PID: ' . $pid . '</info>');
        } else {
            throw new RuntimeException("Problem starting $command", 1);
        }
    }

    protected function startProcess($command, $args = null)
    {
        // This closes any file descriptors above 2. The problem is that
        // proc_open (in Behat tests) leaks the file descriptors it uses to
        // communicate with the child process, and they're kept open for as
        // long as the java process runs. There's no way to clean up this mess
        // from PHP, so we'll let the shell do it.
        $close_descriptors = '3>&- 4>&- 5>&- 6>&- 7>&- 8>&- 9>&-';
        // 3>&- 4>&-
        $output = exec(sprintf('%s </dev/null >/tmp/selenium-server.log 2>&1 ' . $close_descriptors . ' & echo $!', $command));

        if (preg_match('/\d+/', $output)) {
            return $output;
        }

        return null;
    }
}
