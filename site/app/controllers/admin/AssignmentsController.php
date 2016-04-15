<?php

namespace app\controllers\admin;

use app\controllers\IController;
use app\libraries\Core;
use app\libraries\Output;

class AssignmentsController implements IController {
    /**
     * @var Core
     */
    private $core;

    public function __construct(Core $core) {
        $this->core = $core;
    }

    public function run() {
        $_REQUEST['action'] = (isset($_REQUEST['action'])) ? strtolower($_REQUEST['action']) : "";
        switch($_REQUEST['action']) {
            case 'list':
                $this->listAssignments();
                break;
            case 'new':
                $this->assignmentForm(false);
                break;
            case 'edit':
                $this->assignmentForm(true);
                break;
            default:
                Output::showError("Invalid action for controller");
                break;
        }
    }

    public function listAssignments() {
        $assignments = $this->core->getQueries()->getAllAssignments();
        Output::render(array('admin', 'Assignments'), 'assignmentsTable', $assignments);
    }

    public function assignmentForm($edit_assignment=false) {
        if ($edit_assignment) {
            if (!isset($_REQUEST['assignment_id'])) {
                $_SESSION['messages']['errors'][] = 'Invalid Assignment ID';
                $this->core->redirect($this->core->buildUrl(array('component' => 'admin',
                                                                  'page' => 'assignments',
                                                                  'action' => 'list')));
            }

            $assignment = $this->core->getQueries()->getAssignmentById($_REQUEST['assignment_id']);
            if (empty($assignment)) {
                $_SESSION['messages']['errors'][] = 'Invalid Assignment ID';
                $this->core->redirect($this->core->buildUrl(array('component' => 'admin',
                                                                  'page' => 'assignments',
                                                                  'action' => 'list')));
            }
        }
    }
}