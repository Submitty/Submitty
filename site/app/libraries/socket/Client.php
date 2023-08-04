<?php

declare(strict_types=1);

namespace app\libraries\socket;

use app\libraries\Core;
use WebSocket;

class Client extends WebSocket\Client {
    public function __construct(Core $core) {
        $url = parse_url($core->getConfig()->getBaseUrl());
        $uri = sprintf(
            '%s://%s:%d/ws',
            str_replace('http', 'ws', $url['scheme']),
            $url['host'],
            $core->getConfig()->getWebsocketPort()
        );

        parent::__construct($uri, [
            'headers' => [
                'Session-Secret' => $core->getConfig()->getSecretSession()
            ]
        ]);
    }

    /**
     * Send a JSON encoded payload
     *
     * @param mixed $json
     */
    public function json_send($json): void {
        $this->send(json_encode($json));
    }
}
