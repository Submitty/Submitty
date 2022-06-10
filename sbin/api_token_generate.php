#!/usr/bin/env php
<?php

/*
 * This script will generate and output an API token for the
 * specified user ID. It will first check that the given ID
 * is a valid Submitty user.
 *
 * Usage: ./api_token_generate.php <User ID>
 */

require_once __DIR__ . "/../site/vendor/autoload.php";

use app\libraries\Core;
use app\libraries\TokenManager;

if ($argc != 2) {
    echo "Invalid number of args\n";
    exit(1);
}

$user_id = $argv[1];

$core = new Core();

$core->loadMasterConfig();
$core->initializeTokenManager();
$core->loadMasterDatabase();

if ($core->getQueries()->getSubmittyUser($user_id) === null) {
    echo "Submitty user not found with ID given\n";
    exit(1);
}

$core->getQueries()->refreshUserApiKey($user_id);
$api_key = $core->getQueries()->getSubmittyUserApiKey($user_id);

$token = TokenManager::generateApiToken($api_key);

echo $token->toString();
