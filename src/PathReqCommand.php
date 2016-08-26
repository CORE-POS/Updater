<?php

namespace COREPOS\Updater;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;

class PathReqCommand extends Command
{
    protected function configure()
    {
        $this->addArgument('path', InputArgument::REQUIRED, 'Filesystem location of repo to update');
    }
}

