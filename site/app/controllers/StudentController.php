<?php

namespace app\controllers;

use app\libraries\Core;
use app\models\GradeableList;
use app\models\ClassJson;

class StudentController implements IController {
    /**
     * @var Core
     */
    private $core;

    public function __construct(Core $core) {
        $this->core = $core;
    }

    public function run() {
        $controller = null;
        switch ($_REQUEST['page']) {
            case 'rainbow':
                $controller = new student\RainbowGradesController($this->core);
                break;
            case 'submission':
            default:
                $controller = new student\SubmissionController($this->core);
                break;
        }

        $controller->run();
    }
}