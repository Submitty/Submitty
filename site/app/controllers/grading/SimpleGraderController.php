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
            case 'save_grade':
                $this->saveGrade();
                break;
            default:
                break;
        }
    }

    public function gradeLabs() {
        if (!isset($_REQUEST['g_id'])) {
            throw new \Exception("ack");
        }
        $g_id = $_REQUEST['g_id'];
        $gradeable = $this->core->getQueries()->getGradeables($g_id);
        if ($gradeable === null) {
            throw new \Exception("ugh");
        }
        $this->core->getOutput()->addBreadcrumb("Grading {$gradeable->getName()}");
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

    public function saveGrade() {
        if (!isset($_REQUEST['g_id']) || !isset($_REQUEST['user_id'])) {
            $response = array('status' => 'fail', 'message' => 'Did not pass in g_id or user_id');
            $this->core->getOutput()->renderJson($response);
            return $response;
        }
        $g_id = $_REQUEST['g_id'];
        $user_id = $_REQUEST['user_id'];
        $gradeable = $this->core->getQueries()->getGradeables($g_id, $user_id);
        $user = $this->core->getQueries()->getUserById($user_id);
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] != $this->core->getCsrfToken()) {
            $response = array('status' => 'fail', 'message' => 'Invalid CSRF token');
            $this->core->getOutput()->renderJson($response);
            return $response;
        }
        else if ($gradeable === null) {
            $response = array('status' => 'fail', 'message' => 'Invalid gradeable ID');
            $this->core->getOutput()->renderJson($response);
            return $response;
        }
        else if ($user === null) {
            $response = array('status' => 'fail', 'message' => 'Invalid user ID');
            $this->core->getOutput()->renderJson($response);
            return $response;
        }
        else if (!isset($_POST['scores']) || empty($_POST['scores'])) {
            $response = array('status' => 'fail', 'message' => "Didn't submit any scores");
            $this->core->getOutput()->renderJson($response);
            return $response;
        }

        foreach ($gradeable->getComponents() as $component) {
            if (isset($_POST['scores'][$component->getId()])) {
                $component->setScore($_POST['scores'][$component->getId()]);
            }
        }

        $gradeable->setUser($user);
        $gradeable->setGrader($this->core->getUser());
        $gradeable->setStatus(1);
        $gradeable->setActiveVersion(1);

        $this->core->getQueries()->updateGradeableData($gradeable);

        $response = array('status' => 'success', 'data' => null);
        $this->core->getOutput()->renderJson($response);
        return $response;
    }
}