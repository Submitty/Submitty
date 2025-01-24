#!/usr/bin/env php
<?php

/*
 * This script will load lang files from json into php
 * to be cached and used by the site.
 * 
 * Usage: ./load_lang.php <json-dir> <cache-dir>
 */

require_once __DIR__ . "/../site/vendor/autoload.php";

use app\libraries\FileUtils;

$json_dir = $argv[1];
$cache_dir = $argv[2];

echo "\n";

$json_files = scandir($json_dir);
$found = 0;
$copied = 0;
if (gettype($json_files) === "array") {
    foreach ($json_files as $file) {
        $path = FileUtils::joinPaths($json_dir, $file);
        if (is_file($path) && str_ends_with($file, ".json")) {
            $found++;
            $data = FileUtils::readJsonFile($path);
            if (gettype($data) === "array") {
                try {
                    $body = "<?php\n\nreturn " . var_export($data, true) . ";\n";
                    $success = FileUtils::writeFile(FileUtils::joinPaths($cache_dir, str_replace(".json", ".php", $file)), $body);
                    if ($success) {
                        $copied++;
                    } else {
                        throw new Exception("Write Error");
                    }
                } catch (Throwable $e) {
                    error_log("Error loading lang file '" . $file . "': " . $e->getMessage());
                }
            } else {
                error_log("Error reading lang file '" . $file . "'");
            }
        }
    }
}

printf("Loaded [%d/%d] lang files to \"\e[32m%s\e[0m\"\n", $copied, $found, $argv[2]);

echo "\n";
