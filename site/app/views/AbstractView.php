<?php

namespace app\views;

use app\libraries\Core;

abstract class AbstractView {
    /** @var Core */
    protected $core;

    public function __construct($core) {
        $this->core = $core;
    }
}