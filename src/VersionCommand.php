<?php

namespace COREPOS\Updater;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class VersionCommand extends PathReqCommand
{
    protected function configure()
    {
        parent::configure();
        $this->setName('version')
            ->setDescription('Get information about currently installed version');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $path = $input->getArgument('path');
        $ver = false;
        if (file_exists($path . '/composer.json')) {
            $composer = file_get_contents($path . '/composer.json');
            $composer = json_decode($composer, true);
            if (isset($composer['version'])) {
                $ver = $composer['version'];
            }
        }
        if (!$ver) {
            $ver = trim(file_get_contents(__DIR__ . '/VERSION'));
        }
        $output->writeln("Current version: <info>$ver</info>");

        $git = new Git($path);
        $branch = $git->getCurrentBranch();
        $output->writeln("git branch: <info>$branch</info>");
        $revs = $git->getRevisions();
        $last = array_pop($revs);
        $output->writeln("git current head: <info>{$last['sha1']}</info>");
        $origin = $git->fetchURL("origin");
        $output->writeln("git origin: <info>{$origin}</info>");
        if (strstr($origin, 'CORE-POS/IS4C') && strstr($branch, 'version-')) {
            $output->writeln("<comment>You're running a stable, public release</comment>");
        } else {
            $output->writeln("<comment>You're [probably] developing on a fork</comment>");
        }
    }
}

