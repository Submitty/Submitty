<?php

namespace app\controllers\admin;

use app\controllers\IController;
use app\libraries\Core;

class UsersController implements IController {
    private $core;

    public function __construct(Core $core) {
        $this->core = $core;
    }

    public function run() {
        
    }
}