#!/usr/bin/env php
<?php

/*
 * Update Browscap cache
 * Browscap project provides browscap.ini which is required to detect browser's information,
 * if there is no update then a new copy isn't downloaded.
 *
 * Usage: ./update_browscap.php
 */

use BrowscapPHP\BrowscapUpdater;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

require_once __DIR__ . "/../site/vendor/autoload.php";

/* If some problem related to browscap-php is encountered then try removing all the contents of cache_dir and run .setup/install_submitty/install_site.sh.
   If you are running this script independently, make sure that PHP_USER can access the cache by running
   "chown -R ${PHP_USER}:${PHP_USER} ${SUBMITTY_INSTALL_DIR}/site/vendor/browscap/browscap-php/resources;" after the successful execution of this script. */

$cache_dir = __DIR__ . '/../site/vendor/browscap/browscap-php/resources';

$fs_adapter = new FilesystemAdapter("", 0, $cache_dir);
$cache = new Psr16Cache($fs_adapter);
$logger = new NullLogger();
$bc = new BrowscapUpdater($cache, $logger);
// create an active/warm cache
$bc->update();
?>
