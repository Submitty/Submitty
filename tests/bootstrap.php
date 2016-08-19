<?php

define("__TEST_DIRECTORY__", __DIR__."/testData");

require_once(__DIR__ . '/../site/app/libraries/AutoLoader.php');
use \app\libraries\AutoLoader;

AutoLoader::registerDirectory(__DIR__."/../site/app", true, "app");