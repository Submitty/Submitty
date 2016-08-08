<?php

require_once(__DIR__ . '/../site/app/libraries/AutoLoader.php');
use \app\libraries\AutoLoader;

AutoLoader::registerDirectory(__DIR__."/../site/app", true, "app");
AutoLoader::registerDirectory(__DIR__."/e2e", true, "tests\\e2e");