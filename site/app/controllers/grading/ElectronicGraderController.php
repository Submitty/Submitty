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
                $this->core->getOutput()->showError("Invalid action request for controller ".get_class($this));
                break;
        }
    }

    /**
     * This loads a gradeable and
     */
    public function showSummary() {
        $gradeable_id = $_REQUEST['gradeable_id'];
        $this->core->getOutput()->addBreadcrumb("Summary {$gradeable_id}");
        $gradeable = $this->core->getQueries()->getGradeableById($gradeable_id);
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