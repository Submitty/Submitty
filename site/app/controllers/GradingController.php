<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\Output;
use app\models\User;

class GradingController extends AbstractController{
    public function run() {
        if (!$this->core->getUser()->accessGrading()) {
            $this->core->getOutput()->showError("This account is not authorized to view grading section");
        }

        $controller = null;
        switch ($_REQUEST['page']) {
            default:
                $this->core->getOutput()->showError("Invalid page request for controller ".get_class($this));
                break;
        }
        $controller->run();
    }
}