<?php

namespace Proctor\Command;

use Exception;
use Proctor\Proctor;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

/**
 * Create config file.
 *
 * Not called use as that's a reserved word.
 */
class UseSite extends ProctorCommand
{

    protected $config;
    protected $siteConfig;

    protected function configure()
    {
        $this->setDescription('Setup tests to run against site.')
            ->addArgument(
                'site',
                InputArgument::REQUIRED,
                'Site name'
            )
            ->setHelp(<<<EOF
The <info>%command.name%</info> modifies tests to run against a specific site:

  <info>%command.full_name%</info>

Modifies Behat and Codeception YAML files to point them at the hostname given
as argument.

Any URLs in the YAML files marked with " # proctor:host" at the end of the line
will be replaced with the new URL.
EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $url = 'http://' . $input->getArgument('site');

        $finder = new Finder();
        $finder->files();

        if (file_exists('tests/behat')) {
            $finder->in('tests/behat');
        }
        if (file_exists('tests/codecept')) {
            $finder->in('tests/codecept');
        }

        $finder->name('*.yml')->name('*.yml.dist');
        foreach ($finder as $file) {
            $contents = file_get_contents($file->getPathname());
            $contents = preg_replace('{(["\'])?https?:.*?(\\1) # proctor:host}', "\$1$url\$1 # proctor:host", $contents, -1, $count);
                if ($count) {
                    file_put_contents($file->getPathname(), $contents);
                    $output->writeln("<info>Modified " . $file->getPathname() . "</info>");
                }
        }
    }
}
