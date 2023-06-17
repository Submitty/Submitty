<?php

namespace app\libraries\response;

use app\libraries\Core;

/**
 * Class Response
 * @package app\libraries\response
 */
class MultiResponse implements ResponseInterface {
    /** @var null|WebResponse  */
    public $web_response = null;

    /** @var null|JsonResponse  */
    public $json_response = null;

    /** @var null|RedirectResponse */
    public $redirect_response = null;

    /**
     * Response constructor.
     * @param JsonResponse|null $json_response
     * @param WebResponse|null $web_response
     * @param RedirectResponse|null $redirect_response
     */
    public function __construct(
        JsonResponse $json_response = null,
        WebResponse $web_response = null,
        RedirectResponse $redirect_response = null
    ) {
        $this->json_response = $json_response;
        $this->web_response = $web_response;
        $this->redirect_response = $redirect_response;
    }

    /**
     * @deprecated should not be used, just return WebResponse directly
     *
     * @param WebResponse $web_response
     * @return MultiResponse
     */
    public static function webOnlyResponse(WebResponse $web_response): MultiResponse {
        return new self(null, $web_response, null);
    }

    /**
     * @deprecated should not be used, just return JsonResponse directly
     *
     * @param JsonResponse $json_response
     * @return MultiResponse
     */
    public static function JsonOnlyResponse(JsonResponse $json_response): MultiResponse {
        return new self($json_response, null, null);
    }

    /**
     * @deprecated should not be used, just return RedirectResponse directly
     *
     * @param RedirectResponse $redirect_response
     * @return MultiResponse
     */
    public static function RedirectOnlyResponse(RedirectResponse $redirect_response): MultiResponse {
        return new self(null, null, $redirect_response);
    }


    /**
     * Convert this MultiResponse into a json response instead
     * Usefull when dealing with sending messages back to controllers
     *
     * @return JsonResponse
     */
    public function convertToJsonResponse(): JsonResponse {
        if (!is_null($this->json_response)) {
            return $this->json_response;
        }

        if (!is_null($this->redirect_response)) {
            return JsonResponse::getErrorResponse("Error handling request, redirecting", $this->redirect_response->url, 302);
        }

        if (!is_null($this->web_response)) {
            return JsonResponse::getErrorResponse("Webresponse not supported for this request type");
        }

        return JsonResponse::getFailResponse("Cannot handle request");
    }

    /**
     * Renders the response.
     *
     * If there is only JSON or web view, render it. If two kinds
     * of responses exist, check if rendering web view is enabled,
     * if so, render the web view; if not, render JSON. Note that
     * the latter case normally occurs in an API call.
     */
    public function render(Core $core): void {
        $web_response = $this->redirect_response ? $this->redirect_response : $this->web_response;
        if ($web_response && is_null($this->json_response)) {
            $web_response->render($core);
        }
        elseif ($this->json_response && is_null($web_response)) {
            $this->json_response->render($core);
        }
        elseif ($core->getOutput()->getRender()) {
            $web_response->render($core);
        }
        else {
            $this->json_response->render($core);
        }
    }
}
