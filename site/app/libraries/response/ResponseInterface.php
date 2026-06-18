<?php

namespace app\libraries\response;

use app\libraries\Core;

/**
 * Class AbstractResponse
 * @package app\libraries\response
 */
interface ResponseInterface {
    /**
     * Renders the response
     */
    public function render(Core $core): void;
}
