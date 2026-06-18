<?php

namespace tests\phpstan;

use app\libraries\FileUtils;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\DBAL\DriverManager;

$config = ORMSetup::createAttributeMetadataConfiguration([FileUtils::joinPaths(__DIR__, '..', '..', 'site', 'app', 'entities')], true);
$conn = DriverManager::getConnection([
    'driver' => 'pdo_sqlite',
    'serverVersion' => '14.2'
]);
return new EntityManager($conn, $config);
