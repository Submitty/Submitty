<?php

namespace app\controllers\grading;

use app\controllers\AbstractController;
use app\models\User;
use app\models\HWReport;


class ElectronicGraderController extends AbstractController {
    public function run() {
        switch ($_REQUEST['action']) {
            case 'details':
                $this->showDetails();
                break;
            case 'grade':
                $this->showGrading();
                break;
            case 'submit':
                $this->submitGrade();
                break;
            default:
                $this->showStatus();
                break;
        }
    }

    /**
     * Shows statistics for the grading status of a given electronic submission. This is shown to all full access
     * graders. Limited access graders will only see statistics for the sections they are assigned to.
     */
    public function showStatus() {
        $gradeable_id = $_REQUEST['gradeable_id'];
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id);
        $this->core->getOutput()->addBreadcrumb("Status {$gradeable->getName()}");

        /*
         * we need number of students per section
         */
        $total = array();
        $graded = array();
        $graders = array();
        $sections = array();
        if ($gradeable->isGradeByRegistration()) {
            if(!$this->core->getUser()->accessFullGrading()){
                $sections = $this->core->getUser()->getGradingRegistrationSections();
            }
            if (count($sections) > 0 || $this->core->getUser()->accessFullGrading()) {
                $total = $this->core->getQueries()->getTotalUserCountByRegistrationSections($sections);
                $graded = $this->core->getQueries()->getGradedUserCountByRegistrationSections($gradeable->getId(), $sections);
                $graders = $this->core->getQueries()->getGradersForRegistrationSections($sections);
            }
        }
        else {
            if(!$this->core->getUser()->accessFullGrading()){
                $sections = $this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable_id, $this->core->getUser()->getId());
            }
            if (count($sections) > 0 || $this->core->getUser()->accessFullGrading()) {
                $total = $this->core->getQueries()->getTotalUserCountByRotatingSections($sections);
                $graded = $this->core->getQueries()->getGradedUserCountByRotatingSections($gradeable_id, $sections);
                $graders = $this->core->getQueries()->getGradersForRotatingSections($gradeable->getId(), $sections);
            }
        }

        $sections = array();
        if (count($total) > 0) {
            foreach ($total as $key => $value) {
                $sections[$key] = array(
                    'total_students' => $value,
                    'graded_students' => 0,
                    'graders' => array()
                );
                if (isset($graded[$key])) {
                    $sections[$key]['graded_students'] = intval($graded[$key]);
                }
                if (isset($graders[$key])) {
                    $sections[$key]['graders'] = $graders[$key];
                }
            }
        }

        $this->core->getOutput()->renderOutput(array('grading', 'ElectronicGrader'), 'statusPage', $gradeable, $sections);
    }

    /**
     * This loads a gradeable and
     */
    public function showDetails() {
        $gradeable_id = $_REQUEST['gradeable_id'];
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id);
        $this->core->getOutput()->addBreadcrumb("Details {$gradeable->getName()}");
        if ($gradeable === null) {
            $this->core->getOutput()->renderOutput('Error', 'noGradeable', $gradeable_id);
            return;
        }
        $students = array();
        if ($gradeable->isGradeByRegistration()) {
            $section_key = "registration_section";
            $sections = $this->core->getUser()->getGradingRegistrationSections();
            if (!isset($_GET['view']) || $_GET['view'] !== "all") {
                $students = $this->core->getQueries()->getUsersByRegistrationSections($sections);
            }
            $graders = $this->core->getQueries()->getGradersForRegistrationSections($sections);
        }
        else {
            $section_key = "rotating_section";
            $sections = $this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable_id,
                $this->core->getUser()->getId());
            if (!isset($_GET['view']) || $_GET['view'] !== "all") {
                $students = $this->core->getQueries()->getUsersByRotatingSections($sections);
            }
            $graders = $this->core->getQueries()->getGradersForRotatingSections($gradeable->getId(), $sections);
        }
        if ((isset($_GET['view']) && $_GET['view'] === "all") || ($this->core->getUser()->accessAdmin() && count($sections) === 0)) {
            $students = $this->core->getQueries()->getAllUsers($section_key);
        }

        $student_ids = array_map(function(User $student) { return $student->getId(); }, $students);

        $rows = $this->core->getQueries()->getGradeables($gradeable_id, $student_ids, $section_key);
        $this->core->getOutput()->renderOutput(array('grading', 'ElectronicGrader'), 'detailsPage', $gradeable, $rows, $graders);
    }

    //TODO (issue #1128) refactor this function to set data in the gradeable model then call $gradeable->saveData()
    public function submitGrade() {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] != $this->core->getCsrfToken()) {
            $_SESSION['messages']['error'][] = "Invalid CSRF Token";
            $this->core->redirect($this->core->buildUrl(array()));
        }

        $gradeable_id = $_POST['g_id'];
        $who_id = $_POST['u_id'];
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id, $who_id);

        $now = new \DateTime('now', $this->core->getConfig()->getTimezone());
        $homeworkDate = $gradeable->getGradeStartDate();
        if ($now < $homeworkDate) {
            $_SESSION['messages']['error'][] = "Grading is not open yet for {$gradeable->getName()}";
            $this->core->redirect($this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'gradeable_id' => $gradeable_id)));
        }
        
        $regrade = $gradeable->beenTAgraded();
        
        $submit_data = array();
        $submit_data['g_id'] = $gradeable_id;
        $submit_data['u_id'] = $who_id;
        $submit_data['gd_id'] = $regrade ? $gradeable->getGdId() : null;
        $submit_data['grader_id'] = isset($_POST['overwrite']) ? $this->core->getUser()->getId() : $gradeable->getGrader()->getId();
        $submit_data['comment'] = $_POST['comment-general'];
        $submit_data['graded_version'] = $_POST['graded_version'];
        $submit_data['time'] = $now->format("Y-m-d H:i:s");
        
        $submit_data['components'] = array();
        $comps = $gradeable->getComponents();
        //update each gradeable component data
        foreach($comps as $comp){
            $gc_id = $comp->getId();
            $submit_data['components'][$gc_id] = array();
            $submit_data['components'][$gc_id]['grade'] = floatval($_POST["grade-{$comp->getOrder()}"]);
            $submit_data['components'][$gc_id]['comment'] = isset($_POST["comment-{$comp->getOrder()}"]) ? $_POST["comment-{$comp->getOrder()}"] : '';
        }

        $this->core->getQueries()->submitTAGrade($submit_data);

        $hwReport = new HWReport($this->core);
        $hwReport->generateSingleReport($who_id, $gradeable_id);
        $individual = intval($_POST['individual']);

        $_SESSION['messages']['success'][] = "Successfully uploaded grade for {$who_id}";

        if ($individual == 1) {
            $this->core->redirect($this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action'=>'details','gradeable_id'=>$gradeable_id)));
        }
        else {
            $this->core->redirect($this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action'=>'grade', 'gradeable_id'=>$gradeable_id, 'individual'=>'0')));
        }
        
    }

    public function showGrading() {
        $gradeable_id = $_REQUEST['gradeable_id'];
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id);

        $graded = 0;
        $total = 0;
        if ($gradeable->isGradeByRegistration()) {
            $section_key = "registration_section";
            $sections = $this->core->getUser()->getGradingRegistrationSections();
            if ($this->core->getUser()->accessAdmin() && $sections == null) {
                $sections = $this->core->getQueries()->getRegistrationSections();
                for ($i = 0; $i < count($sections); $i++) {
                    $sections[$i] = $sections[$i]['sections_registration_id'];
                }
            }
            $users_to_grade = $this->core->getQueries()->getUsersByRegistrationSections($sections);
            $total = array_sum($this->core->getQueries()->getTotalUserCountByRegistrationSections($sections));
            $graded = array_sum($this->core->getQueries()->getGradedUserCountByRegistrationSections($gradeable_id, $sections));
        }
        else {
            $section_key = "rotating_section";
            $sections = $this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable_id, $this->core->getUser()->getId());
            if ($this->core->getUser()->accessAdmin() && $sections == null) {
                $sections = $this->core->getQueries()->getRotatingSections();
                for ($i = 0; $i < count($sections); $i++) {
                    $sections[$i] = $sections[$i]['sections_rotating_id'];
                }
            }
            $users_to_grade = $this->core->getQueries()->getUsersByRotatingSections($sections);
            $total = array_sum($this->core->getQueries()->getTotalUserCountByRotatingSections($sections));
            $graded = array_sum($this->core->getQueries()->getGradedUserCountByRotatingSections($gradeable_id, $sections));
        }

        if($total == 0) {
            $progress = 100;
        }
        else {
            $progress = round(($graded / $total) * 100, 1);
        }

        $user_ids_to_grade = array_map(function(User $user) { return $user->getId(); }, $users_to_grade);
        $gradeables_to_grade = $this->core->getQueries()->getGradeables($gradeable_id, $user_ids_to_grade, $section_key);

        $who_id = isset($_REQUEST['who_id']) ? $_REQUEST['who_id'] : "";
        $prev_id = "";
        $next_id = "";
        $break_next = false;
        foreach ($gradeables_to_grade as $g) {
            $id = $g->getUser()->getId();
            if ($break_next) {
                $next_id = $id;
                break;
            }
            if (($who_id === "" && !$g->beenTAgraded()) || $who_id === $id) {
                $who_id = $id;
                $break_next = true;
            }
            else {
                $prev_id = $id;
            }
        }
        if ($who_id === "") {
            $_SESSION['messages']['success'][] = "Finished grading for {$gradeable->getName()}";
            $this->core->redirect($this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'gradeable_id' => $gradeable_id)));
        }

        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id, $who_id);
        $gradeable->loadResultDetails();
        $individual = $_REQUEST['individual'];

        $this->core->getOutput()->addCSS($this->core->getConfig()->getBaseUrl()."/css/ta-grading.css");
        $this->core->getOutput()->renderOutput(array('grading', 'ElectronicGrader'), 'hwGradingPage', $gradeable, $progress, $prev_id, $next_id, $individual);
    }
}
