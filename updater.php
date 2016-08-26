#!/bin/env/php
<?php

use COREPOS\Updater\ConfiguredApplication;
use COREPOS\Updater\UpdateAutoCommand;
use COREPOS\Updater\UpdateDevCommand;
use COREPOS\Updater\UpdateMajorCommand;
use COREPOS\Updater\UpdateMinorCommand;
use COREPOS\Updater\VersionCommand;

include(__DIR__ . '/vendor/autoload.php');

$application = new ConfiguredApplication('CORE Updater');
$application->add(new VersionCommand());
$application->add(new UpdateAutoCommand());
$application->add(new UpdateMinorCommand());
$application->add(new UpdateMajorCommand());
$application->add(new UpdateDevCommand());
$application->run();

