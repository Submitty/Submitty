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
            $this->core->getOutput()->renderOutput('Error', 'noGradeable');
        }
        $g_id = $_REQUEST['g_id'];
        $gradeable = $this->core->getQueries()->getGradeable($g_id);
        if ($gradeable === null) {
            $this->core->getOutput()->renderOutput('Error', 'noGradeable', $g_id);
        }
        $this->core->getOutput()->addBreadcrumb("Grading {$gradeable->getName()}");

        $students = array();
        if ($gradeable->isGradeByRegistration()) {
            if(!isset($_GET['sort']) || $_GET['sort'] === "first"){
                $section_key = "registration_section_by_first";
            }
            else if($_GET['sort'] === "last"){
                $section_key = "registration_section_by_last";
            }
            else{
                $section_key = "registration_section";
            }
            $sections = $this->core->getUser()->getGradingRegistrationSections();
            if (!isset($_GET['view']) || $_GET['view'] !== "all") {
                $students = $this->core->getQueries()->getUsersByRegistrationSections($sections);
            }
            $graders = $this->core->getQueries()->getGradersForRegistrationSections($sections);
        }
        else {
            $section_key = "rotating_section";
            $sections = $this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable->getId(),
                $this->core->getUser()->getId());
            if (!isset($_GET['view']) || $_GET['view'] !== "all") {
                $students = $this->core->getQueries()->getUsersByRotatingSections($sections);
            }
            $graders = $this->core->getQueries()->getGradersForRotatingSections($gradeable->getId(), $sections);
        }
        if(count($sections) === 0 && (!isset($_GET['view']) || $_GET['view'] !== "all") && !$this->core->getUser()->accessAdmin()){
            $this->core->getOutput()->renderOutput(array('grading', 'SimpleGrader'), 'checkpointForm', $gradeable, $sections, $graders);
            return;
        }
        if ((isset($_GET['view']) && $_GET['view'] === "all") || (count($sections) === 0 && $this->core->getUser()->accessAdmin())) {
            $students = $this->core->getQueries()->getAllUsers($section_key);
        }
        $student_ids = array_map(function(User $user) { return $user->getId(); }, $students);
        $rows = $this->core->getQueries()->getGradeables($gradeable->getId(), $student_ids, $section_key);
        $this->core->getOutput()->renderOutput(array('grading', 'SimpleGrader'), 'checkpointForm', $gradeable, $rows, $graders);
    }

    public function saveGrade() {
        if (!isset($_REQUEST['g_id']) || !isset($_REQUEST['user_id'])) {
            $response = array('status' => 'fail', 'message' => 'Did not pass in g_id or user_id');
            $this->core->getOutput()->renderJson($response);
            return $response;
        }
        $g_id = $_REQUEST['g_id'];
        $user_id = $_REQUEST['user_id'];
        $gradeable = $this->core->getQueries()->getGradeable($g_id, $user_id);
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