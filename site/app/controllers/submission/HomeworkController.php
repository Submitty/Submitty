<?php

namespace app\controllers\submission;

use app\controllers\IController;
use app\libraries\Core;
use app\libraries\Output;

class HomeworkController implements IController {
    private $assignments = array();

    /**
     * @var Core
     */
    private $core;

    public function __construct(Core $core, $assignments) {
        $this->core = $core;
        $this->assignments = $assignments;
    }

    public function run() {
        $_REQUEST['action'] = (isset($_REQUEST['action'])) ? strtolower($_REQUEST['action']) : 'display';
        switch($_REQUEST['action']) {
            case 'upload':
                break;
            case 'update':
                break;
            case 'display':
            default:
                $this->showHomeworkPage();
                break;
        }
    }

    private function showHomeworkPage() {
        if (count($this->assignments) > 0) {
            if(isset($_REQUEST['assignment']) && isset($this->assignments[$_REQUEST['assignment']])) {
                $assignment = $_REQUEST['assignment'];
            } else {
                end($this->assignments);
                $assignment = key($this->assignments);
                reset($this->assignments);
            }

            Output::render(array('submission', 'Homework'), 'assignmentSelect', $this->assignments);

            Output::render(array('submission', 'Homework'), 'showAssignment', $this->assignments[$assignment]);
        }
        else {
            Output::render(array('submission', 'Homework'), 'noAssignments');
        }
    }
}