<?php
require 'site/vendor/autoload.php';

$core = new \app\libraries\Core();
$config = $core->getConfig();
echo "Config path: " . $config->getConfigPath() . "\n";
