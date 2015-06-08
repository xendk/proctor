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
            ->addOption(
                'hash-seed',
                's',
                InputOption::VALUE_OPTIONAL,
                'Integer seed for random hash value. Default is random.'
            )
            ->setHelp(<<<EOF
The <info>%command.name%</info> command builds a site:

  <info>%command.full_name% default</info>

Creates a new Drupal multi-site, creates the database and populates it using
the method configured in setup:drupal.

EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!($coreMajor = $this->sniffDrupalMajor(getcwd()))) {
            throw new RuntimeException('Could not determine Drupal version');
        }

        if ($coreMajor !== 7 && $coreMajor !== 8) {
            throw new RuntimeException("Drupal $coreMajor currently not supported");
        }

        if (getenv('CIRCLECI')) {
            $this->circleConfig();
        } else {
            $this->requireConfig();
        }
        $this->requireSiteConfig();

        $siteName = $input->getArgument('site');

        $timeout = $input->getOption('timeout');

        $seed = (int) $input->getOption('hash-seed');

        switch ($this->siteConfig['fetch-method']) {
            case 'drush':
                $alias = $this->siteConfig['fetch-alias'];
                if (empty($alias) || $alias[0] != '@') {
                    throw new RuntimeException("Invalid fetch-alias \"{$alias}\" in tests/proctor/drupal.yml");
                }
                break;

            case 'dump-n-config':
                if ($coreMajor != 8) {
                    throw new RuntimeException("The dump-n-config fetch method is only supported in Drupal 8, in tests/proctor/drupal.yml");
                }
                $dumpFile = $this->siteConfig['fetch-dumpfile'];
                if (empty($dumpFile)) {
                    throw new RuntimeException("Missing fetch-dumpfile in tests/proctor/drupal.yml");
                }

                if (!file_exists($dumpFile)) {
                    throw new RuntimeException("SQL dump file \"$dumpFile\" in tests/proctor/drupal.yml, doesn't exist.");
                }


                $stagingDir = $this->siteConfig['fetch-staging'];
                if (empty($stagingDir)) {
                    throw new RuntimeException("Missing fetch-staging in tests/proctor/drupal.yml");
                }

                if (!file_exists($stagingDir)) {
                    throw new RuntimeException("Configuration staging directory  \"$stagingDir\" in tests/proctor/drupal.yml, doesn't exist.");
                }
                break;

            default:
                throw new RuntimeException("Unknown fetch method \"{$this->config['fetch-method']}\", in tests/proctor/drupal.yml");
        }

        $output->writeln("<info>Building Drupal " . $coreMajor . " site</info>");
        $output->writeln("<info>Configuring site</info>");

        $database = $this->databaseName($siteName);
        // Create database.
        $command = $this->mysqlCommand();
        $command .= " -e \"CREATE DATABASE IF NOT EXISTS {$database};\"";

        $this->runCommand($command, null, $timeout);

        $this->buildDrupal($coreMajor, $siteName, $database, $seed);
        $this->addToSites($siteName);


        switch ($this->siteConfig['fetch-method']) {
            case 'drush':
                $output->writeln("<info>Syncing database and files</info>");
                $this->methodDrush($siteName, $database, $timeout);
                break;

            case 'dump-n-config':
                $output->writeln("<info>Importing SQL dump and config</info>");
                $this->methodDumpNConfig($siteName, $database, $timeout);
                break;

        }

        $output->writeln("<info>Done</info>");
    }

    /**
     * Detect Drupal major version at path.
     */
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
     * Build Drupal site.
     */
    protected function buildDrupal($major, $siteName, $database, $seed = 0)
    {
        $settingsFile = 'sites/' . $siteName . '/settings.php';

        if (!file_exists(dirname($settingsFile))) {
            if (!mkdir(dirname($settingsFile), 0777, true)) {
                throw new RuntimeException('Could not create site directory');
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
        if ($seed) {
            mt_srand($seed);
        }

        $hash = hash('sha256', mt_rand());

        if ($major == 7) {
            $settings .= <<<EOF
\$drupal_hash_salt = '$hash';
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
        } elseif ($major == 8) {
            $settings .= <<<EOF
\$settings['hash_salt'] = '$hash';
\$settings['container_yamls'][] = __DIR__ . '/services.yml';
\$settings['file_chmod_directory'] = 0777;
\$settings['file_chmod_file'] = 0777;
\$config_directories[CONFIG_STAGING_DIRECTORY] = 'configuration/staging';
\$config_directories[CONFIG_ACTIVE_DIRECTORY] = 'sites/$siteName/private/config_active';
\$settings['file_public_path'] = 'sites/$siteName/files';
\$settings['file_private_path'] = 'sites/$siteName/private';

EOF;
        }

        if (!file_put_contents($settingsFile, $settings)) {
            throw new RuntimeException("Could not write $settingsFile");
        }

        if ($major == 8) {
            if (!copy('sites/default/default.services.yml', 'sites/' . $siteName . '/services.yml')) {
                throw new RuntimeException("Could not copy sites/default/default.services.yml");
            }
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
            throw new RuntimeException("Could not create $sitesFile");
        }

        $sites = file_get_contents($sitesFile);
        $sites = rtrim($sites) . "\n\$sites['$siteName'] = '$siteName';\n";
        if (!file_put_contents($sitesFile, $sites)) {
            throw new RuntimeException("Could not write $sitesFile");
        }
    }

    /**
     * Fetch database and files using Drush.
     */
    protected function methodDrush($siteName, $database, $timeout)
    {
        $command = $this->getCommand('drush');
        $command .= ' --uri=' . $siteName;
        $alias = $this->siteConfig['fetch-alias'];
        // Sync database.
        $this->runCommand($command . " {$alias} sql-dump | " . $this->mysqlCommand() . ' ' . $database, null, $timeout);

        // Sync files.
        $this->runCommand($command . " rsync -y {$alias}:%files files", 'sites/' . $siteName, $timeout);
        $this->runCommand($command . " rsync -y {$alias}:%private private", 'sites/' . $siteName, $timeout);

        // Clear cache.
        $this->runCommand($command . " cc all", null, $timeout);
    }

    /**
     * Build site from dump and config.
     */
    protected function methodDumpNConfig($siteName, $database, $timeout)
    {
        $drushCommand = $this->getCommand('drush');
        $drushCommand .= ' --uri=' . $siteName;
        $zcatCommand = $this->getCommand('zcat');

        $dumpFile = $this->siteConfig['fetch-dumpfile'];
        $staging = $this->siteConfig['fetch-staging'];

        // Import dump.
        $this->runCommand($zcatCommand . " " . $dumpFile . " | " . $this->mysqlCommand() . ' ' . $database, null, $timeout);

        // Import configuration.
        $this->runCommand($drushCommand . " -y cim", null, $timeout);
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
