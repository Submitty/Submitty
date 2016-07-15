<?php

namespace app\controllers\submission;

use app\controllers\IController;
use app\libraries\Core;
use app\libraries\Utils;
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

            $details = Utils::loadJsonFile($this->core->getConfig()->getCoursePath()."/config/".$assignment."_assignment_config.json");
            $this->assignments[$assignment] = array_merge($this->assignments[$assignment], $details);

            $select = Output::render_template(array('submission', 'Homework'), 'assignmentSelect', $this->assignments, $this->assignments[$assignment]['assignment_id']);

            Output::render_output(array('submission', 'Homework'), 'showAssignment', $this->assignments[$assignment], $select);
        }
        else {
            Output::render_output(array('submission', 'Homework'), 'noAssignments');
        }
    }
}