<?php


namespace app\libraries\response;

use app\libraries\Core;


/**
 * Class Response
 * @package app\libraries\response
 */
class Response extends AbstractResponse {
    /** @var null | WebResponse  */
    protected $web_response = null;

    /** @var null | JsonResponse  */
    protected $json_response = null;

    /**
     * Response constructor.
     * @param JsonResponse|null $json_response
     * @param WebResponse|null $web_response
     */
    public function __construct(JsonResponse $json_response = null, WebResponse $web_response = null) {
        $this->json_response = $json_response;
        $this->web_response = $web_response;
    }

    /**
     * @param WebResponse $web_response
     */
    public function setWebResponse(WebResponse $web_response) {
        $this->web_response = $web_response;
    }

    /**
     * @param JsonResponse $json_response
     */
    public function setJsonResponse(JsonResponse $json_response) {
        $this->json_response = $json_response;
    }

    /**
     * Renders the response.
     *
     * If there is only JSON or web view, render it. If two kinds
     * of responses exist, check if rendering web view is enabled,
     * if so, render the web view; if not, render JSON. Note that
     * the latter case normally occurs in an API call.
     *
     * @param Core $core
     */
    public function render(Core $core) {
        if ($this->web_response && is_null($this->json_response)) {
            $this->web_response->render($core);
        }
        elseif ($this->json_response && is_null($this->web_response)) {
            $this->json_response->render($core);
        }
        elseif ($core->getOutput()->getRender()) {
            $this->web_response->render($core);
        }
        else {
            $this->json_response->render($core);
        }
    }
}