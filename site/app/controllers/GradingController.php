<?php

namespace app\controllers;

use app\controllers\grading\ElectronicGraderController;
use app\controllers\grading\SimpleGraderController;
use app\controllers\grading\TeamListController;


class GradingController extends AbstractController {
    
    public function run() {
        $controller = null;
        switch ($_REQUEST['page']) {
            case 'simple':
                $controller = new SimpleGraderController($this->core);
                break;
            case 'electronic':
                $controller = new ElectronicGraderController($this->core);
                break;
            default:
                $this->core->getOutput()->showError("Invalid page request for controller ".get_class($this));
                break;
        }
        $controller->run();
    }
}
