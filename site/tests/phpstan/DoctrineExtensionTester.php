<?php

namespace tests\phpstan;

use app\libraries\FileUtils;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

$config = ORMSetup::createAttributeMetadataConfiguration([FileUtils::joinPaths(__DIR__, '..', '..', 'site', 'app', 'entities')], true);
$conn = [
    'driver' => 'pdo_sqlite',
    'serverVersion' => '14.2'
];
return EntityManager::create($conn, $config);
