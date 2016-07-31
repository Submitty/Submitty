<?php

define('__TEST_URL__', getenv('TEST_URL').(getenv('TEST_URL')[strlen(getenv('TEST_URL'))-1] == "/" ? "" : "/"));
define("__TEST_DIRECTORY__", __DIR__."/testData");

require_once(__DIR__ . '/../site/app/libraries/AutoLoader.php');
require_once(__DIR__ . '/test.php');
use \app\libraries\AutoLoader;

AutoLoader::registerDirectory(__DIR__."/../site/app", true, "app");
AutoLoader::registerDirectory(__DIR__."/e2e", true, "tests\\e2e");

/*
use lib\Database;
Database::connect(__DATABASE_HOST__, __DATABASE_USER__, __DATABASE_PASSWORD__, __DATABASE_NAME__);
*/