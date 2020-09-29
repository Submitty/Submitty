<?php

namespace socket;

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use app\libraries\Core;
use app\libraries\socket\Server;

require_once(__DIR__ . '/../vendor/autoload.php');

$core = new Core();

$core->loadMasterConfig();
/** @noinspection PhpUnhandledExceptionInspection */
$core->loadMasterDatabase();
/** @noinspection PhpUnhandledExceptionInspection */
$core->loadAuthentication();
$core->loadCourseDatabase();
$core->getOutput()->loadTwig();
$core->getOutput()->setInternalResources();

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new Server($core)
        )
    ),
    41983,
    '127.0.0.1'
);

$server->run();
