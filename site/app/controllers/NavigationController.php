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
            $sections_to_lists["FUTURE"] = $future_gradeables_list;
            $sections_to_lists["BETA"] = $beta_gradeables_list;
        }

        $sections_to_lists["OPEN"] = $open_gradeables_list;
        $sections_to_lists["CLOSED"] = $closed_gradeables_list;
        $sections_to_lists["ITEMS BEING GRADED"] = $grading_gradeables_list;
        $sections_to_lists["GRADED"] = $graded_gradeables_list;

        //Remove incomplete gradeables for non-instructors
        if (!$user->accessAdmin()) {
            foreach ($sections_to_lists as $key => $value) {
                $sections_to_lists[$key] = array_filter($value, array($this, "filterNoConfig"));
            }
        }

        //Clear empty sections
        foreach ($sections_to_lists as $key => $value) {
            $electronic_gradeable_count = 0;
            foreach ($sections_to_lists[$key] as $gradeable => $g_data) {
                /* @var Gradeable $g_data */
                if ($g_data->getType() == GradeableType::ELECTRONIC_FILE && $g_data->getStudentView()) {
                    $electronic_gradeable_count++;
                    break;
                }
            }
            // if there are no gradeables, or if its a student and no electronic upload gradeables, don't show this category
            if (count($sections_to_lists[$key]) == 0 || ($electronic_gradeable_count == 0 && !$user->accessGrading())) {
                unset($sections_to_lists[$key]);
            }
        }

        $this->core->getOutput()->renderOutput('Navigation', 'showGradeables', $sections_to_lists);
        $this->core->getOutput()->renderOutput('Navigation', 'deleteGradeableForm'); 
    }
    
    /**
     * @param Gradeable $gradeable
     * @return bool
     */
    private function filterNoConfig($gradeable) {
        if ($gradeable->getType() == GradeableType::ELECTRONIC_FILE) {
            return $gradeable->hasConfig();
        }
        return true;
    }
}
