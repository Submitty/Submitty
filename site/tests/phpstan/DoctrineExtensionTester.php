<?php

namespace tests\phpstan;

use app\libraries\FileUtils;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Configuration as ORMConfiguration;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

// Use Symfony Cache (PSR-6 compatible) instead of deprecated doctrine/cache
$cache = new ArrayAdapter();
$config = new ORMConfiguration();
$config->setMetadataDriverImpl(new AttributeDriver([FileUtils::joinPaths(__DIR__, '..', '..', 'site', 'app', 'entities')]));
$config->setMetadataCache($cache);
$config->setQueryCache($cache);
$config->setProxyDir(sys_get_temp_dir());
$config->setProxyNamespace('DoctrineProxies');
$config->setAutoGenerateProxyClasses(true);
$conn = DriverManager::getConnection([
    'driver' => 'pdo_sqlite',
    'serverVersion' => '14.2'
]);
return new EntityManager($conn, $config);
