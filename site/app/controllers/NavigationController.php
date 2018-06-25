<?php

namespace app\controllers;

use app\controllers\AbstractController;
use app\libraries\Core;
use app\libraries\DateUtils;
use app\libraries\ErrorMessages;
use app\libraries\FileUtils;
use app\libraries\GradeableType;
use app\libraries\Logger;
use app\libraries\Utils;
use app\models\Gradeable;
use app\models\GradeableList;
use app\models\GradeableSection;

class NavigationController extends AbstractController {
    public function __construct(Core $core) {
        parent::__construct($core);
    }

    public function run() {
        switch ($_REQUEST['page']) {
            case 'no_access':
                $this->noAccess();
                break;
            default:
                $this->navigationPage();
                break;
        }
    }

    private function noAccess() {
        $this->core->getOutput()->renderOutput('Navigation', 'noAccessCourse');
    }

    private function navigationPage() {
        $gradeables_list = new GradeableList($this->core);
        $this->core->getOutput()->addCss("https://fonts.googleapis.com/css?family=Open+Sans+Condensed:300,300italic,700");
        $this->core->getOutput()->addCss("https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic,700italic");
        $this->core->getOutput()->addCss("https://fonts.googleapis.com/css?family=PT+Sans:700,700italic");
        $this->core->getOutput()->addCss("https://fonts.googleapis.com/css?family=Inconsolata");
        
        $future_gradeables_list = $gradeables_list->getFutureGradeables();
        $beta_gradeables_list = $gradeables_list->getBetaGradeables();
        $open_gradeables_list = $gradeables_list->getOpenGradeables();
        $closed_gradeables_list = $gradeables_list->getClosedGradeables();
        $grading_gradeables_list = $gradeables_list->getGradingGradeables();
        $graded_gradeables_list = $gradeables_list->getGradedGradeables();
        
        $sections_to_lists = [];

        $user = $this->core->getUser();

        if ($user->accessGrading()) {
            $sections_to_lists[GradeableList::FUTURE] = $future_gradeables_list;
            $sections_to_lists[GradeableList::BETA] = $beta_gradeables_list;
        }

        $sections_to_lists[GradeableList::OPEN] = $open_gradeables_list;
        $sections_to_lists[GradeableList::CLOSED] = $closed_gradeables_list;
        $sections_to_lists[GradeableList::GRADING] = $grading_gradeables_list;
        $sections_to_lists[GradeableList::GRADED] = $graded_gradeables_list;

        //Remove gradeables we are not allowed to view
        foreach ($sections_to_lists as $key => $value) {
            $sections_to_lists[$key] = array_filter($value, array($this, "filterCanView"));
        }

        //Clear empty sections
        foreach ($sections_to_lists as $key => $value) {
            // if there are no gradeables, don't show this category
            if (count($sections_to_lists[$key]) == 0) {
                unset($sections_to_lists[$key]);
            }
        }

        $this->core->getOutput()->renderOutput('Navigation', 'showGradeables', $sections_to_lists);
        $this->core->getOutput()->renderOutput('Navigation', 'deleteGradeableForm'); 
    }
    
    /**
     * Test if the current user is allowed to view this gradeable
     * @param Gradeable $gradeable
     * @return bool True if they are
     */
    private function filterCanView($gradeable) {
        $user = $this->core->getUser();

        //Remove incomplete gradeables for non-instructors
        if (!$user->accessAdmin() && $gradeable->getType() == GradeableType::ELECTRONIC_FILE && !$gradeable->hasConfig()) {
            return false;
        }

        // student users should only see electronic gradeables -- NOTE: for now, we might change this design later
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE && !$user->accessGrading()) {
            return false;
        }

        // if student view false, never show
        if (!$gradeable->getStudentView() && !$user->accessGrading()) {
            return false;
        }

        //If we're not instructor and this is not open to TAs
        $date = new \DateTime("now", $this->core->getConfig()->getTimezone());
        if ($gradeable->getTAViewDate()->format('Y-m-d H:i:s') > $date->format('Y-m-d H:i:s') && !$user->accessAdmin()) {
            return false;
        }

        return true;
    }
}
