<?php

namespace app\controllers;

use app\libraries\Core;
use app\models\GradeableList;
use app\models\ClassJson;

class StudentController extends AbstractController {
    public function run() {
        $controller = null;
        switch ($_REQUEST['page']) {
            case 'rainbow':
                $controller = new student\RainbowGradesController($this->core);
                break;
            case 'team':
                $controller = new student\TeamController($this->core);
                break;
            case 'submission':
            default:
                $controller = new student\SubmissionController($this->core);
                break;
        }

        $controller->run();
    }
}