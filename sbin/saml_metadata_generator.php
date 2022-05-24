<?php

use app\authentication\SamlAuthentication;
use app\libraries\Core;
use app\libraries\FileUtils;

require_once __DIR__ . '/../site/vendor/autoload.php';

$core = new Core();
$core->loadMasterConfig();

$saml_auth = new SamlAuthentication($core);
$metadata = $saml_auth->getMetaData();

$path = FileUtils::joinPaths($core->getConfig()->getConfigPath(), 'saml', 'sp_metadata.xml');
FileUtils::writeFile($path, $metadata);

echo "Metadata written to {$path}" . PHP_EOL;
