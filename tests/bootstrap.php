<?php

define("__TEST_DATA__", __DIR__."/testData");

require_once(__DIR__ . '/../site/app/libraries/AutoLoader.php');
use \app\libraries\AutoLoader;

AutoLoader::registerDirectory(__DIR__."/unitTests", true, "tests\\unitTests");
AutoLoader::registerDirectory(__DIR__."/../site/app", true, "app");
