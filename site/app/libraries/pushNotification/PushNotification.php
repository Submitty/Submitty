<?php

declare(strict_types=1);

namespace app\libraries\pushNotification;

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;


class PushNotification {
    private $publicKey;
    private $privateKey;

    public function __construct(){
        $this->sendSinglePushNotification('https://updates.push.services.mozilla.com/wpush/v2/gAAAAABfS1c6HFtyath1uXkjL4in6fzbVZgeVIlEC5yhto-PGwDbQ4RN6asPLA9f44PdBKEqj-9sG6gC8OD2wbm-RHlnB20IW5yjskCv40C7QeYCmOWHue0TCUKnwqh-pE6PeyBdEBdUuktHND0VMD50U78SHcHE_SNPuiPsOBZhId0FdZvAmN0');
        // TODO Update this with PEM files
//        $this->publicKey = 'BCRmgusXnYzZxmeloZmg2Bg57k_v1ft0dWbRHyZVnT87QRmSoCCxnfFdKDHUQgnUu8F5aEM-qPfYPTKR2iOLI08';
//        $this->privateKey =  's1jShKXd_xU5H3ITPhXIRvdYADxSTdB4tW_0A6Lrx0I';
//        $auth = [
//            'VAPID' => [
//                'subject' => 'mailto:jimickeyji@gmail.com',
//                'publicKey' => $this->publicKey,
//                'privateKey' => $this->privateKey,
//            ],
//        ];
//        parent::__construct($auth);
    }
    public function sendSinglePushNotification($endpoint){
//        $this->publicKey = 'BCRmgusXnYzZxmeloZmg2Bg57k_v1ft0dWbRHyZVnT87QRmSoCCxnfFdKDHUQgnUu8F5aEM-qPfYPTKR2iOLI08';
//        $this->privateKey =  's1jShKXd_xU5H3ITPhXIRvdYADxSTdB4tW_0A6Lrx0I';
//
        $this->publicKey = "BFIIEZA1CinQVgXoJd_wSupTtmf6y09BZYDfEM2-pkonjFJeRQHh0EDGKyICwWMbdwD3OBmpsVKK8yL_LpiUtLE";
        $this->privateKey = "yXUvc1QuQOtGwSbkBW-mBt09HVmNTQBnjX5xcN7tuiY";
        $auth = [
            'VAPID' => [
                'subject' => 'mailto:jimickeyji@gmail.com',
                "publicKey" => $this->publicKey,
                "privateKey" => $this->privateKey,
            ],
        ];
        $webPushObj = new WebPush($auth);
        $notificationObj = [
            'subscription' => Subscription::create([
                'endpoint' => $endpoint, // Firefox 43+,
                'publicKey' => $this->publicKey, // base 64 encoded, should be 88 chars
                //'authToken' => 'CxVX6QsVToEGEcjfYPqXQw==',
            ]),
            'payload' => 'Sending a single push notification from PHP',
        ];
        $report = $webPushObj->sendOneNotification(
            $notificationObj['subscription'],
            $notificationObj['payload'] // optional (defaults null)
        );
        var_dump($report);
    }
}


// class banao phir ek method bnao jo ki send krega subscriptions

