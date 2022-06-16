#!/usr/bin/env php
<?php

/*
 * This script provides a couple of utilities to run
 * while setting up SAML on their system.
 *
 * Usage: ./saml_utils.php <action>
 *
 * Actions: add_users, generate_metadata
 */

$long_opts = [
    "add_users",
    "generate_metadata"
];

$options = getopt(
    "",
    $long_opts
);

if (empty($options)) {
    echo "No option specified. Please refer to SAML documentation to use this." . PHP_EOL;
    exit();
}

$action = null;

// Verify an option is selected
foreach ($long_opts as $option) {
    if (array_key_exists($option, $options)) {
        if ($action !== null) {
            echo "Cannot specify multiple options!" . PHP_EOL;
            exit();
        }
        $action = $option;
    }
}

require_once __DIR__ . '/../site/vendor/autoload.php';

use app\authentication\SamlAuthentication;
use app\libraries\Core;
use app\libraries\FileUtils;

$core = new Core();
$core->loadMasterConfig();
$core->loadAuthentication();

if (!$core->getAuthentication() instanceof SamlAuthentication) {
    echo "Warning: SAML not enabled." . PHP_EOL;
}

switch ($action) {
    case "add_users":
        add_users($core);
        break;
    case "generate_metadata":
        generate_metadata($core);
        break;
}

function add_users(Core $core) {
    $core->loadMasterDatabase();
    $auth = $core->getAuthentication();
    $users = $core->getQueries()->getAllSubmittyUsers();
    $added = 0;
    $skipped = 0;
    foreach ($users as $user) {
        if ($auth->isValidUsername($user->getId())) {
            $core->getQueries()->insertSamlMapping($user->getId(), $user->getId());
            $added++;
        }
        else {
            $skipped++;
        }
    }
    echo "Added {$added} users to mapping and skipped {$skipped} users." . PHP_EOL;
}

function generate_metadata(Core $core) {
    $saml_auth = $core->getAuthentication();
    $metadata = $saml_auth->getMetaData();

    $path = FileUtils::joinPaths($core->getConfig()->getConfigPath(), 'saml', 'sp_metadata.xml');
    FileUtils::writeFile($path, $metadata);

    echo "Metadata written to {$path}" . PHP_EOL;
}
