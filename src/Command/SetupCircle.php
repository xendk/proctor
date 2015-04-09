<?php

namespace Proctor\Command;

use Proctor\Proctor;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Setup config for Drupal site.
 */
class SetupCircle extends ProctorCommand
{

    protected function configure()
    {
        $this->setDescription('Configure Circle CI for running tests.')
            ->addOption(
                'apache-sites',
                null,
                InputOption::VALUE_OPTIONAL,
                'Print external commands instead of invoking them',
                '/etc/apache2/sites-available'
            )
            ->addOption(
                'print-commands',
                'p',
                InputOption::VALUE_NONE,
                'Print external commands instead of invoking them'
            )
            ->setHelp(<<<EOF
The <info>%command.name%</info> sets up Circle CI for running tests:

  <info>%command.full_name%</info>

Sets up an Apache virtual host to point to the current site.
EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $phpVersion = null;
        if (file_exists('circle.yml')) {
            $circle = Yaml::parse(file_get_contents('circle.yml'));
            if (isset($circle['machine']['environment']['php']['version'])) {
                $phpVersion = $circle['machine']['environment']['php']['version'];
            }
        }

        if (empty($phpVersion)) {
            throw new RuntimeException("No PHP version found in circle.yml.

Currently proctor needs PHP version to be pinned.", 1);
        }

        $output->writeln("<info>Setting up Circle Apache virtual host</info>");

        $directory = basename(getcwd());

        $vhost = <<<EOF
Listen 8080

<VirtualHost *:8080>
  LoadModule php5_module /home/ubuntu/.phpenv/versions/$phpVersion/libexec/apache2/libphp5.so
  DocumentRoot /home/ubuntu/$directory
  ServerName proctor.dev
  <FilesMatch \.php$>
    SetHandler application/x-httpd-php
  </FilesMatch>
</VirtualHost>
EOF;

        $vhostFilename = rtrim($input->getOption('apache-sites'), '/') . '/proctor.conf';
        if (file_put_contents($vhostFilename, $vhost) === false) {
            throw new RuntimeException("Could not write $vhostFilename", 1);
        }
        $output->writeln("<info>Wrote " . $vhostFilename . "</info>");

        $this->runCommand('a2ensite proctor.conf');
        $this->runCommand('sudo service apache2 restart');

        $output->writeln("<info>Done</info>");
    }
}
