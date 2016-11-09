<?php

define("__TEST_DIRECTORY__", __DIR__."/testData");
$test_url = getenv("TEST_URL");
define("__TEST_URL__", ($test_url !== false ? $test_url : "http://192.168.56.101"));
$browser = getenv("BROWSER");
define("__BROWSER__", ($browser !== false ? $browser : "firefox"));

require_once(__DIR__ . '/../site/app/libraries/AutoLoader.php');
use \app\libraries\AutoLoader;

AutoLoader::registerDirectory(__DIR__."/unitTests", true, "tests\\unitTests");
AutoLoader::registerDirectory(__DIR__."/../site/app", true, "app");
