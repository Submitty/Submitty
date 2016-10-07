<?php

namespace app\controllers;

use app\libraries\Core;

abstract class AbstractController {

    /** @var Core  */
    protected $core;

    public function __construct(Core $core) {
        $this->core = $core;
    }

    abstract public function run();
}