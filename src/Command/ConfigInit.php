<?php

namespace Proctor\Command;

use Proctor\Proctor;
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
        $fileName = getenv('HOME') . '/.proctor.yml';
        if (file_exists($fileName)) {
            $output->writeln("<error>~/.proctor.yml already exists</error>");
            return;
        }

        $config = <<<EOF
# Username for mysql.
mysql-username: username
# Password for mysql.
mysql-password: password
# Path to selenium-server jar.
selenium-server: ""
EOF;
        file_put_contents($fileName, $config);
        $output->writeln("<info>Created ~/.proctor.yml</info>");
    }
}
