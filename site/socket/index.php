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

$ws_server = new WsServer(new Server($core));

$server = IoServer::factory(
    new HttpServer($ws_server),
    41983,
    '127.0.0.1'
);

//send messages every 30 seconds to keep active connections alive
$ws_server->enableKeepAlive($server->loop);

$server->run();
