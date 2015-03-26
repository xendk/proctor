<?php

namespace Proctor\Command;

use Proctor\Proctor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
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
            ->addArgument(
                'alias',
                InputArgument::REQUIRED,
                'Alias to sync database and files from'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $fileName = 'tests/proctor/drupal.yml';
        $dirName = dirname($fileName);
        if (!file_exists($dirName) && !mkdir($dirName, 0777, true)) {
            throw new RuntimeException("Could not create $dirName", 1);

        }

        $config = YAML::dump(array(
            'fetch-strategy' => 'drush',
            'fetch-alias' => $input->getArgument('alias'),
        ));

        if (file_put_contents($fileName, $config) === false) {
            throw new RuntimeException("Could not write $fileName", 1);
        }
        $output->writeln("<info>Wrote " . $fileName . "</info>");
    }
}
