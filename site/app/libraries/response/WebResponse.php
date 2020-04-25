<?php

namespace app\libraries\response;

use app\libraries\Core;

/**
 * Class WebResponse
 * @package app\libraries\response
 */
class WebResponse implements ResponseInterface {
    /** @var string|array */
    public $view_class;

    /** @var string */
    public $view_function;

    /** @var array  */
    public $parameters;

    /**
     * WebResponse constructor.
     *
     * The first argument is a string if it's a top level view or an
     * array of strings if its a view in a subdirectory/sub-namespace.
     *
     * @param $view_class
     * @param $view_function
     * @param mixed ...$parameters
     */
    public function __construct($view_class, $view_function, ...$parameters) {
        $this->view_class = $view_class;
        $this->view_function = $view_function;
        $this->parameters = $parameters;
    }

    /**
     * Renders the view.
     *
     * @param Core $core
     */
    public function render(Core $core): void {
        $core->getOutput()->renderOutput($this->view_class, $this->view_function, ...$this->parameters);
    }
}
