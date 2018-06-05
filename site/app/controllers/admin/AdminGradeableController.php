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
use app\libraries\FileUtils;

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
                $this->editPage(array_key_exists('nav_tab', $_REQUEST) ? $_REQUEST['nav_tab'] : 0);
                break;
            case 'update_gradeable':
                $this->updateGradeable();
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
            case 'delete_gradeable':
                $this->deleteGradeable();
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
        $admin_gradeable = $this->getAdminGradeable($_REQUEST['template_id']);
        $this->core->getQueries()->getGradeableInfo($_REQUEST['template_id'], $admin_gradeable, true);
        $this->core->getOutput()->renderOutput(array('admin', 'AdminGradeable'), 'show_add_gradeable', "add_template", $admin_gradeable);
    }

    //view the page with no data from previous gradeables
    private function viewPage() {
        $admin_gradeable = $this->getAdminGradeable("");
        $this->core->getOutput()->renderOutput(array('admin', 'AdminGradeable'), 'show_add_gradeable', "add", $admin_gradeable);
    }

    //view the page with pulled data from the gradeable to be edited
    private function editPage($nav_tab = 0) {
        $admin_gradeable = $this->getAdminGradeable($_REQUEST['id']);
        $this->core->getQueries()->getGradeableInfo($_REQUEST['id'], $admin_gradeable, false);
        $this->core->getOutput()->renderOutput(array('admin', 'AdminGradeable'), 'show_add_gradeable', "edit", $admin_gradeable, $nav_tab);
    }

    private function getAdminGradeable($gradeable_id) {
        $admin_gradeable = new AdminGradeable($this->core);
        $admin_gradeable->setRotatingGradeables($this->core->getQueries()->getRotatingSectionsGradeableIDS());
        $admin_gradeable->setGradeableSectionHistory($this->core->getQueries()->getGradeablesPastAndSection());
        $admin_gradeable->setNumSections($this->core->getQueries()->getNumberRotatingSections());
        $admin_gradeable->setGradersAllSection($this->core->getQueries()->getGradersForAllRotatingSections($gradeable_id));
        $graders_from_usertype1 = $this->core->getQueries()->getGradersFromUserType(1);
        $graders_from_usertype2 = $this->core->getQueries()->getGradersFromUserType(2);
        $graders_from_usertype3 = $this->core->getQueries()->getGradersFromUserType(3);

        // Be sure to have this array start at 1 since instructor's permission level is 1
        $graders_from_usertypes = array(1=>$graders_from_usertype1, 2=>$graders_from_usertype2, 3=>$graders_from_usertype3);
        $admin_gradeable->setGradersFromUsertypes($graders_from_usertypes);
        $admin_gradeable->setTemplateList($this->core->getQueries()->getAllGradeablesIdsAndTitles());
        // $admin_gradeable->setInheritTeamsList($this->core->getQueries()->getAllElectronicGradeablesWithBaseTeams());
        return $admin_gradeable;
    }

    /**
     * Checks if a gradeable is valid
     *
     * @param $admin_gradeable the gradeable to validate
     *
     * @return array error messages
     */
    private static function validateGradeable($admin_gradeable)
    {
        // For now, only check that the dates are valid, but here's a list of checks:
        //  -Non-blank Name
        //  -one 'type' checkbox is checked
        //  -non-blank autograding config (for electronic submission)
        //  -maybe some warnings about the rubric

        // Messages array that holds warning/error messages for
        //  any AdminGradeable Properties that have issues
        $messages = array();


        if($admin_gradeable->g_title === '') {
            $messages['g_title'] = 'Title cannot be blank!';
        }

        $ta_view = null;
        $open = null;
        $due = null;
        $grade = null;
        $release = null;
        $late_interval = null;
        $max_due = null;

        // Make sure that all of the provided dates are in a valid format
        try {
            $ta_view = new \DateTime($admin_gradeable->getGTaViewStartDate());
        } catch (\Exception $e) {
            $messages['g_ta_view_start_date'] = 'Invalid Format!';
        }

        try {
            $open = new \DateTime($admin_gradeable->getEgSubmissionOpenDate());
        } catch (\Exception $e) {
            $messages['eg_submission_open_date'] = 'Invalid Format!';
        }

        try {
            $due = new \DateTime($admin_gradeable->getEgSubmissionDueDate());
        } catch (\Exception $e) {
            $messages['eg_submission_due_date'] = 'Invalid Format!';
        }

        try {
            $grade = new \DateTime($admin_gradeable->getGGradeStartDate());
        } catch (\Exception $e) {
            $messages['g_grade_start_date'] = 'Invalid Format!';
        }

        try {
            $release = new \DateTime($admin_gradeable->getGGradeReleasedDate());
        } catch (\Exception $e) {
            $messages['g_grade_released_date'] = 'Invalid Format!';
        }

        try {
            $late_interval = new \DateInterval('P' . strval($admin_gradeable->getEgLateDays()) . 'D');
        } catch (\Exception $e) {
            $messages['eg_late_days'] = 'Invalid Format!';
        }

        if(!($due === null || $late_interval === null)) {
            $max_due = $due->add($late_interval);
        }

        // No validation for team lock dates (tbd)

        if($admin_gradeable->getGGradeableType() === GradeableType::ELECTRONIC_FILE) {
            if(!($ta_view === null || $open === null) && $ta_view > $open) {
                $messages['g_ta_view_start_date']   = 'TA Beta Testing Date must not be later than Submission Open Date';
            }
            if(!($open === null || $due === null) && $open > $due) {
                $message['eg_submission_open_date'] = 'Submission Open Date must not be later than Submission Due Date';
            }

            if($admin_gradeable->getEgUseTaGrading()) {

                if(!($due === null || $grade === null) && $due > $grade) {
                    $message['g_grade_start_date']      = 'Manual Grading Open Date must be no earlier than Due Date';
                }
                else if(!($due === null || $grade === null) && $max_due > $grade) {
                    $message['g_grade_start_date']      = '[Warning] Manual Grading Open Date should be no earlier than Due Date';
                }

                if(!($grade === null || $release === null) && $grade > $release) {
                    $message['g_grade_released_date']   = 'Grades Released Date must be later than the Manual Grading Open Date';
                }
            }
            else {

                if(!($max_due === null || $release === null) && $max_due > $release) {
                    $message['g_grade_released_date']   = 'Grades Released Date must be later than the Due Date + Max Late Days';
                }
            }
        }
        else {
            // The only check if its not an electronic gradeable
            if(!($ta_view === null || $release === null) && $ta_view > $release) {
                $message['g_grade_released_date']       = 'Grades Released Date must be later than the TA Beta Testing Date';
            }
        }

        return $messages;
    }

    // check whether radio button's value is 'true'
    private function isRadioButtonTrue($name) {
        return isset($_POST[$name]) && $_POST[$name] === 'true';
    }

    // Maintain previous function while adding new 'update field' feature
    private function modifyGradeable($edit_gradeable) {
        $peer_grading_complete_score = 0;
        if ($edit_gradeable === 0) {
            $gradeable = new Gradeable($this->core);
            $gradeable->setId($_POST['gradeable_id']);
        } else {
            $gradeable = $this->core->getQueries()->getGradeable($_POST['gradeable_id']);
        }
        $gradeable->setName(filter_input(INPUT_POST, 'gradeable_title', FILTER_SANITIZE_SPECIAL_CHARS));
        $gradeable->setInstructionsUrl(filter_input(INPUT_POST, 'instructions_url', FILTER_SANITIZE_SPECIAL_CHARS));
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
            // $gradeable->setInheritTeamsFrom($_POST['eg_inherit_teams_from']);
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
            $components = $gradeable->getComponents();
            //Adds/Edits/Deletes the Marks
            $index = 1;
            foreach ($components as $comp) {
                $marks = $comp->getMarks();
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
                    //adds the mark if it is new
                    if($_POST['mark_gcmid_' . $index . '_' . $y] == "NEW") {
                        $mark = new GradeableComponentMark($this->core);
                        $mark->setGcId($comp->getId());
                        $mark->setPoints(floatval($_POST['mark_points_' . $index . '_' . $y]));
                        $mark->setNote($_POST['mark_text_' . $index . '_' . $y]);
                        if (isset($_POST['mark_publish_' . $index . '_' . $y])) {
                            $mark->setPublish(true);
                        } else {
                            $mark->setPublish(false);
                        }
                        $mark->setOrder($y);
                        $this->core->getQueries()->createGradeableComponentMark($mark);
                    } else { //edits existing marks
                        foreach($marks as $mark) {
                            if($_POST['mark_gcmid_' . $index . '_' . $y] == $mark->getId()) {
                                $mark->setGcId($comp->getId());
                                $mark->setPoints(floatval($_POST['mark_points_' . $index . '_' . $y]));
                                $mark->setNote($_POST['mark_text_' . $index . '_' . $y]);
                                $mark->setOrder($y);
                                if (isset($_POST['mark_publish_' . $index . '_' . $y])) {
                                	$mark->setPublish(true);
                                } else {
                                	$mark->setPublish(false);
                                }
                                $this->core->getQueries()->updateGradeableComponentMark($mark);
                            }
                        }
                    }

                    //delete marks marked for deletion
                    $is_there_deleted = false;
                    $gcm_ids_deletes = explode(",", $_POST['component_deleted_marks_' . $index]);
                    foreach($gcm_ids_deletes as $gcm_id_to_delete) {
                    	foreach($marks as $mark) {
                    		if ($gcm_id_to_delete == $mark->getId()) {
								$this->core->getQueries()->deleteGradeableComponentMark($mark);
								$is_there_deleted = true;
                    		}
                    	}
                    }

                    //since we delete some marks, we must now reorder them. Also it is important to note that 
                    //$marks is sorted by gcm_order in increasing order
                    if($is_there_deleted === true) {
                    	$temp_order = 0;
                    	foreach ($marks as $mark) {
                    		//if the mark's id is a deleted id, skip it
                    		$is_deleted = false;
                    		foreach ($gcm_ids_deletes as $gcm_id_to_delete) {
                    			if ($gcm_id_to_delete == $mark->getId()) {
                    				$is_deleted = true;
                    				break;
                    			}
                    		}
                    		if(!$is_deleted) {
                    			$mark->setOrder($temp_order);
                    			$this->core->getQueries()->updateGradeableComponentMark($mark);
                    			$temp_order++;
                    		}
                    	}
                    }
                }
                $index++;
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

        //set up rotating sections
        $graders = array();
        foreach ($_POST as $k => $v ) {
            if (substr($k,0,7) === 'grader_' && !empty(trim($v))) {
                $sections = explode('_', $k);
                $graders[$sections[3]][] = $sections[2];
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


        if($edit_gradeable === 0) {
            $this->redirectToEdit(); // redirect to next page of the form
        }
        else {
            $this->returnToNav();
        }
    }

    private function updateGradeable() {
	    // Get existing gradeable
        $admin_gradeable = new AdminGradeable($this->core);
        $this->core->getQueries()->getGradeableInfo($_REQUEST['id'], $admin_gradeable, false);

        $errors = array();
        $blacklist = [
            'g_id' => 'Gradeable Id',
            'g_gradeable_type' => 'Gradeable Type',
            'eg_team_assignment' => 'Teamness',
            'eg_is_repository' => 'Upload Method'
        ];

        // Apply new values for all properties submitted
        foreach($_POST as $prop=>$post_val) {

            // small blacklist (values that can't change)
            if(key_exists($prop, $blacklist)) {
                $errors[$prop] = 'Cannot Change ' . $blacklist[$prop] . ' once created';
                continue;
            }

            // Submitter is trying to set this property
            try {
                if(property_exists($admin_gradeable, $prop)){
                    $admin_gradeable->$prop = $post_val;
                }
                else {
                    $errors[$prop] = 'Not Found!';
                }
            } catch(\Exception $e){
                $errors[$prop] = $e;
            }
        }

        // If the post array is 0, that means that the name of the element was blank
        if(count($_POST) === 0) {
            $errors['general'] = 'Request contained no properties, perhaps the name was blank?';
        }

        // TODO: Construct complex values for validation (i.e. rubrics)

        $errors = array_merge($errors, self::validateGradeable($admin_gradeable));

        $response_data = [];

        // Be strict.  Only apply database changes if there were no errors.
        if(count($errors) === 0) {
            // TODO: apply to database

            http_response_code(204); // NO CONTENT
        }
        else {
            $response_data['errors'] = $errors;
            http_response_code(400);
        }

        // Finally, send the requester back the information
        $this->core->getOutput()->renderJson($response_data);
    }

    private function deleteGradeable() {
        $g_id = $_REQUEST['id'];

        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] != $this->core->getCsrfToken()) {
            die("Invalid CSRF Token");
            $this->returnToNav();
        }
        if (!$this->core->getUser()->accessAdmin()) {
            die("Only admins can delete gradeable");
            $this->returnToNav();
        }
        $this->core->getQueries()->deleteGradeable($g_id);

        $course_path = $this->core->getConfig()->getCoursePath();
        $semester=$this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();
        
        $file = FileUtils::joinPaths($course_path,"config","form","form_".$g_id.".json");
        if ((file_exists($file)) && (!unlink($file))){
            die("Cannot delete form_{$g_id}.json");
        }

        $config_build_file = "/var/local/submitty/to_be_built/".$semester."__".$course."__".$g_id.".json";
        $config_build_data = array("semester" => $semester,
                                   "course" => $course,
                                   "no_build" => true);

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
    private function redirectToEdit() {
	    $url = $this->core->buildUrl([
	        'component' => 'admin',
            'page'      => 'admin_gradeable',
            'action'    => 'edit_gradeable_page',
            'id'        => $_POST['gradeable_id'],
            'nav_tab'   => '-1']);
	    header('Location: '.$url);
    }
}
