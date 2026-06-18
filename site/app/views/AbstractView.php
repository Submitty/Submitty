<?php

namespace app\views;

use app\libraries\Core;
use app\libraries\Output;

abstract class AbstractView {
    /** @var Core */
    protected $core;
    /** @var Output */
    protected $output;

    public function __construct(Core $core, Output $output) {
        $this->core = $core;
        $this->output = $output;
    }
}
