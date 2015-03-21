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
class ConfigInit extends Command
{

    protected function configure()
    {
        $this->setDescription('Creates stub ~/.proctor.yml config file.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $fileName = Proctor::getConfigFileName();
            if (file_exists($fileName)) {
                throw new RuntimeException('~/.proctor.yml already exists', 1);

            }

            // Not using YAML::dump() as we want to inject comments.
            $config = <<<EOF
# Hostname for mysql server.
mysql-hostname: localhost
# Username for mysql.
mysql-username: username
# Password for mysql.
mysql-password: password
# Path to selenium-server jar.
selenium-server: ""
# Command line to use for drush, if "drush" wont suffice.
drush-command: ""
# Command line to use for mysql, if "mysql" wont suffice.
mysql-command: ""
# This allows for mapping site names to database names. The first matching
# pattern will be used.
# database-mapping:
#     "/^([^.]+).([^.]+).([^.]+)$/": "$2_$1"
EOF;
            if (file_put_contents($fileName, $config) === false) {
                throw new RuntimeException('Could not write ~/.proctor.yml', 1);
            }
            $output->writeln("<info>Created ~/.proctor.yml</info>");
        } catch (Exception $e) {
            $output->writeln("<error>" . $e->getMessage() . "</error>");
            return $e->getCode() > 0 ? $e->getCode() : 1;
        }
    }
}
