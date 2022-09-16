#!/usr/bin/env php
<?php

/*
 * Update Browscap cache
 * Browscap project provides browscap.ini which is required to detect browser's information,
 * if there is no update then a new copy isn't downloaded.
 *
 * Usage: ./update_browscap.php
 */
require_once __DIR__ . "/../site/vendor/autoload.php";

/* If some problem related to browscap-php is encountered then try removing all the contents of cache_dir and run .setup/install_submitty/install_site.sh.
   If you are running this script independently, make sure that PHP_USER can access the cache by running
   "chown -R ${PHP_USER}:${PHP_USER} ${SUBMITTY_INSTALL_DIR}/site/vendor/browscap/browscap-php/resources;" after the successful execution of this script. */

$cache_dir = __DIR__ . '/../site/vendor/browscap/browscap-php/resources';

$file_cache = new \League\Flysystem\Local\LocalFilesystemAdapter($cache_dir);
$filesystem = new \League\Flysystem\Filesystem($file_cache);
$cache = new \MatthiasMullie\Scrapbook\Psr16\SimpleCache(
    new \MatthiasMullie\Scrapbook\Adapters\Flysystem($filesystem)
);
$logger = new \Psr\Log\NullLogger();
$bc = new \BrowscapPHP\BrowscapUpdater($cache, $logger);
// create an active/warm cache
$bc->update(\BrowscapPHP\Helper\IniLoaderInterface::PHP_INI);
?>
