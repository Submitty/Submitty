<?php

namespace tests\phpstan;

use app\libraries\FileUtils;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

// Use Symfony Cache (PSR-6 compatible) instead of deprecated doctrine/cache
$cache = new ArrayAdapter();
$config = ORMSetup::createAttributeMetadataConfiguration(
    [FileUtils::joinPaths(__DIR__, '..', '..', 'site', 'app', 'entities')],
    true,
    null,
    $cache
);
$conn = DriverManager::getConnection([
    'driver' => 'pdo_sqlite',
    'serverVersion' => '14.2'
]);
return new EntityManager($conn, $config);
