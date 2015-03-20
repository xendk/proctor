<?php

namespace Proctor;

use Exception;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

class Proctor
{
    /**
     * Version.
     */
    const VERSION = "0.0.2";

    /**
     * Returns the file name of the global configuration file.
     */
    public static function getConfigFileName()
    {
        return getenv('HOME') . '/.proctor.yml';
    }

    /**
     * Load config.
     */
    public static function loadConfig()
    {
        $configFile = self::getConfigFileName();
        if (file_exists($configFile)) {
            return Yaml::parse(file_get_contents($configFile));
        }
        throw new RuntimeException('Global configuration not found, please run proctor config:init', 1);
    }

    /**
     * Load site config.
     */
    public static function loadSiteConfig()
    {
        $configFile = 'tests/proctor/drupal.yml';
        if (file_exists($configFile)) {
            return Yaml::parse(file_get_contents($configFile));
        }
        throw new RuntimeException('Site configuration not found, please run proctor setup:drupal', 1);
    }
}
