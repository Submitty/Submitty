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
    "generate_metadata",
    "validate_users"
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
$auth = $core->getAuthentication();

if (!$auth instanceof SamlAuthentication) {
    echo "Warning: SAML not enabled." . PHP_EOL;
    $auth = new SamlAuthentication($core);

}

switch ($action) {
    case "add_users":
        add_users($core, $auth);
        break;
    case "generate_metadata":
        generate_metadata($core, $auth);
        break;
    case "validate_users":
        validate_users($core, $auth);
        break;
}

function add_users(Core $core, SamlAuthentication $auth) {
    $core->loadMasterDatabase();
    $users = $core->getQueries()->getAllSubmittyUsers();
    $added = 0;
    $skipped = 0;
    $usernames = [];
    foreach ($users as $user) {
        $usernames[] = $user->getId();
    }
    $auth->setValidUsernames($usernames);
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

function generate_metadata(Core $core, SamlAuthentication $saml_auth) {
    $metadata = $saml_auth->getMetaData();

    $path = FileUtils::joinPaths($core->getConfig()->getConfigPath(), 'saml', 'sp_metadata.xml');
    FileUtils::writeFile($path, $metadata);

    echo "Metadata written to {$path}" . PHP_EOL;
}

function validate_users(Core $core, SamlAuthentication $saml_auth) {
    $core->loadMasterDatabase();
    $user_checks = [];
    $proxy_users = $core->getQueries()->getProxyMappedUsers();
    foreach ($proxy_users as $proxy_user) {
        $user_checks[] = $proxy_user['user_id'];
        $user_checks[] = $proxy_user['saml_id'];
    }
    $saml_users = $core->getQueries()->getSamlMappedUsers();
    foreach ($saml_users as $saml_user) {
        $user_checks[] = $saml_user['user_id'];
    }
    $saml_auth->setValidUsernames($user_checks);
    foreach ($proxy_users as $proxy_user) {
        if ($saml_auth->isInvalidUsername($proxy_user['saml_id'])) {
            echo "Proxy user " . $proxy_user['user_id'] . " has invalid SAML ID: " . $proxy_user['saml_id'] . "\n";
        }
        if ($saml_auth->isValidUsername($proxy_user['user_id'])) {
            echo "Proxy user " . $proxy_user['user_id'] . " has a Submitty user ID which is a valid SAML ID\n";
        }
    }
    foreach ($saml_users as $saml_user) {
        if ($saml_auth->isInvalidUsername($saml_user['user_id'])) {
            echo "SAML user " . $saml_user['user_id'] . " has a SAML ID which is not valid\n";
        }
    }
}
