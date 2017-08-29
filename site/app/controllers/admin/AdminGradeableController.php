<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use \lib\Database;
use \lib\Functions;
use \app\libraries\GradeableType;
use app\models\AdminGradeable;
use app\models\Gradeable;
use app\models\GradeableComponent;
use app\models\GradeableComponentMark;
use \DateTime;

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
        $admin_gradeable = new AdminGradeable($this->core);
        $admin_gradeable->setRotatingGradeables($this->core->getQueries()->getRotatingSectionsGradeableIDS());
        $admin_gradeable->setGradeableSectionHistory($this->core->getQueries()->getGradeablesPastAndSection());
        $admin_gradeable->setNumSections($this->core->getQueries()->getNumberRotatingSections());
        $admin_gradeable->setGradersAllSection($this->core->getQueries()->getGradersForAllRotatingSections($_REQUEST['template_id']));
        $graders_from_usertype1 = $this->core->getQueries()->getGradersFromUserType(1);
        $graders_from_usertype2 = $this->core->getQueries()->getGradersFromUserType(2);
        $graders_from_usertype3 = $this->core->getQueries()->getGradersFromUserType(3);
        $graders_from_usertypes = array($graders_from_usertype1, $graders_from_usertype2, $graders_from_usertype3);
        $admin_gradeable->setGradersFromUsertypes($graders_from_usertypes);
        $admin_gradeable->setTemplateList($this->core->getQueries()->getAllGradeablesIdsAndTitles());
        $this->core->getQueries()->getGradeableInfo($_REQUEST['template_id'], $admin_gradeable);
        $this->core->getOutput()->renderOutput(array('admin', 'AdminGradeable'), 'show_add_gradeable', "add_template", $admin_gradeable);
    }

    //view the page with no data from previous gradeables
    private function viewPage() {
        $admin_gradeable = new AdminGradeable($this->core);
        $admin_gradeable->setRotatingGradeables($this->core->getQueries()->getRotatingSectionsGradeableIDS());
        $admin_gradeable->setGradeableSectionHistory($this->core->getQueries()->getGradeablesPastAndSection());
        $admin_gradeable->setNumSections($this->core->getQueries()->getNumberRotatingSections());
        $admin_gradeable->setGradersAllSection($this->core->getQueries()->getGradersForAllRotatingSections(""));
        $graders_from_usertype1 = $this->core->getQueries()->getGradersFromUserType(1);
        $graders_from_usertype2 = $this->core->getQueries()->getGradersFromUserType(2);
        $graders_from_usertype3 = $this->core->getQueries()->getGradersFromUserType(3);
        $graders_from_usertypes = array($graders_from_usertype1, $graders_from_usertype2, $graders_from_usertype3);
        $admin_gradeable->setGradersFromUsertypes($graders_from_usertypes);
        $admin_gradeable->setTemplateList($this->core->getQueries()->getAllGradeablesIdsAndTitles());
        $this->core->getOutput()->renderOutput(array('admin', 'AdminGradeable'), 'show_add_gradeable', "add", $admin_gradeable);
    }

    //view the page with pulled data from the gradeable to be edited
    private function editPage() {
        $rotatingGradeables = $this->core->getQueries()->getRotatingSectionsGradeableIDS();
        $gradeableSectionHistory = $this->core->getQueries()->getGradeablesPastAndSection();
        $num_sections = $this->core->getQueries()->getNumberRotatingSections();
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

    // check whether radio button's value is 'true'
    private function isRadioButtonTrue($name) {
        return isset($_POST[$name]) && $_POST[$name] === 'true';
    }

    //if $edit_gradeable === 0 then it uploads the gradeable to the database
    //if $edit_gradeable === 1 then it updates the gradeable to the database
    private function modifyGradeable($edit_gradeable) {
        $peer_grading_complete_score = 0;
        if ($edit_gradeable === 0) {
            $gradeable = new Gradeable($this->core);
            $gradeable->setId($_POST['gradeable_id']);
        } else {
            $gradeable = $this->core->getQueries()->getGradeable($_POST['gradeable_id']);
        }        
        $gradeable->setName(htmlentities($_POST['gradeable_title']));
        $gradeable->setInstructionsUrl($_POST['instructions_url']);
        $gradeable->setTaInstructions($_POST['ta_instructions']);
        $gradeable_type = $_POST['gradeable_type'];
        if ($gradeable_type === "Electronic File") {
            $gradeable_type = GradeableType::ELECTRONIC_FILE;
        } else if ($gradeable_type === "Checkpoints") {
            $gradeable_type = GradeableType::CHECKPOINTS;
        } else if ($gradeable_type === "Numeric") {
            $gradeable_type = GradeableType::NUMERIC_TEXT;
        }
        $gradeable->setType($gradeable_type);
        $grade_by_registration = (isset($_POST['section_type']) && $_POST['section_type']=='reg_section') ? true : false;
        $gradeable->setGradeByRegistration($grade_by_registration);
        $gradeable->setTaViewDate(new \DateTime($_POST['date_ta_view'], $this->core->getConfig()->getTimezone())); 
        $gradeable->setGradeStartDate(new \DateTime($_POST['date_grade'], $this->core->getConfig()->getTimezone()));
        $gradeable->setGradeReleasedDate(new \DateTime($_POST['date_released'], $this->core->getConfig()->getTimezone()));
        $gradeable->setMinimumGradingGroup($_POST['minimum_grading_group']);
        $gradeable->setBucket($_POST['gradeable_buckets']);
        if ($gradeable_type === GradeableType::ELECTRONIC_FILE) {
            $gradeable->setOpenDate(new \DateTime($_POST['date_submit'], $this->core->getConfig()->getTimezone()));
            $gradeable->setDueDate(new \DateTime($_POST['date_due'], $this->core->getConfig()->getTimezone()));
            $gradeable->setLateDays($_POST['eg_late_days']);
            $is_repository = (isset($_POST['upload_type']) && $_POST['upload_type']=='repository') ? true : false;
            $gradeable->setIsRepository($is_repository);
            $gradeable->setSubdirectory($_POST['subdirectory']);
            $gradeable->setPointPrecision(floatval($_POST['point_precision']));
            $is_ta_grading = (isset($_POST['ta_grading']) && $_POST['ta_grading']=='true') ? true : false;
            $gradeable->setTaGrading($is_ta_grading);
            if($is_ta_grading === false) { // sets that in order to not break a database constraint
                $gradeable->setGradeStartDate(new \DateTime($_POST['date_released'], $this->core->getConfig()->getTimezone()));
            }
            $gradeable->setTeamAssignment($this->isRadioButtonTrue('team_assignment'));
            $gradeable->setMaxTeamSize($_POST['eg_max_team_size']);
            $gradeable->setTeamLockDate(new \DateTime($_POST['date_team_lock'], $this->core->getConfig()->getTimezone()));
            $gradeable->setStudentView($this->isRadioButtonTrue('student_view'));
            $gradeable->setStudentSubmit($this->isRadioButtonTrue('student_submit'));
            $gradeable->setStudentDownload($this->isRadioButtonTrue('student_download'));
            $gradeable->setStudentAnyVersion($this->isRadioButtonTrue('student_any_version'));
            $gradeable->setConfigPath($_POST['config_path']);
            $is_peer_grading = $this->isRadioButtonTrue('peer_grading');
            $gradeable->setPeerGrading($is_peer_grading);
            if ($is_peer_grading) { 
                $gradeable->setPeerGradeSet($_POST['peer_grade_set']);
                $peer_grading_complete_score = $_POST['peer_grade_complete_score'];
            }
        }

        if ($edit_gradeable === 0) {
            $this->core->getQueries()->createNewGradeable($gradeable); 
        } else {
            $this->core->getQueries()->updateGradeable($gradeable); 
        }

        $num_questions = 0;
        $num_checkpoints = -1; // remove 1 for the template
        $num_numeric = intval($_POST['num_numeric_items']);
        $num_text = intval($_POST['num_text_items']);
        foreach($_POST as $k=>$v){
            if(strpos($k,'comment_title_') !== false){
                ++$num_questions;
            }
            if(strpos($k, 'checkpoint_label_') !== false){
                ++$num_checkpoints;
            }
        }
        
        if ($edit_gradeable === 1) {
            $old_components = $gradeable->getComponents();
            $num_old_components = count($old_components);
            $start_index = $num_old_components;
        }
        else {
            $start_index = 0;
        }

        if ($gradeable_type === GradeableType::ELECTRONIC_FILE) {
            $make_peer_assignments = false;
            if ($edit_gradeable === 1 && $gradeable->getPeerGrading()) {
                $old_peer_grading_assignments = $this->core->getQueries()->getPeerGradingAssignNumber($gradeable->getId());
                $make_peer_assignments = ($old_peer_grading_assignments !== $gradeable->getPeerGradeSet());
                if ($make_peer_assignments) {
                    $this->core->getQueries()->clearPeerGradingAssignments($_POST['gradeable_id']);
                }
            }
            if (($edit_gradeable === 0 || $make_peer_assignments) && $gradeable->getPeerGrading()) {
                $users = $this->core->getQueries()->getAllUsers();
                $user_ids = array();
                $grading = array();
                $peer_grade_set = $gradeable->getPeerGradeSet();
                foreach($users as $key=>$user) {
                    // Need to remove non-student users, or users in the NULL section
                    if ($user->getRegistrationSection() == null) {
                        unset($users[$key]);
                    }
                    else {
                        $user_ids[] = $user->getId();
                        $grading[$user->getId()] = array();
                    }
                }
                $user_number = count($user_ids);
                shuffle($user_ids);
                for($i = 0; $i<$user_number; $i++) {
                    for($j = 1; $j <=$peer_grade_set; $j++) {
                        $grading[$user_ids[$i]][] = $user_ids[($i+$j)%$user_number];
                    }
                }
                
                foreach($grading as $grader=> $assignment) {
                    foreach($assignment as $student) {
                        $this->core->getQueries()->insertPeerGradingAssignment($grader, $student, $gradeable->getId());
                    }
                }
            }

            if ($edit_gradeable === 1) {
                $x = 0;
                foreach ($old_components as $old_component) {
                    if(is_array($old_component)) {
                        $old_component = $old_component[0];
                    }
                    if ($x < $num_questions && $x < $num_old_components) {
                        if($old_component->getTitle() === "Grading Complete" || $old_component->getOrder() == -1) {
                            if($peer_grading_complete_score == 0) {
                                $this->core->getQueries()->deleteGradeableComponent($old_component);
                            }
                            else if($old_component->getMaxValue() != $peer_grading_complete_score) {
                                $old_component->setMaxValue($peer_grading_complete_score);
                                $old_component->setUpperClamp($peer_grading_complete_score);
                                $this->core->getQueries()->updateGradeableComponent($old_component);
                            }
                            continue;
                        }
                        $old_component->setTitle($_POST['comment_title_' . strval($x + 1)]);
                        $old_component->setTaComment($_POST['ta_comment_' . strval($x + 1)]);
                        $old_component->setStudentComment($_POST['student_comment_' . strval($x + 1)]);
                        $is_penalty = (isset($_POST['rad_penalty-'.strval($x+1)]) && $_POST['rad_penalty-'.strval($x+1)]=='yes') ? true : false;
                        $lower_clamp = ($is_penalty === true) ? floatval($_POST['lower_'.strval($x+1)]) : 0;
                        $old_component->setLowerClamp($lower_clamp);
                        $is_deduction = (isset($_POST['grade_by-'.strval($x+1)]) && $_POST['grade_by-'.strval($x+1)]=='count_down') ? true : false;
                        $temp_num = ($is_deduction === true) ? floatval($_POST['points_' . strval($x + 1)]) : 0;
                        $old_component->setDefault($temp_num);
                        $old_component->setMaxValue($_POST['points_' . strval($x + 1)]);
                        $is_extra = (isset($_POST['rad_extra_credit-'.strval($x+1)]) && $_POST['rad_extra_credit-'.strval($x+1)]=='yes') ? true : false;
                        $upper_clamp = ($is_extra === true) ? (floatval($_POST['points_' . strval($x+1)]) + floatval($_POST['upper_' . strval($x + 1)])) : floatval($_POST['points_' . strval($x+1)]);
                        $old_component->setUpperClamp($upper_clamp);
                        $old_component->setIsText(false);
                        $peer_grading_component = (isset($_POST['peer_component_'.strval($x+1)]) && $_POST['peer_component_'.strval($x+1)]=='on') ? true : false;
                        $old_component->setIsPeer($peer_grading_component);
                        if ($this->isRadioButtonTrue('pdf_page_student')) {
                            $page_component = -1;
                        }
                        else if ($this->isRadioButtonTrue('pdf_page')) {
                            $page_component = ($_POST['page_component_' . strval($x + 1)]);
                        }
                        else {
                            $page_component = 0;
                        }
                        echo $page_component;
                        $old_component->setPage($page_component);
                        $old_component->setOrder($x);
                        $this->core->getQueries()->updateGradeableComponent($old_component);
                    } else if ($num_old_components > $num_questions) {
                        $this->core->getQueries()->deleteGradeableComponent($old_component);
                    }
                    $x++;
                }
            } 
            for ($x = $start_index; $x < $num_questions; $x++) {
                if($x == 0 && $peer_grading_complete_score != 0) {
                    $gradeable_component = new GradeableComponent($this->core);
                    $gradeable_component->setMaxValue($peer_grading_complete_score);
                    $gradeable_component->setUpperClamp($peer_grading_complete_score);
                    $gradeable_component->setOrder($x-1);
                    $gradeable_component->setTitle("Grading Complete");
                    $this->core->getQueries()->createNewGradeableComponent($gradeable_component, $gradeable);
                }
                $gradeable_component = new GradeableComponent($this->core);
                $gradeable_component->setTitle($_POST['comment_title_' . strval($x + 1)]);
                $gradeable_component->setTaComment($_POST['ta_comment_' . strval($x + 1)]);
                $gradeable_component->setStudentComment($_POST['student_comment_' . strval($x + 1)]);
                $is_penalty = (isset($_POST['rad_penalty-'.strval($x+1)]) && $_POST['rad_penalty-'.strval($x+1)]=='yes') ? true : false;
                $lower_clamp = ($is_penalty === true) ? floatval($_POST['lower_'.strval($x+1)]) : 0;
                $gradeable_component->setLowerClamp($lower_clamp);
                $is_deduction = (isset($_POST['grade_by-'.strval($x+1)]) && $_POST['grade_by-'.strval($x+1)]=='count_down') ? true : false;
                $temp_num = ($is_deduction === true) ? floatval($_POST['points_' . strval($x + 1)]) : 0;
                $gradeable_component->setDefault($temp_num);
                $gradeable_component->setMaxValue($_POST['points_' . strval($x + 1)]);
                $is_extra = (isset($_POST['rad_extra_credit-'.strval($x+1)]) && $_POST['rad_extra_credit-'.strval($x+1)]=='yes') ? true : false;
                $upper_clamp = ($is_extra === true) ? (floatval($_POST['points_' . strval($x+1)]) + floatval($_POST['upper_' . strval($x + 1)])) : floatval($_POST['points_' . strval($x+1)]);
                $gradeable_component->setUpperClamp($upper_clamp);
                $gradeable_component->setIsText(false);
                $peer_grading_component = (isset($_POST['peer_component_'.strval($x+1)]) && $_POST['peer_component_'.strval($x+1)]=='on') ? true : false;
                $gradeable_component->setIsPeer($peer_grading_component);
                if ($this->isRadioButtonTrue('pdf_page_student')) {
                    $page_component = -1;
                }
                else if ($this->isRadioButtonTrue('pdf_page')) {
                    $page_component = ($_POST['page_component_' . strval($x + 1)]);
                }
                else {
                    $page_component = 0;
                }
                $gradeable_component->setPage($page_component);
                $gradeable_component->setOrder($x);
                $this->core->getQueries()->createNewGradeableComponent($gradeable_component, $gradeable); 
            }
            

            //remake the gradeable to update all the data
            $gradeable = $this->core->getQueries()->getGradeable($_POST['gradeable_id']);

            if ($edit_gradeable === 0) {
                $components = $gradeable->getComponents();
                $index = 1;
                foreach ($components as $comp) {
                    if(is_array($comp)) {
                        $comp = $comp[0];
                    }
                    if($comp->getOrder() == -1) {
                        continue;
                    }
                    $num_marks = 0;
                    foreach($_POST as $k=>$v){
                        if(strpos($k,'mark_points_' . $index) !== false){
                            $num_marks++;
                        }
                    }

                    for ($y = 0; $y < $num_marks; $y++) {
                        $mark = new GradeableComponentMark($this->core);
                        $mark->setGcId($comp->getId());
                        $mark->setPoints(floatval($_POST['mark_points_' . $index . '_' . $y]));
                        $mark->setNote($_POST['mark_text_' . $index . '_' . $y]);
                        $mark->setOrder($y);
                        $this->core->getQueries()->createGradeableComponentMark($mark);
                    }                    
                    $index++;
                }            
            }
            else if ($edit_gradeable === 1) {
                $components = $gradeable->getComponents();
                $index = 1;
                foreach ($components as $comp) {
                    if(is_array($comp)) {
                        $comp = $comp[0];
                    }
                    if($comp->getOrder() == -1) {
                        continue;
                    }
                    $num_marks = 0; //current number of marks
                    foreach($_POST as $k=>$v){
                        if(strpos($k,'mark_points_' . $index) !== false){
                            $num_marks++;
                        }
                    }

                    $marks = $comp->getMarks();
                    $num_old_mark = count($marks); //old number of marks
                    //if old > new, delete old
                    //if old < new, create more

                    $y = 0;
                    foreach($marks as $mark) {
                        if($y < $num_marks && $y < $num_old_mark) {
                            $mark->setGcId($comp->getId());
                            $mark->setPoints(floatval($_POST['mark_points_' . $index . '_' . $y]));
                            $mark->setNote($_POST['mark_text_' . $index . '_' . $y]);
                            $mark->setOrder($y);
                            $this->core->getQueries()->updateGradeableComponentMark($mark);
                        } else if($num_old_mark > $num_marks) {
                            $this->core->getQueries()->deleteGradeableComponentMark($mark);
                        }
                        $y++; 
                    }
                    for($y = $num_old_mark; $y < $num_marks; $y++) {
                        $mark = new GradeableComponentMark($this->core);
                        $mark->setGcId($comp->getId());
                        $mark->setPoints(floatval($_POST['mark_points_' . $index . '_' . $y]));
                        $mark->setNote($_POST['mark_text_' . $index . '_' . $y]);
                        $mark->setOrder($y);
                        $this->core->getQueries()->createGradeableComponentMark($mark);
                    }             
                    $index++;
                }               
            }                
        } else if($gradeable->getType() === GradeableType::CHECKPOINTS) { 
            if ($edit_gradeable === 1) {
                $x = 0;
                foreach ($old_components as $old_component) {
                    if ($x < $num_checkpoints && $x < $num_old_components) {
                        $old_component->setTitle($_POST['checkpoint_label_' . strval($x + 1)]);
                        $old_component->setTaComment("");
                        $old_component->setStudentComment("");
                        $old_component->setLowerClamp(0);
                        $old_component->setDefault(0);
                        // if it is extra credit then it woull be out of 0 points otherwise 1
                        $max_value = (isset($_POST['checkpoint_extra_'.strval($x+1)])) ? 0 : 1;
                        $old_component->setMaxValue($max_value);
                        $old_component->setUpperClamp(1);
                        $old_component->setIsText(false);
                        $old_component->setIsPeer(false);
                        $old_component->setOrder($x);
                        $old_component->setPage(0);
                        $this->core->getQueries()->updateGradeableComponent($old_component);
                    } else if ($num_old_components > $num_checkpoints) {
                        $this->core->getQueries()->deleteGradeableComponent($old_component);
                    }
                    $x++;
                }
            }
            for ($x = $start_index; $x < $num_checkpoints; $x++) {
                $gradeable_component = new GradeableComponent($this->core);
                $gradeable_component->setTitle($_POST['checkpoint_label_' . strval($x + 1)]);
                $gradeable_component->setTaComment("");
                $gradeable_component->setStudentComment("");
                $gradeable_component->setLowerClamp(0);
                $gradeable_component->setDefault(0);
                // if it is extra credit then it woull be out of 0 points otherwise 1
                $max_value = (isset($_POST['checkpoint_extra_'.strval($x+1)])) ? 0 : 1;
                $gradeable_component->setMaxValue($max_value);
                $gradeable_component->setUpperClamp(1);
                $gradeable_component->setIsText(false);
                $gradeable_component->setIsPeer(false);
                $gradeable_component->setOrder($x);
                $gradeable_component->setPage(0);
                $this->core->getQueries()->createNewGradeableComponent($gradeable_component, $gradeable); 
            }
        } else if($gradeable->getType() === GradeableType::NUMERIC_TEXT) {
            $start_index_numeric = 0;
            $start_index_text = 0;
            if ($edit_gradeable === 1) {
                $old_numerics = array();
                $num_old_numerics = 0;
                $old_texts = array();
                $num_old_texts = 0;
                foreach ($old_components as $old_component) {
                    if($old_component->getIsText() === true) {
                        $old_texts[] = $old_component;
                        $num_old_texts++;
                    }
                    else {
                        $old_numerics[] = $old_component;
                        $num_old_numerics++;
                    }
                }
                $x = 0;
                foreach ($old_numerics as $old_numeric) {
                    if ($x < $num_numeric && $x < $num_old_numerics) {
                        $old_numeric->setTitle($_POST['numeric_label_'. strval($x + 1)]);
                        $old_numeric->setTaComment("");
                        $old_numeric->setStudentComment("");
                        $old_numeric->setLowerClamp(0);
                        $old_numeric->setDefault(0);
                        $max_value = (isset($_POST['numeric_extra_'.strval($x+1)])) ? 0 : $_POST['max_score_'. strval($x + 1)];
                        $old_numeric->setMaxValue($max_value);
                        $old_numeric->setUpperClamp($_POST['max_score_'. strval($x + 1)]);
                        $old_numeric->setIsText(false);
                        $old_numeric->setIsPeer(false);
                        $old_numeric->setOrder($x);
                        $old_numeric->setPage(0);
                        $this->core->getQueries()->updateGradeableComponent($old_numeric);
                        $start_index_numeric++; 
                    }
                    else if ($num_old_numerics > $num_numeric) {
                        $this->core->getQueries()->deleteGradeableComponent($old_numeric);
                    }
                    $x++;
                }
            }
                for ($x = $start_index_numeric; $x < $num_numeric; $x++) {
                    $gradeable_component = new GradeableComponent($this->core);
                    $gradeable_component->setTitle($_POST['numeric_label_'. strval($x + 1)]);
                    $gradeable_component->setTaComment("");
                    $gradeable_component->setStudentComment("");
                    $gradeable_component->setLowerClamp(0);
                    $gradeable_component->setDefault(0);
                    $max_value = (isset($_POST['numeric_extra_'.strval($x+1)])) ? 0 : $_POST['max_score_'. strval($x + 1)];
                    $gradeable_component->setMaxValue($max_value);
                    $gradeable_component->setUpperClamp($_POST['max_score_'. strval($x + 1)]);
                    $gradeable_component->setIsText(false);
                    $gradeable_component->setIsPeer(false);
                    $gradeable_component->setOrder($x);
                    $gradeable_component->setPage(0);
                    $this->core->getQueries()->createNewGradeableComponent($gradeable_component, $gradeable); 
                }
                $z = $x;
                $x = 0;
            if ($edit_gradeable === 1) {
                foreach ($old_texts as $old_text) {
                    if ($x < $num_text && $x < $num_old_texts) {
                        $old_text->setTitle($_POST['text_label_'. strval($x + 1)]);
                        $old_text->setTaComment("");
                        $old_text->setStudentComment("");
                        $old_text->setLowerClamp(0);
                        $old_text->setDefault(0);
                        $old_text->setMaxValue(0);
                        $old_text->setUpperClamp(0);
                        $old_text->setIsText(true);
                        $old_text->setIsPeer(false);
                        $old_text->setPage(0);
                        $old_text->setOrder($z + $x);
                        $this->core->getQueries()->updateGradeableComponent($old_text);
                        $start_index_text++; 
                    }
                    else if ($num_old_texts > $num_text) {
                        $this->core->getQueries()->deleteGradeableComponent($old_text);
                    }
                    $x++;
                }
            }
            
            for ($y = $start_index_text; $y < $num_text; $y++) {
                $gradeable_component = new GradeableComponent($this->core);
                $gradeable_component->setTitle($_POST['text_label_'. strval($y + 1)]);
                $gradeable_component->setTaComment("");
                $gradeable_component->setStudentComment("");
                $gradeable_component->setLowerClamp(0);
                $gradeable_component->setDefault(0);
                $gradeable_component->setMaxValue(0);
                $gradeable_component->setUpperClamp(0);
                $gradeable_component->setIsText(true);
                $gradeable_component->setIsPeer(false);
                $gradeable_component->setPage(0);
                $gradeable_component->setOrder($y + $z);
                $this->core->getQueries()->createNewGradeableComponent($gradeable_component, $gradeable); 
            }
        } else {
            throw new \InvalidArgumentException("Error.");
        }

        //set up roating sections
        $graders = array();
        foreach ($_POST as $k => $v ) {
            if (substr($k,0,7) === 'grader_' && !empty(trim($v))) {
                $graders[explode('_', $k)[1]]=explode(',',trim($v));
            }
        }

        if($gradeable->getGradeByRegistration() === false) {
            $this->core->getQueries()->setupRotatingSections($graders, $_POST['gradeable_id']);
        }

        $fp = $this->core->getConfig()->getCoursePath() . '/config/form/form_'.$_POST['gradeable_id'].'.json';
        if (!$fp){
           echo "Could not open file";
        }
        file_put_contents ($fp ,  json_encode(json_decode(urldecode($_POST['gradeableJSON'])), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));


        // --------------------------------------------------------------
        // Write queue file to build this assignment...
        $semester=$this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();
        // FIXME:  should use a variable intead of hardcoded top level path
        $config_build_file = "/var/local/submitty/to_be_built/".$semester."__".$course."__".$_POST['gradeable_id'].".json";

        $config_build_data = array("semester" => $semester,
                                   "course" => $course,
                                   "gradeable" =>  $_POST['gradeable_id']);

        if (file_put_contents($config_build_file, json_encode($config_build_data, JSON_PRETTY_PRINT)) === false) {
          die("Failed to write file {$config_build_file}");
        }


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
