<?php


namespace app\libraries\response;

use app\libraries\Core;

/**
 * Class AbstractResponse
 * @package app\libraries\response
 */
abstract class AbstractResponse {
    /**
     * Calls the core to render the response
     *
     * @param Core $core
     * @return void
     */
    abstract public function render(Core $core);
}
