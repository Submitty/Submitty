<?php

namespace app\controllers;

use app\libraries\Core;
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
        $assignment = (isset($_REQUEST['assignment'])) ? $_REQUEST['assignment'] : null;
        $class_info = new ClassJson($this->core, $assignment);

        $controller = null;
        switch ($_REQUEST['page']) {
            case 'submission':
            default:
                $controller = new student\SubmissionController($this->core, $class_info);
                break;
        }

        $controller->run();
    }
}