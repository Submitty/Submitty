<?php


namespace app\libraries\response;

use app\libraries\Core;


/**
 * Class JsonResponse
 * @package app\libraries\response
 */
class JsonResponse extends AbstractResponse {

    /** @var array json encoded array */
    protected $json;

    /**
     * JsonResponse constructor.
     * @param array $json
     */
    public function __construct($json) {
        $this->json = $json;
    }

    /**
     * Renders JSON data.
     * @param Core $core
     */
    public function render(Core $core) {
        $core->getOutput()->renderJson($this->json);
    }

    /**
     * Returns a json response for the "success" case
     *  (see http://submitty.org/developer/json_responses)
     * @param mixed|null $data Response data
     * @return array the unencoded response
     */
    static public function success($data = null) {
        $response = [
            'status' => 'success',
            'data' => $data
        ];

        return $response;
    }

    /**
     * Returns a json response for the "fail" case
     *  (see http://submitty.org/developer/json_responses)
     * @param string $message A non-blank failure message
     * @param mixed|null $data Response data
     * @return array the unencoded response
     */
    static public function fail($message, $data = null) {
        $response = [
            'status' => 'fail',
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return $response;
    }

    /**
     * Returns a json response for the "error" case
     *  (see http://submitty.org/developer/json_responses)
     * @param string $message A non-blank error message
     * @param mixed|null $data Response data
     * @param int $code Code to identify error case
     * @return array the unencoded response
     */
    static public function error($message, $data = null, $code = null) {
        $response = [
            'status' => 'error',
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }
        if ($code !== null) {
            $response['code'] = $code;
        }

        return $response;
    }
}