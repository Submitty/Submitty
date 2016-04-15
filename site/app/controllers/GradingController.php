<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\Output;
use app\models\User;

class GradingController implements IController{
    /**
     * @var Core
     */
    private $core;

    public function __construct(Core $core) {
        $this->core = $core;
    }

    public function run() {
        if (!$this->core->getUser()->accessGrading()) {
            Output::showError("This account is not authorized to view grading section");
        }

        $controller = null;
        $_REQUEST['page'] = (isset($_REQUEST['page'])) ? strtolower($_REQUEST['page']) : "";
        switch ($_REQUEST['page']) {
            default:
                Output::showError("Invalid page request for controller");
                break;
        }
        $controller->run();
    }
}