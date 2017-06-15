<?php

namespace app\controllers\grading;

use app\controllers\AbstractController;
use app\models\User;

class ElectronicGraderController extends AbstractController {
    public function run() {
        switch ($_REQUEST['action']) {
            case 'summary':
                $this->showSummary();
                break;
            case 'grade':
                $this->showGrading();
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
    public function showSummary() {
        $gradeable_id = $_REQUEST['gradeable_id'];
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id);
        $this->core->getOutput()->addBreadcrumb("Summary {$gradeable->getName()}");
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
        $this->core->getOutput()->renderOutput(array('grading', 'ElectronicGrader'), 'summaryPage', $gradeable, $rows, $graders);
    }

    public function showGrading() {
        $gradeable_id = $_REQUEST['gradeable_id'];
        $who_id = $_REQUEST['who_id'];
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id, $who_id);
        $this->core->getOutput()->renderOutput(array('grading', 'ElectronicGrader'), 'hwGradingPage', $gradeable);
    }
}