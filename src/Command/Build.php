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
 * Create config file.
 */
class Build extends ProctorCommand
{
    protected $input;

    protected $output;

    protected function configure()
    {
        $this->setDescription('Build a Drupal site for testing.')
            ->addArgument(
                'site',
                InputArgument::REQUIRED,
                'Site name'
            )
            ->addOption(
                'print-commands',
                'p',
                InputOption::VALUE_NONE,
                'Print external commands instead of invoking them'
            )
            ->addOption(
                'timeout',
                't',
                InputOption::VALUE_REQUIRED,
                'Maximum execution time of syncing commands',
                '300'
            )
            ->setHelp(<<<EOF
The <info>%command.name%</info> command builds a site:

  <info>%command.full_name% default</info>

Creates a new Drupal multi-site, creates the database and populates it with
database and files from the source configured with:

  <info>%command.full_name% setup:drupal</info>
EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!($coreMajor = $this->sniffDrupalMajor(getcwd()))) {
            throw new RuntimeException('Could not determine Drupal version', 1);
        }

        if ($coreMajor !== 7) {
            throw new RuntimeException("Drupal $coreMajor currently not supported", 1);
        }

        if (getenv('CIRCLECI')) {
            $this->circleConfig();
        } else {
            $this->requireConfig();
        }
        $this->requireSiteConfig();

        $siteName = $input->getArgument('site');

        $timeout = $input->getOption('timeout');

        $output->writeln("<info>Building Drupal " . $coreMajor . " site</info>");
        $output->writeln("<info>Configuring site</info>");

        $database = $this->databaseName($siteName);
        // Create database.
        $command = $this->mysqlCommand();
        $command .= " -e \"CREATE DATABASE IF NOT EXISTS {$database};\"";

        $this->runCommand($command, null, $timeout);

        $this->{'buildDrupal' . $coreMajor}($siteName, $database);
        $this->addToSites($siteName);

        $output->writeln("<info>Syncing database and files</info>");

        switch ($this->siteConfig['fetch-strategy']) {
            case 'drush':
                $this->fetchDrush($siteName, $database, $timeout);
                break;

            default:
                throw new RuntimeException("Unknown fetch-strategy \"{$this->config['fetch-strategy']}\" in ~/..proctor.yml");
        }

        $output->writeln("<info>Done</info>");
    }

    protected function sniffDrupalMajor($path)
    {
        if (file_exists($path . '/core/lib/Drupal.php')) {
            return 8;
        }
        if (file_exists($path . '/includes/bootstrap.inc')) {
            $head = file_get_contents($path . '/includes/bootstrap.inc', false, null, 0, 500);
            if (preg_match("{define\('VERSION', '7\.}", $head)) {
                return 7;
            }
        }
        if (file_exists($path . '/modules/system/system.module')) {
            $head = file_get_contents($path . '/modules/system/system.module', false, null, 0, 500);
            if (preg_match("{define\('VERSION', '6\.}", $head)) {
                return 6;
            }
        }
        return null;
    }

    /**
     * Figure out the database name for a site.
     */
    protected function databaseName($siteName)
    {
        $patterns = $this->config['database-mapping'];
        // Default to prepend "proctor_" to the site name.
        $patterns += array('/^(.*)$/' => 'proctor_$1');

        $database = $siteName;
        foreach ($patterns as $pattern => $replacement) {
            if (preg_match($pattern, $siteName)) {
                $database = preg_replace($pattern, $replacement, $siteName);
                break;
            }
        }

        // Replace anything but alphanumeric and underscore with underscore to
        // avoid annoying mysql.
        $database = preg_replace('/[^a-z0-9_]/', '_', $database);

        return $database;
    }

    /**
     * Build Drupal  site.
     */
    protected function buildDrupal7($siteName, $database)
    {
        $settingsFile = 'sites/' . $siteName . '/settings.php';

        if (!file_exists(dirname($settingsFile))) {
            if (!mkdir(dirname($settingsFile), 0777, true)) {
                throw new RuntimeException('Could not create site directory', 1);
            }
        }

        $settings = "<?php
/**
 * Settings file generated by Proctor.
 *
 * The settings has been taken from the default.settings.php.
 */
";

        $dbSettings = array(
            'default' => array(
                'default' => array(
                    'driver' => 'mysql',
                    'database' => $database,
                    'username' => $this->config['mysql']['user'],
                    'password' => $this->config['mysql']['pass'],
                    'host' => $this->config['mysql']['host'],
                    'port' => '',
                    'prefix' => '',
                ),
            ),
        );

        $settings .= '$databases = ' . var_export($dbSettings, true) . ";\n";

        // An empty hash salt makes Drupal use a hash of the database
        // credentials as salt, which is good enough for our purposes.
        $settings .= <<<EOF
\$drupal_hash_salt = '';
\$update_free_access = FALSE;

ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);
ini_set('session.gc_maxlifetime', 200000);
ini_set('session.cookie_lifetime', 2000000);

\$conf['file_public_path'] = 'sites/$siteName/files';
\$conf['file_private_path'] = 'sites/$siteName/private';
# Hardcoded until someone makes an issue out of it.
\$conf['file_temporary_path'] = '/tmp';

EOF;

        if (!file_put_contents($settingsFile, $settings)) {
            throw new RuntimeException("Could not write $settingsFile", 1);
        }

        if (!file_exists('sites/' . $siteName . '/files')) {
            mkdir('sites/' . $siteName . '/files');
        }
        if (!file_exists('sites/' . $siteName . '/private')) {
            mkdir('sites/' . $siteName . '/private');
        }
    }

    /**
     * Adds the site to sites/sites.php.
     */
    protected function addToSites($siteName)
    {
        $sitesFile = 'sites/sites.php';
        if (!file_exists($sitesFile) &&
            !file_put_contents($sitesFile, "<?php\n")) {
            throw new RuntimeException("Could not create $sitesFile", 1);
        }

        $sites = file_get_contents($sitesFile);
        $sites = rtrim($sites) . "\n\$sites['$siteName'] = '$siteName';\n";
        if (!file_put_contents($sitesFile, $sites)) {
            throw new RuntimeException("Could not write $sitesFile", 1);
        }
    }

    /**
     * Fetch database and files using Drush.
     */
    protected function fetchDrush($siteName, $database, $timeout)
    {
        $command = $this->getCommand('drush');
        $alias = $this->siteConfig['fetch-alias'];
        if (empty($alias) || $alias[0] != '@') {
            throw new RuntimeException("Invalid fetch-alias \"{$alias}\" in tests/proctor/drupal.yml");
        }

        // Sync database.
        $this->runCommand($command . " {$alias} sql-dump | " . $this->mysqlCommand() . ' ' . $database, null, $timeout);

        // Sync files.
        $this->runCommand($command . " rsync -y {$alias}:%files files", 'sites/' . $siteName, $timeout);
        $this->runCommand($command . " rsync -y {$alias}:%private private", 'sites/' . $siteName, $timeout);

        // Clear cache.
        $this->runCommand($command . " cc all", null, $timeout);
    }

    /**
     * Generate mysql command line.
     */
    protected function mysqlCommand()
    {
        $command = $this->getCommand('mysql');

        $options = array(
            '--host' => 'host',
            '--user' => 'user',
            '--password' => 'pass',
        );
        foreach ($options as $switch => $key) {
            if (!empty($this->config['mysql'][$key])) {
                $command .= ' ' . $switch . '=' . $this->config['mysql'][$key];
            }
        }

        return $command;
    }
}
