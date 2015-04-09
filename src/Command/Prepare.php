<?php

namespace Proctor\Command;

use Exception;
use Proctor\Proctor;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
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
            ->addOption(
                'fetch',
                'f',
                InputOption::VALUE_NONE,
                'Fetch Selenium Server if not found'

            )
            ->addOption(
                'selenium-dir',
                's',
                InputOption::VALUE_OPTIONAL,
                'Directory for Selenium JAR. Defaults to /tmp',
                '/tmp'
            )
            ->addOption(
                'print-commands',
                'p',
                InputOption::VALUE_NONE,
                'Print external commands instead of invoking them'
            )
            ->setHelp(<<<EOF
The <info>%command.name%</info> starts up Selenium Server:

  <info>%command.full_name%</info>

The Selenium JAR to use can be configured in the global configuration file.
EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->optionalConfig();
        if ($input->getOption('fetch')) {
            $url = 'http://selenium-release.storage.googleapis.com/2.45/selenium-server-standalone-2.45.0.jar';
            $seleniumServer = $input->getOption('selenium-dir') . '/' . basename($url);
            if (!file_exists($seleniumServer)) {
                if (!file_exists($input->getOption('selenium-dir'))) {
                    if (!mkdir($input->getOption('selenium-dir'), 0777, true)) {
                        throw new RuntimeException("Could not create " . $input->getOption('selenium-dir'), 1);
                    }
                }

                // Fetch the JAR.
                $output->writeln('<info>Fetching ' . $url . '</info>');
                $this->runCommand('wget ' . $url, $input->getOption('selenium-dir'));
                if (!file_exists($seleniumServer)) {
                    throw new RuntimeException("Problem downloading Selenium Server", 1);
                }
            }
        } else {
            if (empty($this->config['selenium-server']) ||
                !file_exists($this->config['selenium-server'])) {
                throw new RuntimeException("Could not find Selenium jar file.\nPlease download Selenium Server from http://www.seleniumhq.org/download/ and add the location of the jar file to ~/.proctor.yml or run with --fetch", 1);
            }
            $seleniumServer = $this->config['selenium-server'];
        }

        $java = $this->getCommand('java');
        $java = trim(shell_exec('which ' . $java));

        $command = $java . ' -jar ' . $seleniumServer;

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
