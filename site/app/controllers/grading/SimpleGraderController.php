<?php

namespace app\controllers\grading;


use app\controllers\AbstractController;
use app\models\User;

class SimpleGraderController extends AbstractController  {
    public function run() {
        switch ($_REQUEST['action']) {
            case 'lab':
                $this->gradeLabs();
                break;
            case 'save':
                break;
            default:
                break;
        }
    }

    public function gradeLabs() {
        if (!isset($_REQUEST['gradeable_id'])) {
            throw new \Exception("ack");
        }
        $g_id = $_REQUEST['gradeable_id'];
        $gradeable = $this->core->getQueries()->getGradeableById($g_id);
        if ($gradeable === null) {
            throw new \Exception("ugh");
        }
        $this->core->getOutput()->addBreadcrumb("Overview {$gradeable->getName()}");
        if ($gradeable->isGradeByRegistration()) {
            $section_key = 'registration_section';
        }
        else {
            $section_key = 'rotating_section';
        }

        $students = $this->core->getQueries()->getAllUsers($section_key);
        $student_ids = array_map(function(User $user) { return $user->getId(); }, $students);
        $rows = $this->core->getQueries()->getGradeableForUsers($gradeable->getId(), $student_ids, $section_key);
        $this->core->getOutput()->renderOutput(array('grading', 'SimpleGrader'), 'checkpointForm', $gradeable, $rows);
    }
}