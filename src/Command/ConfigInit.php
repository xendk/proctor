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
 * Create config file.
 */
class ConfigInit extends ProctorCommand
{

    protected function configure()
    {
        $this->setDescription('Creates stub ~/.proctor.yml config file.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> creates a stub global configuration file:

  <info>%command.full_name%</info>

The global configuration file provides credentials for mysql and allows for
overriding of the commands invoked.
EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $fileName = $this->getConfigFileName();
        if (file_exists($fileName)) {
            throw new RuntimeException('~/.proctor.yml already exists', 1);

        }

        // Not using YAML::dump() as we want to inject comments.
        $config = <<<EOF
# MySQL/MariaDB credentials for creating test site databases.
mysql:
  host: localhost
  user: username
  pass: pass
# Path to selenium-server jar.
selenium-server: ""
# Allows you to override the command lines used for external commands.
# commands:
#   drush: ""
#   mysql: ""
#   java: ""
# This allows for mapping site names to database names. The first matching
# pattern will be used.
# database-mapping:
#     "/^([^.]+).([^.]+).([^.]+)$/": "$2_$1"
EOF;
        if (file_put_contents($fileName, $config) === false) {
            throw new RuntimeException('Could not write ~/.proctor.yml', 1);
        }
        $output->writeln("<info>Created ~/.proctor.yml</info>");
    }
}
