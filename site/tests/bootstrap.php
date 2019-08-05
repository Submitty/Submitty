<?php

use Doctrine\Common\Annotations\AnnotationRegistry;

define("__TEST_DATA__", __DIR__ . "/data");

$loader = require(__DIR__.'/../vendor/autoload.php');
AnnotationRegistry::registerLoader([$loader, 'loadClass']);
