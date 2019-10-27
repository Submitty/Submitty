<?php

use Doctrine\Common\Annotations\AnnotationRegistry;

require_once(__DIR__.'/constants.php');

$loader = require(__DIR__.'/../vendor/autoload.php');
AnnotationRegistry::registerLoader([$loader, 'loadClass']);
