<?php

namespace socket;

use app\libraries\FileUtils;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use app\libraries\Core;
use app\libraries\socket\Server;
use React\EventLoop\Loop;
use React\Socket\UnixServer;

require_once(__DIR__ . '/../vendor/autoload.php');

$core = new Core();

$core->loadMasterConfig();
$core->initializeTokenManager();
/** @noinspection PhpUnhandledExceptionInspection */
$core->loadMasterDatabase();
/** @noinspection PhpUnhandledExceptionInspection */
$core->loadAuthentication();
$core->loadCourseDatabase();
$core->getOutput()->loadTwig();
$core->getOutput()->setInternalResources();

$ws_server = new WsServer(new Server($core));

umask(0117); // u=rw, g=rw

$socket_path = FileUtils::joinPaths(
    $core->getConfig()->getSubmittyPath(),
    "run",
    "websocket",
    "server.sock"
);

if (file_exists($socket_path)) {
    unlink($socket_path);
}

$server = new IoServer(
    new HttpServer($ws_server),
    new UnixServer($socket_path),
    Loop::get()
);

//send messages every 30 seconds to keep active connections alive
$ws_server->enableKeepAlive($server->loop);

$server->run();
