<?php

namespace socket;

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

require_once(__DIR__.'/../vendor/autoload.php');

$server = IoServer::factory(
    new HttpServer(
            new WsServer(
                new Server()
            )
    ),
    8080,
    '127.0.0.1'
);

$server->run();
