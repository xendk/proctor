<?php

namespace Proctor\Command;

use Proctor\Proctor;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Setup config for Drupal site.
 */
class SetupDrupal extends ProctorCommand
{

    protected function configure()
    {
        $this->setDescription('Create configuration for setting up Drupal.')
          ->addOption(
              'method',
              'm',
              InputOption::VALUE_REQUIRED,
              'Method for populating site.',
              'drush'
          )
          ->addOption(
              'alias',
              'a',
              InputOption::VALUE_REQUIRED,
              '(drush) Drush alias to sync from.',
              null
          )
          ->addOption(
              'dumpfile',
              'd',
              InputOption::VALUE_OPTIONAL,
              '(dump-n-config) SQL dump to import.',
              'configuration/base.sql.gz'
          )
            ->addOption(
                'staging',
                's',
                InputOption::VALUE_OPTIONAL,
                '(dump-n-config) SQL dump to import.',
                'configuration/staging'
            )
          ->setHelp(<<<EOF
The <info>%command.name%</info> creates a Drupal configuration file:

  <info>%command.full_name% --alias @live</info>

Or
  <info>%command.full_name% --method dump-n-config</info>

Creates a configuration file with information about how to populate the site.

There's currently two methods:

  drush: Uses Drush sql-sync and rsync to sync from a Drush alias.

  dump-n-config: Imports an SQL dump and imports the staging configuration
                 (Drupal 8).

EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $method = $input->getOption('method');

        switch ($method) {
            case 'drush':
                $alias = $input->getOption('alias');
                if (empty($alias)) {
                    throw new RuntimeException("You must supply a valid Drush alias using the --alias argument.");
                }
                if ($alias[0] != '@') {
                    throw new RuntimeException("Invalid alias \"$alas\".");
                }

                $config = array(
                    'fetch-method' => $method,
                    'fetch-alias' => $alias,
                );
                break;
            case 'dump-n-config':
                $config = array(
                    'fetch-method' => $method,
                    'fetch-dumpfile' => $input->getOption('dumpfile'),
                    'fetch-staging' => $input->getOption('staging'),
                );
                break;
            default:
                throw new RuntimeException("Unknown fetching method \"$method\".");
        }

        $fileName = 'tests/proctor/drupal.yml';
        $dirName = dirname($fileName);
        if (!file_exists($dirName) && !mkdir($dirName, 0777, true)) {
            throw new RuntimeException("Could not create $dirName", 1);

        }

        $config = YAML::dump($config);

        if (file_put_contents($fileName, $config) === false) {
            throw new RuntimeException("Could not write $fileName", 1);
        }
        $output->writeln("<info>Wrote " . $fileName . "</info>");
    }
}
