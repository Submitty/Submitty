<?php

namespace app\libraries\response;

use app\libraries\Core;

/**
 * Class DownloadResponse
 * @package app\libraries\response
 */
class DownloadResponse implements ResponseInterface {
    /** @var array<mixed> json encoded array */
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
     * @param bool $is_web
     */
    private function __construct(string $type, mixed $data = null, string|null $message = null, bool $is_web = false, string|null $code = null) {

        if (!$is_web) {
            $this->json['status'] = $type;

            if ($type === 'success') {
                $this->json['data'] = $data;
            }

            if ($type !== 'success') {
                $this->json['message'] = $message;
            }

            if ($code !== null) {
                $this->json['code'] = $code;
            }
        }
        else {
            if ($type === 'success') {
                $this->json = $data;
            }
            if ($type !== 'success') {
                $this->json = [$type . ' ' . $message];
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
     * @param bool $is_web
     * @return DownloadResponse
     */
    public static function getSuccessResponse(mixed $data = null, bool $is_web = false): DownloadResponse {
        return new self('success', $data, null, $is_web);
    }

    /**
     * Returns a fail DownloadResponse.
     * @param string $message
     * @param bool $is_web
     * @return DownloadResponse
     */
    public static function getFailResponse(string $message, bool $is_web = false): DownloadResponse {
        return new self('fail', null, $message, $is_web);
    }

    /**
     * Returns an error DownloadResponse.
     * @param string $message
     * @param mixed|null $data
     * @param string|null $code
     * @param bool $is_web
     * @return DownloadResponse
     */
    public static function getErrorResponse(string $message, mixed $data = null, string|null $code = null, bool $is_web = false): DownloadResponse {
        return new self('error', $data, $message, $is_web, $code);
    }
}
