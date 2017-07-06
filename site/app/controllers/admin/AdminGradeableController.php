<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use \lib\Database;
use \lib\Functions;
use \app\libraries\GradeableType;
use app\models\Gradeable;

class AdminGradeableController extends AbstractController {
	public function run() {
        switch ($_REQUEST['action']) {
            case 'view_gradeable_page':
            	$this->viewPage();
                break;
            case 'upload_new_gradeable':
                $this->modifyGradeable(0);
                break;
            case 'edit_gradeable_page':
                $this->editPage();
                break;
            case 'upload_edit_gradeable':
                $this->modifyGradeable(1);
                break;
            case 'upload_new_template':
                $this->uploadNewTemplate();
                break;
            case 'quick_link':
                $this->quickLink();
                break;
            default:
                $this->viewPage();
                break;
        }
    }

    //Pulls the data from an existing gradeable and just prints it on the page
    private function uploadNewTemplate() {
        if($_REQUEST['template_id'] === "--None--") {
            $this->viewPage();
            return;
        }
        $rotatingGradeables = $this->core->getQueries()->getRotatingSectionsGradeableIDS();
        $gradeableSectionHistory = $this->core->getQueries()->getGradeablesPastAndSection();
        $num_sections = $this->core->getQueries()->getNumberRotatingSessions();
        $graders_all_section = $this->core->getQueries()->getGradersForAllRotatingSections($_REQUEST['template_id']);
        $graders_from_usertype1 = $this->core->getQueries()->getGradersFromUserType(1);
        $graders_from_usertype2 = $this->core->getQueries()->getGradersFromUserType(2);
        $graders_from_usertype3 = $this->core->getQueries()->getGradersFromUserType(3);
        $graders_from_usertypes = array($graders_from_usertype1, $graders_from_usertype2, $graders_from_usertype3);
        $template_list = $this->core->getQueries()->getAllGradeablesIdsAndTitles();
        $ini_data = array($rotatingGradeables, $gradeableSectionHistory, $num_sections, $graders_all_section, $graders_from_usertypes,
            $template_list);
        $data = $this->core->getQueries()->getGradeableData($_REQUEST['template_id']);
        $this->core->getOutput()->renderOutput(array('admin', 'AdminGradeable'), 'show_add_gradeable', "add_template", $ini_data, $data);
    }

    //view the page with no data from previous gradeables
    private function viewPage() {
        $rotatingGradeables = $this->core->getQueries()->getRotatingSectionsGradeableIDS();
        $gradeableSectionHistory = $this->core->getQueries()->getGradeablesPastAndSection();
        $num_sections = $this->core->getQueries()->getNumberRotatingSessions();
        $graders_all_section = $this->core->getQueries()->getGradersForAllRotatingSections("");
        $graders_from_usertype1 = $this->core->getQueries()->getGradersFromUserType(1);
        $graders_from_usertype2 = $this->core->getQueries()->getGradersFromUserType(2);
        $graders_from_usertype3 = $this->core->getQueries()->getGradersFromUserType(3);
        $graders_from_usertypes = array($graders_from_usertype1, $graders_from_usertype2, $graders_from_usertype3);
        $template_list = $this->core->getQueries()->getAllGradeablesIdsAndTitles();
        $ini_data = array($rotatingGradeables, $gradeableSectionHistory, $num_sections, $graders_all_section, $graders_from_usertypes,
            $template_list);
        $this->core->getOutput()->renderOutput(array('admin', 'AdminGradeable'), 'show_add_gradeable', "add", $ini_data);
    }

    //view the page with pulled data from the gradeable to be edited
    private function editPage() {
        $rotatingGradeables = $this->core->getQueries()->getRotatingSectionsGradeableIDS();
        $gradeableSectionHistory = $this->core->getQueries()->getGradeablesPastAndSection();
        $num_sections = $this->core->getQueries()->getNumberRotatingSessions();
        $graders_all_section = $this->core->getQueries()->getGradersForAllRotatingSections($_REQUEST['id']);
        $graders_from_usertype1 = $this->core->getQueries()->getGradersFromUserType(1);
        $graders_from_usertype2 = $this->core->getQueries()->getGradersFromUserType(2);
        $graders_from_usertype3 = $this->core->getQueries()->getGradersFromUserType(3);
        $graders_from_usertypes = array($graders_from_usertype1, $graders_from_usertype2, $graders_from_usertype3);
        $template_list = $this->core->getQueries()->getAllGradeablesIdsAndTitles();
        $ini_data = array($rotatingGradeables, $gradeableSectionHistory, $num_sections, $graders_all_section, $graders_from_usertypes,
            $template_list);
        $data = $this->core->getQueries()->getGradeableData($_REQUEST['id']);
        $this->core->getOutput()->renderOutput(array('admin', 'AdminGradeable'), 'show_add_gradeable', "edit", $ini_data, $data);
    }

    //if $edit_gradeable === 0 then it uploads the gradeable to the database
    //if $edit_gradeable === 1 then it updates the gradeable to the database
    private function modifyGradeable($edit_gradeable) {
        $details = array();
        $details['g_id'] = $_POST['gradeable_id'];
        $details['g_title'] = htmlentities($_POST['gradeable_title']);
        $details['g_instructions_url'] = $_POST['instructions_url'];
        $details['g_overall_ta_instructions'] = $_POST['ta_instructions'];
        $details['g_use_teams'] = (isset($_POST['team_assignment']) && $_POST['team_assignment']=='yes')? "true" : "false";
        $details['g_gradeable_type'] = $_POST['gradeable_type'];
        if ($details['g_gradeable_type'] === "Electronic File") {
            $details['g_gradeable_type'] = GradeableType::ELECTRONIC_FILE;
        } else if ($details['g_gradeable_type'] === "Checkpoints") {
            $details['g_gradeable_type'] = GradeableType::CHECKPOINTS;
        } else if ($details['g_gradeable_type'] === "Numeric") {
            $details['g_gradeable_type'] = GradeableType::NUMERIC_TEXT;
        }
        $details['g_grade_by_registration'] = (isset($_POST['section_type']) && $_POST['section_type']=='reg_section')? "true" : "false";
        $details['g_ta_view_start_date'] = $_POST['date_ta_view'];
        $details['g_grade_start_date'] = $_POST['date_grade'];
        $details['g_grade_released_date'] = $_POST['date_released'];
        $details['g_min_grading_group'] = $_POST['minimum_grading_group'];
        $details['eg_submission_open_date'] = $_POST['date_submit'];
        $details['eg_submission_due_date'] = $_POST['date_due'];
        $details['eg_late_days'] = $_POST['eg_late_days'];
        $details['eg_is_repository'] = "false"; //may change
        //$details['eg_subdirectory'] = $_POST['subdirectory'];
        $details['eg_subdirectory'] = " ";
        $details['use_ta_grading'] = $_POST['ta_grading'];
        $details['eg_config_path'] = $_POST['config_path'];
        $details['eg_precision'] = $_POST['point_precision'];
        $details['array_gc_id'] = array();
        $details['array_gc_title'] = array();
        $details['array_gc_ta_comment'] = array();
        $details['array_gc_student_comment'] = array();
        $details['array_gc_max_value'] = array();
        $details['array_gc_is_text'] = array();
        $details['array_gc_is_extra_credit'] = array();
        $details['array_gc_order'] = array();
        $details['syllabus_bucket'] = $_POST['gradeable_buckets'];

        $num_questions = 0;
        foreach($_POST as $k=>$v){
            if(strpos($k,'comment_title_') !== false){
                ++$num_questions;
            }
        }
        $details['num_questions'] = $num_questions;
        if ($details['g_gradeable_type'] === 0) {
            for ($x = 0; $x < $num_questions; $x++) {
                $details['array_gc_id'][$x] = $x;
                $details['array_eg_gc_title'][$x] = $_POST['comment_title_' . strval($x + 1)];
                $details['array_gc_ta_comment'][$x] = $_POST['ta_comment_' . strval($x + 1)];
                $details['array_gc_student_comment'][$x] = $_POST['student_comment_' . strval($x + 1)];
                $details['array_gc_max_value'][$x] = $_POST['points_' . strval($x + 1)];
                $details['array_gc_is_text'][$x] = "false";
                $details['array_eg_gc_is_extra_credit'][$x] = (isset($_POST['eg_extra_'.strval($x+1)]) && $_POST['eg_extra_'.strval($x+1)]=='on')? "true" : "false";
                $details['array_gc_order'][$x] = $x;
            }
        }
        // create a gradeable component for each checkpoint
        $num_checkpoints = -1; // remove 1 for the template
        foreach($_POST as $k=>$v){
            if(strpos($k, 'checkpoint_label') !== false){
                ++$num_checkpoints;
            }
        }
        $details['num_checkpoints'] = $num_checkpoints;

        if ($details['g_gradeable_type'] === 1) {
            for ($x = 0; $x < $num_checkpoints; $x++) {
                $details['array_cp_gc_is_extra_credit'][$x] = (isset($_POST["checkpoint_extra_".strval($x+1)])) ? "true" : "false";
                $details['array_cp_gc_title'][$x] = $_POST['checkpoint_label_'. strval($x+1)];
            }
        }
        
        $num_numeric = intval($_POST['num_numeric_items']);
        $num_text= intval($_POST['num_text_items']);
        $details['num_numeric'] = $num_numeric;
        $details['num_text'] = $num_text;

        if ($details['g_gradeable_type'] === 2) {
            for($x=1; $x<=$num_numeric+$num_text; $x++){
                $details['array_gc_is_text'][$x] = ($x > $num_numeric)? "true" : "false";
                if($x > $num_numeric){
                    $details['array_nt_gc_title'][$x] = (isset($_POST['text_label_'. strval($x-$num_numeric)]))? $_POST['text_label_'. strval($x-$num_numeric)] : '';
                    $details['array_gc_max_value'][$x] = 0;
                    $details['array_nt_gc_is_extra_credit'][$x] ="false";
                }
                else{
                    $details['array_nt_gc_title'][$x] = (isset($_POST['numeric_label_'. strval($x)]))? $_POST['numeric_label_'. strval($x)] : '';
                    $details['array_gc_max_value'][$x] = (isset($_POST['max_score_'. strval($x)]))? $_POST['max_score_'. strval($x)] : 0;
                    $details['array_nt_gc_is_extra_credit'][$x] = (isset($_POST['numeric_extra_'.strval($x)]))? "true" : "false";
                }
            }
        }

        if ($edit_gradeable === 0) {
            $this->core->getQueries()->createNewGradeable($details);
        }
        else if ($edit_gradeable === 1) {
            $this->core->getQueries()->updateGradeable($details);
        }

        //set up roating sections
        $graders = array();
        foreach ($_POST as $k => $v ) {
            if (substr($k,0,7) === 'grader_' && !empty(trim($v))) {
                $graders[explode('_', $k)[1]]=explode(',',trim($v));
            }
        }

        if($details['g_grade_by_registration'] === 'false') {
            $this->core->getQueries()->setupRotatingSections($graders, $details['g_id']);
        }

        $fp = $this->core->getConfig()->getCoursePath() . '/config/form/form_'.$details['g_id'].'.json';
        if (!$fp){
           echo "Could not open file";
        }
        file_put_contents ($fp ,  json_encode(json_decode(urldecode($_POST['gradeableJSON'])), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->returnToNav();
    }

    private function quickLink() {
        $g_id = $_REQUEST['id'];
        $action = $_REQUEST['quick_link_action'];
        $gradeable = $this->core->getQueries()->getGradeable($g_id);
        if ($action === "release_grades_now") { //what happens on the quick link depends on the action
            $gradeable->setGradeReleasedDate(new \DateTime('now', $this->core->getConfig()->getTimezone()));
        } else if ($action === "open_ta_now") {
            $gradeable->setTaViewDate(new \DateTime('now', $this->core->getConfig()->getTimezone()));
        } else if ($action === "open_grading_now") {
            $gradeable->setGradeStartDate(new \DateTime('now', $this->core->getConfig()->getTimezone()));
        } else if ($action === "open_students_now") {
            $gradeable->setOpenDate(new \DateTime('now', $this->core->getConfig()->getTimezone()));
        } 
        $gradeable->updateGradeable();
        $this->returnToNav();
    }
    //return to the navigation page
    private function returnToNav() {
        $url = $this->core->buildUrl(array());
        header('Location: '. $url);
    }
}
