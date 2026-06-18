#!/usr/bin/env php
<?php

/*
 * This script runs the Doctrine console
 *
 * Trying to perform interactions on course databases
 * through this will fail
 *
 * Usage (this will show commands): ./doctrine.php
 */

require_once __DIR__ . "/../site/vendor/autoload.php";

use app\libraries\Core;
use Doctrine\ORM\Tools\Console\ConsoleRunner;
use Doctrine\ORM\Tools\Console\EntityManagerProvider\SingleManagerProvider;

$core = new Core();

$core->loadMasterConfig();
$core->loadMasterDatabase();

ConsoleRunner::run(
    new SingleManagerProvider($core->getSubmittyEntityManager())
);
