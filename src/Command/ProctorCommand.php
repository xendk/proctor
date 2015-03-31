<?php

namespace Proctor\Command;

use Exception;
use Proctor\Proctor;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Base class for Proctor commands.
 */
class ProctorCommand extends Command
{
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
    public function requireConfig()
    {
        $configFile = $this->getConfigFileName();
        if (!file_exists($configFile)) {
            throw new RuntimeException('Global configuration not found, please run proctor config:init', 1);
        }
        $this->config = Yaml::parse(file_get_contents($configFile));
    }

    /**
     * Load site config.
     */
    public function requireSiteConfig()
    {
        $configFile = 'tests/proctor/drupal.yml';
        if (!file_exists($configFile)) {
            throw new RuntimeException('Site configuration not found, please run proctor setup:drupal', 1);
        }
        $this->siteConfig =  Yaml::parse(file_get_contents($configFile));
    }
}
