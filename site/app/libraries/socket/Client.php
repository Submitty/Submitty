<?php

declare(strict_types=1);

namespace app\libraries\socket;

use app\libraries\Core;
use WebSocket;

class Client extends WebSocket\Client {
    public function __construct(Core $core) {
        parent::__construct('ws://127.0.0.1:41983/', [
            'headers' => [
                'Session-Secret' => $core->getConfig()->getSecretSession()
            ]
        ]);
    }

    public function send($payload, $opcode = 'text', $masked = true) {
        parent::send(json_encode($payload), $opcode, $masked);
    }
}
