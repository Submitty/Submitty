<?php

namespace app\controllers\grading;

use app\controllers\AbstractController;

class ElectronicGraderController extends AbstractController {
    public function run() {
        switch ($_REQUEST['action']) {
            case 'summary':
                $this->showSummary();
                break;
            default:
                $this->showOverview();
                break;
        }
    }

    /**
     * Shows an overview of the grading status of a given electronic submission. This is shown to all graders showing
     * only their sections that they've been assigned to, unless their an administrator in which case it'll either
     * show only the sections they're assigned to (if any) or if not assigned to any, all sections.
     *
     * Additionally, there's an optional flag that can be used to always show all sections
     */
    public function showOverview() {
        $gradeable_id = $_REQUEST['gradeable_id'];
        $gradeable = $this->core->getQueries()->getGradeableById($gradeable_id);
        $this->core->getOutput()->addBreadcrumb("Overview {$gradeable->getName()}");

        /*
         * we need number of students per section
         */
        $total = array();
        $graded = array();
        $graders = array();
        if ($gradeable->isGradeByRegistration()) {
            $section_key = "registration_section";
            $sections = $this->core->getUser()->getGradingRegistrationSections();
            if (count($sections) > 0 || (count($sections) === 0 && $this->core->getUser()->accessAdmin())) {
                $total = $this->core->getQueries()->getTotalUserCountByRegistrationSections($sections);
                $graded = $this->core->getQueries()->getGradedUserCountByRegistrationSections($gradeable->getId(), $sections);
                $graders = $this->core->getQueries()->getGradersForRegistrationSections($sections);
            }
        }
        else {
            $section_key = "rotating_section";
            $sections = $this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable_id,
                $this->core->getUser()->getId());
            if (count($sections) > 0 || (count($sections) === 0 && $this->core->getUser()->accessAdmin())) {
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

        $this->core->getOutput()->renderOutput(array('grading', 'ElectronicGrader'), 'overviewPage', $gradeable, $sections);
    }

    /**
     * This loads a gradeable and
     */
    public function showSummary() {
        $gradeable_id = $_REQUEST['gradeable_id'];
        $gradeable = $this->core->getQueries()->getGradeableById($gradeable_id);
        $this->core->getOutput()->addBreadcrumb("Summary {$gradeable->getName()}");
        if ($gradeable === null) {
            $this->core->getOutput()->renderOutput('Error', 'noGradeable', $gradeable_id);
            return;
        }
        if ($gradeable->isGradeByRegistration()) {
            $section_key = "registration_section";
            $sections = $this->core->getUser()->getGradingRegistrationSections();
            $students = $this->core->getQueries()->getUsersByRegistrationSections($sections);
        }
        else {
            $section_key = "rotating_section";
            $sections = $this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable_id,
                $this->core->getUser()->getId());
            $students = $this->core->getQueries()->getUsersByRotatingSections($sections);
        }
        if (count($students) === 0 && $this->core->getUser()->accessAdmin()) {
            $students = array();
            foreach ($this->core->getQueries()->getAllUsers($section_key) as $users) {
                $students[] = $users->getId();
            }
        }

        $rows = $this->core->getQueries()->getGradeableForUsers($gradeable_id, $students, $section_key);
        $this->core->getOutput()->renderOutput(array('grading', 'ElectronicGrader'), 'summaryPage', $gradeable, $rows);
    }
}