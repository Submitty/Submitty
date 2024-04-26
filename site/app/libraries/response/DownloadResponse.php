<?php

namespace app\libraries\response;

use app\libraries\Core;

/**
 * Class DownloadResponse
 * @package app\libraries\response
 */
class DownloadResponse implements ResponseInterface {
    /** @var array json encoded array */
    public $json;

    /**
     * DownloadResponse constructor.
     * Returns a Jsend format download response in the same format as
     * JsonResponse
     * (see http://submitty.org/developer/json_responses)
     * @param string $type
     * @param mixed|null $data
     * @param string|null $message
     * @param string|null $code
     */
    private function __construct(string $type, $data = null, $message = null, $is_web = false, $code = null) {

        if (!$is_web) {
            $this->json['status'] = $type;

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
        else {
            if ($data || $type === 'success') {
                $this->json = $data;
            }
            if ($message || $type !== 'success') {
                $this->json = $type . ' ' . $message;
            }
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
     * Returns a success DownloadResponse.
     * @param mixed|null $data
     * @return DownloadResponse
     */
    public static function getSuccessResponse($data = null, $is_web = false): DownloadResponse {
        return new self('success', $data, null, $is_web);
    }

    /**
     * Returns a fail DownloadResponse.
     * @param string $message
     * @param mixed|null $data
     * @return DownloadResponse
     */
    public static function getFailResponse($message, $data = null, $is_web = false): DownloadResponse {
        return new self('fail', $data, $message, $is_web);
    }

    /**
     * Returns an error DownloadResponse.
     * @param string $message
     * @param mixed|null $data
     * @param string|null $code
     * @return DownloadResponse
     */
    public static function getErrorResponse($message, $data = null, $code = null, $is_web = false): DownloadResponse {
        return new self('error', $data, $message, $code, $is_web);
    }
}
