<?php

define('__TEST_URL__', getenv('TEST_URL').(getenv('TEST_URL')[strlen(getenv('TEST_URL'))-1] == "/" ? "" : "/"));
define("__TEST_DIRECTORY__", __DIR__."/testData");

require_once(__DIR__ . '/../TAGradingServer/lib/AutoLoader.php');
require_once(__DIR__ . '/test.php');
use lib\AutoLoader;
AutoLoader::registerDirectory(__DIR__."/../TAGradingServer/lib", true, "lib");
AutoLoader::registerDirectory(__DIR__."/../TAGradingServer/app", true, "app");
AutoLoader::registerDirectory(__DIR__."/functionalTests", true, "tests\\functionalTests");