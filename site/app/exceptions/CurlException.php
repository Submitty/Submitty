<?php

declare(strict_types=1);

namespace app\exceptions;

class CurlException extends \RuntimeException {
    public function __construct($curl_handle, $curl_return) {
        if ($curl_return === false) {
            parent::__construct("cURL error " . curl_error($curl_handle));
        }
        else {
            $http_code = curl_getinfo($curl_handle, CURLINFO_RESPONSE_CODE);
            parent::__construct("Invalid HTTP Code {$http_code}.");
        }
    }
}
