#!/usr/bin/env php
<?php

/*
 * Update Browscap cache
 * Browscap project provides browscap.ini which is required to detect browser's information,
 * if there is no update then a new copy isn't downloaded.
 *
 * Usage: ./update_browscap.php [<CACHE_USER>]
 */
require_once __DIR__ . "/../site/vendor/autoload.php";

// if some problem related to browscap-php is encountered then try removing all the contents of cache_dir and run this script
$cache_dir = __DIR__ . '/../site/vendor/browscap/browscap-php/resources';
$cache_user = isset($argv[1]) ? escapeshellarg($argv[1]) : "\${PHP_USER}";

$file_cache = new \League\Flysystem\Local\LocalFilesystemAdapter($cache_dir);
$filesystem = new \League\Flysystem\Filesystem($file_cache);
$cache = new \MatthiasMullie\Scrapbook\Psr16\SimpleCache(
    new \MatthiasMullie\Scrapbook\Adapters\Flysystem($filesystem)
);
$logger = new \Psr\Log\NullLogger();
$bc = new \BrowscapPHP\BrowscapUpdater($cache, $logger);
// create an active/warm cache
$bc->update(\BrowscapPHP\Helper\IniLoaderInterface::PHP_INI);

`chown -R ${cache_user}:${cache_user} ${cache_dir};`;
?>
