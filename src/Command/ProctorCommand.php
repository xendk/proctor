<?php

namespace Proctor\Command;

use Exception;
use Proctor\Proctor;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Process\Process;

/**
 * Base class for Proctor commands.
 */
class ProctorCommand extends Command
{
    protected $input = null;
    protected $output = null;

    /**
     * Global configuration, if loaded.
     */
    protected $config = null;

    /**
     * Site configuration, if loaded.
     */
    protected $siteConfig = null;

    /**
     * Override run to let Exceptions function as exit codes.
     */
    public function run(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        try {
            return parent::run($input, $output);

        } catch (Exception $e) {
            $output->writeln("<error>" . $e->getMessage() . "</error>");
            return $e->getCode() > 0 ? $e->getCode() : 1;
        }
    }

    /**
     * Returns the file name of the global configuration file.
     */
    public function getConfigFileName()
    {
        return getenv('HOME') . '/.proctor.yml';
    }

    /**
     * Load config.
     */
    protected function requireConfig()
    {
        $configFile = $this->getConfigFileName();
        if (!file_exists($configFile)) {
            throw new RuntimeException('Global configuration not found, please run proctor config:init', 1);
        }
        $this->config = $this->normalizeConfig(Yaml::parse(file_get_contents($configFile)));
    }

    /**
     * Load config if there is one.
     */
    protected function optionalConfig() {
        try {
            $this->requireConfig();
        } catch (Exception $e) {
            $this->config = array();
        }
    }

    /**
     * Use CircleCI config.
     */
    protected function circleConfig()
    {
        $this->config = $this->normalizeConfig(array(
            'mysql' => array(
                'user' => 'ubuntu',
                'host' => '127.0.0.1',
            ),
            'database-mapping' => array(
                '/^(.*)$/' => 'circle_test',
            ),
        ));
    }

    /**
     * Normalize configuration.
     */
    protected function normalizeConfig($config)
    {
        if (empty($config['mysql']) ||
            !is_array($config['mysql'])) {
            $config['mysql'] = array();
        }

        $config['mysql'] += array(
            'host' => '',
            'user' => '',
            'pass' => '',
        );

        if (empty($config['database-mapping']) ||
            !is_array($config['database-mapping'])) {
            $config['database-mapping'] = array();
        }

        return $config;
    }

    /**
     * Load site config.
     */
    protected function requireSiteConfig()
    {
        $configFile = 'tests/proctor/drupal.yml';
        if (!file_exists($configFile)) {
            throw new RuntimeException('Site configuration not found, please run proctor setup:drupal', 1);
        }
        $this->siteConfig = Yaml::parse(file_get_contents($configFile));
    }

    /**
     * Get the command line for a command.
     *
     * Return the configured command or the default.
     */
    protected function getCommand($name, $default = null)
    {
        if (!$default) {
            $default = $name;
        }

        if (is_null($this->config)) {
            $this->requireConfig();
        }

        if (isset($this->config['commands'][$name])) {
            return $this->config['commands'][$name];
        }

        return $default;
    }

    /**
     * Run a command.
     */
    protected function runCommand($command, $cwd = null)
    {
        if ($this->input->getOption('print-commands')) {
            $this->output->writeln("<comment>command: " . $command . "</comment>");
        } else {
            $process = new Process($command, $cwd);
            $process->run();
            if ($process->getExitCode() !== 0) {
                throw new RuntimeException("Command \"{$process->getCommandLine()}\" failed\nOutput:\n{$process->getOutput()}\nError outbut:\n{$process->getErrorOutput()}");
            }
        }
    }
}
