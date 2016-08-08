<?php

namespace app\controllers\admin;

use app\controllers\IController;
use app\libraries\Core;
use app\libraries\Output;

class GradeablesController implements IController {
    /**
     * @var Core
     */
    private $core;

    public function __construct(Core $core) {
        $this->core = $core;
    }

    public function run() {
        switch($_REQUEST['action']) {
            case 'list':
                $this->listGradeables();
                break;
            case 'add':
                $this->gradeablesForm(false);
                break;
            case 'update':
                $this->gradeablesForm(true);
                break;
            default:
                $this->core->getOutput()->showError("Invalid action for controller ".get_class($this));
                break;
        }
    }

    public function listGradeables() {
        //$assignments = $this->core->getQueries()->getAllGradeables();
        $assignments = array();
        $this->core->getOutput()->renderOutput(array('admin', 'Gradeables'), 'gradeablesTable', $assignments);
    }

    public function gradeablesForm($edit_assignment=false) {
        if ($edit_assignment) {
            if (!isset($_REQUEST['gradeables_id'])) {
                $_SESSION['messages']['error'] = 'Invalid Gradeables ID';
                $this->core->redirect($this->core->buildUrl(array('component' => 'admin',
                                                                  'page' => 'gradeables',
                                                                  'action' => 'list')));
            }

            $assignment = $this->core->getQueries()->getAssignmentById($_REQUEST['assignment_id']);
            if (empty($assignment)) {
                $_SESSION['messages']['error'] = 'Invalid Gradeables ID';
                $this->core->redirect($this->core->buildUrl(array('component' => 'admin',
                                                                  'page' => 'gradeables',
                                                                  'action' => 'list')));
            }
        }

        $this->core->getOutput()->renderOutput(array('admin', 'Gradeables'), 'gradeableForm');
    }
}