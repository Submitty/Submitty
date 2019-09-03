<?php declare(strict_types = 1);

namespace tests\utils;

use app\libraries\Core;
use app\libraries\Output;

class NullOutput extends Output{
    public function __construct(Core $core) {
        $this->core = $core;
    }

    public function disableRender() {
    }

    public function getRender() {
    }

    public function loadTwig($full_load = true) {
    }

    public function setInternalResources() {
    }

    public function renderOutput() {
    }

    public function renderTemplate() {
    }

    /**
     * Please avoid using this function unless absolutely necessary.
     * Please use renderJsonSuccess, renderJsonFail and renderJsonError
     * instead to ensure JSON responses have consistent format.
     * @param $json
     */
    public function renderJson($json) {
        $this->output_buffer = json_encode($json, JSON_PRETTY_PRINT);
        $this->useFooter(false);
        $this->useHeader(false);
    }

    /**
     * Renders a json response for the "success" case
     *  (see http://submitty.org/developer/json_responses)
     * @param mixed|null $data Response data
     * @return array the unencoded response
     */
    public function renderJsonSuccess($data = null) {
        $response = [
            'status' => 'success',
            'data' => $data
        ];

        $this->renderJson($response);

        // Because sometimes the controllers want to return the response array
        return $response;
    }

    /**
     * Renders a json response for the "fail" case
     *  (see http://submitty.org/developer/json_responses)
     * @param string $message A non-blank failure message
     * @param mixed|null $data Response data
     * @param array $extra Extra data merged into the response array
     * @return array the unencoded response
     */
    public function renderJsonFail($message, $data = null, $extra = []) {
        $response = [
            'status' => 'fail',
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        // Merge $response second so it overwrites conflicting keys in $extra
        $response = array_merge($extra, $response);
        $this->renderJson($response);

        // Because sometimes the controllers want to return the response array
        return $response;
    }

    /**
     * Renders a json response for the "error" case
     *  (see http://submitty.org/developer/json_responses)
     * @param string $message A non-blank error message
     * @param mixed|null $data Response data
     * @param int $code Code to identify error case
     * @return array the unencoded response
     */
    public function renderJsonError($message, $data = null, $code = null) {
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
        $this->renderJson($response);

        // Because sometimes the controllers want to return the response array
        return $response;
    }

    /**
     * Renders success/error messages and/or JSON responses.
     * @param $message
     * @param bool $success
     * @param bool $show_msg
     * @return array
     */
    public function renderResultMessage($message, $success = true, $show_msg = true) {
        if ($show_msg == true) {
            if ($success) {
                $this->core->addSuccessMessage($message);
            }
            else {
                $this->core->addErrorMessage($message);
            }
        }

        if ($success === true) {
            return $this->renderJsonSuccess($message);
        } else {
            return $this->renderJsonFail($message);
        }
    }

    public function renderString($string) {
    }

    public function renderFile($contents, $filename, $filetype = "text/plain") {
    }

    /**
     * Render a Twig template from the templates directory
     * @param string $filename Template file basename, file should be in site/app/templates
     * @param array $context Associative array of variables to pass into the Twig renderer
     * @return string Rendered page content
     */
    public function renderTwigTemplate($filename, $context = []) {
    }

    public function renderTwigOutput($filename, $context = []) {
    }

    public function getOutput() {
        $return = "";
        $return .= $this->renderHeader();
        $return .= $this->output_buffer;
        $return .= $this->renderFooter();
        return $return;
    }

    private function renderHeader() {
    }

    private function renderFooter() {
    }

    public function bufferOutput() {
        return $this->buffer_output;
    }

    public function disableBuffer() {
    }

    /**
     * Returns the stored output buffer that we've been building
     *
     * @return string
     */
    public function displayOutput() {
        echo($this->getOutput());
    }

    public function showException($exception = "", $die = true) {
    }

    public function showError($error = "", $die = true) {
    }

    public function addInternalCss($file, $folder='css') {
    }

    public function addVendorCss($file) {
    }

    public function addCss($url) {
    }

    public function addInternalJs($file, $folder='js') {
    }

    public function addVendorJs($file) {
    }

    public function addJs($url) {
    }

    public function timestampResource($file, $folder) {
    }

    public function useHeader($bool = true) {
    }

    public function useFooter($bool = true) {
    }

    public function addBreadcrumb($string, $url=null, $external_link=false) {
    }

    public function addRoomTemplatesTwigPath() {
    }

    public function getBreadcrumbs() {
    }

    public function getCss() {
    }

    public function getJs() {
    }

    public function getRunTime() {
    }
}