<?php

namespace app\libraries\response;

use app\libraries\Core;

/**
 * Class JsonResponse
 * @package app\libraries\response
 */
class JsonResponse implements ResponseInterface {
    /** @var array json encoded array */
    public $json;

    
    /**
     * HTTP status code to send with the response. Defaults to 200 OK.
     * @var int
     */
    private $http_status_code;

    /**
     * JsonResponse constructor.
     * Returns a Jsend format json response
     * (see http://submitty.org/developer/json_responses)
     * @param string $type
     * @param mixed|null $data
     * @param string|null $message
     * @param string|null $code
     * @param int|null $http_status_code
     */
    private function __construct(string $type, $data = null, $message = null, $code = null, ?int $http_status_code = null) {
        $this->json = [
            'status' => $type
        ];

        if ($data || $type === 'success') {
            $this->json['data'] = $data;
        }

        if ($message || $type !== 'success') {
            $this->json['message'] = $message;
        }

        if ($code) {
            $this->json['code'] = $code;
        }

        $this->http_status_code = $http_status_code ?? 200;
    }

    /**
     * Renders JSON data.
     * @param Core $core
     */
    public function render(Core $core): void {
        http_response_code($this->http_status_code);
        $core->getOutput()->renderJson($this->json);
    }

    /**
     * Returns a success JsonResponse.
     * @param mixed|null $data
     * @return JsonResponse
     */
    public static function getSuccessResponse($data = null): JsonResponse {
        return new self('success', $data);
    }

    /**
     * Returns a fail JsonResponse.
     * @param string $message
     * @param mixed|null $data
     * @param int|null $http_status_code
     * @return JsonResponse
     */
    public static function getFailResponse($message, $data = null, ?int $http_status_code = null): JsonResponse {
        return new self('fail', $data, $message, null, $http_status_code);
    }

    /**
     * Returns an error JsonResponse.
     * @param string $message
     * @param mixed|null $data
     * @param string|null $error_code
     * @param int|null $http_status_code
     * @return JsonResponse
     */
    public static function getErrorResponse($message, $data = null, ?string $code = null, ?int $http_status_code = null): JsonResponse {
        return new self('error', $data, $message, $code, $http_status_code);
    }
}
