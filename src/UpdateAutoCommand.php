<?php

namespace COREPOS\Updater;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class UpdateAutoCommand extends PathReqCommand
{
    protected function configure()
    {
        parent::configure();
        $this->setName('update:auto')
            ->setDescription('Update to the latest version')
            ->addOption('merge', 'm', InputOption::VALUE_NONE, 'Perform a merge instead of checking out the latest tag', null)
            ->addOption('force', 'f', InputOption::VALUE_REQUIRED, 'Automatically resolve merge conflicts. Specify "ours" or "theirs"', null);
    }

    protected function goodTags($tags)
    {
        $tags = array_filter($tags, function ($i) { return preg_match('/^\d+\.\d+\.\d+/', $i); });
        usort($tags, array('COREPOS\\Updater\\UpdateAutoCommand', 'semVarSort'));

        return array_reverse($tags);
    }

    public static function semVarSort($a, $b)
    {
        if (preg_match('/^(\d+\.\d+\.\d+)/', $a, $matches)) {
            $a = $matches[1];
        } else {
            return 0;
        }
        if (preg_match('/^(\d+\.\d+\.\d+)/', $b, $matches)) {
            $b = $matches[1];
        } else {
            return 0;
        }
        list($a_maj, $a_min, $a_rev) = explode('.', $a);
        list($b_maj, $b_min, $b_rev) = explode('.', $b);
        if ($a_maj < $b_maj) {
            return -1;
        } elseif ($a_maj > $b_maj) {
            return 1;
        } elseif ($a_min < $b_min) {
            return -1;
        } elseif ($a_min > $b_min) {
            return 1;
        } elseif ($a_rev < $b_rev) {
            return -1;
        } elseif ($a_rev > $b_rev) {
            return 1;
        }
        return 0;
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $path = $input->getArgument('path');
        $git = new Git($path);
        $branch = $git->getCurrentBranch();
        $revs = $git->getRevisions();
        $last = array_pop($revs); 
        $repo = $this->getApplication()->configValue('repo');
    
        try {
            // verify upstream is a remte
            $upstream = $git->remote('upstream');
        } catch (\Exception $ex) {
            if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $output->writeln("Running: <comment>git remote add upstream {$repo}</comment>");
            }
            $git->addRemote('upstream', $repo);
        }

        $git->fetch('upstream');
        $tags = $git->tags('upstream');
        $tags = $this->goodTags($tags);
        $latest = $tags[0];
        $current = false;
        if (file_exists($path . '/composer.json')) {
            $composer = file_get_contents($path . '/composer.json');
            $composer = json_decode($composer, true);
            if (isset($composer['version'])) {
                $current = $composer['version'];
            }
        }
        if (!$current) {
            $current = trim(file_get_contents(__DIR__ . '/VERSION'));
        }
        if (!preg_match('/^(\d+.\d+.\d+)/', $current, $matches)) {
            $output->writeln("<error>Version {$current} is not semVar</error>");
            return;
        }
        $current = $matches[0];
        if (self::semVarSort($current, $latest) != -1) {
            $output->writeln("<info>Version {$current} is up-to-date</info>");
            return;
        }

        $merge = $input->getOption('merge');
        if ($merge) {
            $git->branch($test_branch, $branch);
            $test_branch = 'snapshot-' . $current . '-' . date('Y-m-d-His');
            if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $output->writeln("Running: <comment>git branch {$test_branch} {$branch}</comment>");
            }
            $force = trim(strtolower($input->getOption('force')));
            $forceID = false;
            if ($force && $force !== 'ours' && $force !== 'theirs') {
                throw new \Exception("Invalid force option: {$force}");
            } elseif ($force) {
                $forceID = $force == 'ours' ? Git::FORCE_OURS : Git::FORCE_THEIRS;
            }
            if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $force_cmd = $force ? " -s recursive -X{$force} " : ' ';
                $output->writeln("Running: <comment>git pull{$force_cmd}{$repo} {$latest}</comment>");
            }

            $updated = $git->pull($repo, $latest, false, $forceID);
            if ($updated !== true) {
                $output->writeln("<error>Unable to complete update</error>");
                $output->writeln("Details:");
                foreach (explode("\r\n", $updated) as $line) {
                    $output->writeln($line);
                }
            } else {
                $output->writeln("\nTo get back to your previous environment temporarily run:");
                $output->writeln("<comment>git checkout {$test_branch}</comment>");
                $output->writeln("\nTo undo this update permanently run:");
                $output->writeln("<comment>git reset --hard {$last['sha1']}");
            }
        } else {
            $git->checkout($latest);
        }
    }
}

