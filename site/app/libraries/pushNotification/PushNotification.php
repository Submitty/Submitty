<?php

declare(strict_types=1);

namespace app\libraries\pushNotification;

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;


class PushNotification extends WebPush {
    private $publicKey;
    private $privateKey;

    public function __construct() {
        // TODO Update this with PEM files
        $this->publicKey = "BFIIEZA1CinQVgXoJd_wSupTtmf6y09BZYDfEM2-pkonjFJeRQHh0EDGKyICwWMbdwD3OBmpsVKK8yL_LpiUtLE";
        $this->privateKey = "yXUvc1QuQOtGwSbkBW-mBt09HVmNTQBnjX5xcN7tuiY";
        $auth = [
            'VAPID' => [
                'subject' => 'mailto:jimickeyji@gmail.com',
                "publicKey" => $this->publicKey,
                "privateKey" => $this->privateKey,
            ],
        ];
        parent::__construct($auth);
    }
    public function sendSinglePushNotification($subscriptionObj) {
        var_dump($subscriptionObj);
        try {
            $notificationObj = [
                'subscription' => Subscription::create([
                    'endpoint' => $subscriptionObj["end"],
                    'publicKey' => $subscriptionObj["key"],
                    'contentEncoding' => $subscriptionObj['enc'],
                    'authToken' => $subscriptionObj['auth'],
                ]),
                'payload' => 'Sending a single push notification from PHP',
            ];
            $res = parent::sendOneNotification(
                $notificationObj['subscription'],
                $notificationObj['payload']
            );
            var_dump($res);
            return true;
        }
        catch (\Exception $exception) {
            return false;
        }
    }
}


