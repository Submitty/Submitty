<?php

$finder = PhpCsFixer\Finder::create()
    ->exclude('node_modules')
    ->exclude('vendor')
    ->exclude('cache')
    ->in(__DIR__)
;

$config = new PhpCsFixer\Config();
return $config->setRules([
])
    ->setFinder($finder)
    ;
