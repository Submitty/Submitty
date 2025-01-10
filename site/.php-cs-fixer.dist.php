<?php

$finder = PhpCsFixer\Finder::create()
    ->exclude('node_modules')
    ->exclude('vendor')
    ->exclude('cache')
    ->in(__DIR__)
;

$config = new PhpCsFixer\Config();
return $config->setRules([
    '@PSR12' => true,
    'braces_position' => [
        'classes_opening_brace' => 'same_line',
        'functions_opening_brace' => 'same_line',
    ],
    'control_structure_continuation_position' => [
        'position' => 'next_line',
    ],
])->setFinder($finder);
