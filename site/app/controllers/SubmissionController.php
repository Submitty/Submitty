<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\Output;

class SubmissionController implements IController {
    /**
     * @var Core
     */
    private $core;

    public function __construct(Core $core) {
        $this->core = $core;
    }

    public function run() {
        $class_json = json_decode(file_get_contents($this->core->getConfig()->getCoursePath()."/config/class.json"), true);
        $assignments = array();
        foreach ($class_json['assignments'] as $assignment) {
            if ((isset($assignment['released']) && $assignment['released'] == true) || ($this->core->getUser()->accessAdmin())) {
                $assignments[$assignment['assignment_id']] = $assignment;
            }
        }

        $controller = null;
        switch ($_REQUEST['page']) {
            case 'homework':
            default:
                $controller = new submission\HomeworkController($this->core, $assignments);
                break;
        }

        $controller->run();
    }
}