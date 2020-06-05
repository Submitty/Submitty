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
     * JsonResponse constructor.
     * Returns a Jsend format json response
     * (see http://submitty.org/developer/json_responses)
     * @param $type
     * @param mixed|null $data
     * @param string|null $message
     * @param string|null $code
     */
    private function __construct($type, $data = null, $message = null, $code = null) {
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
    }

    /**
     * Renders JSON data.
     * @param Core $core
     */
    public function render(Core $core): void {
        $core->getOutput()->renderJson($this->json);
    }

    /**
     * Returns a success JsonResponse.
     * @param mixed|null $data
     * @return JsonResponse
     */
    public static function getSuccessResponse($data = null) {
        return new self('success', $data);
    }

    /**
     * Returns a fail JsonResponse.
     * @param string $message
     * @param mixed|null $data
     * @return JsonResponse
     */
    public static function getFailResponse($message, $data = null) {
        return new self('fail', $data, $message);
    }

    /**
     * Returns an error JsonResponse.
     * @param string $message
     * @param mixed|null $data
     * @param string|null $code
     * @return JsonResponse
     */
    public static function getErrorResponse($message, $data = null, $code = null) {
        return new self('error', $data, $message, $code);
    }
}
