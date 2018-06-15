<?php
namespace app\views;
use \app\libraries\GradeableType;
use app\models\Gradeable;
use app\libraries\FileUtils;


class NavigationView extends AbstractView {
    public function noAccessCourse() {
        return <<<HTML
<div class="content">
   You don't have access to {$this->core->getDisplayedCourseName()}. If you think this is mistake,
   please contact your instructor to gain access.
</div>
HTML;
    }
    public function showGradeables($sections_to_list) {
        $site_url = $this->core->getConfig()->getSiteUrl();
        $return = "";

        // ======================================================================================
        // DISPLAY CUSTOM BANNER (typically used for exam seating assignments)
        // note: placement of this information this may eventually be re-designed
        // ======================================================================================
        $message_file_path = $this->core->getConfig()->getCoursePath()."/reports/summary_html/".$this->core->getUser()->getId()."_message.html";
        $message_file_contents = "";
        if (file_exists($message_file_path)) {
            $message_file_contents = file_get_contents($message_file_path);
        }
        $display_custom_message = $this->core->getConfig()->displayCustomMessage();
        if ($display_custom_message && $message_file_contents != "") {
          $return .= <<<HTML
<div class="content">
   {$message_file_contents}
</div>
HTML;
        }
        $return .= <<<HTML
<div class="content">
    <div class="nav-buttons">
HTML;
        // ======================================================================================
        // IMAGES BUTTON -- visible to limited access graders and up
        // ======================================================================================
        if ($this->core->getUser()->accessGrading()) {
            $return .= <<<HTML
        <a class="btn btn-primary" href="{$this->core->buildUrl(array('component' => 'grading', 'page' => 'images', 'action' => 'view_images_page'))}">Images</a>
HTML;
        }
        // ======================================================================================
        // CREATE NEW IMAGES & GRADEABLE BUTTON -- only visible to instructors
        // ======================================================================================
        if ($this->core->getUser()->accessAdmin()) {
            $return .= <<<HTML
        <a class="btn btn-primary" href="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'view_gradeable_page'))}">New Gradeable</a>
        <a class="btn btn-primary" href="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'gradeable', 'action' => 'upload_config'))}">Upload Config & Review Build Output</a>
HTML;
        }
        // ======================================================================================
        // LATE DAYS TABLE BUTTON
        // ======================================================================================

        $return .= <<<HTML
        <a class="btn btn-primary" href="{$this->core->buildUrl(array('component' => 'student', 'page' => 'view_late_table'))}">Show my late days information</a>
HTML;
        // ======================================================================================
        // FORUM BUTTON
        // ======================================================================================

        if($this->core->getConfig()->isForumEnabled()) {
            $return .= <<<HTML
            <a class="btn btn-primary" href="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread'))}">Discussion Forum</a>
HTML;
        }
        // ======================================================================================
        // GRADES SUMMARY BUTTON
        // ======================================================================================
        $display_rainbow_grades_summary = $this->core->getConfig()->displayRainbowGradesSummary();
        if ($display_rainbow_grades_summary) {
        $return .= <<<HTML
        <a class="btn btn-primary" href="{$this->core->buildUrl(array('component' => 'student', 'page' => 'rainbow'))}">View Grades</a>
HTML;
          }
        $return .= <<<HTML
    </div>
HTML;
        // ======================================================================================
        // INDEX OF ALL GRADEABLES
        // ======================================================================================
        $return .= <<<HTML
    <table class="gradeable_list" style="width:100%;">
HTML;
	//What title is displayed to the user for each category
        $title_to_category_title = array(
            "FUTURE" => "FUTURE &nbsp;&nbsp; <em>visible only to Instructors</em>",
            "BETA" => "BETA &nbsp;&nbsp; <em>open for testing by TAs</em>",
            "OPEN" => "OPEN",
            "CLOSED" => "PAST DUE",
            "ITEMS BEING GRADED" => "CLOSED &nbsp;&nbsp; <em>being graded by TA/Instructor</em>",
            "GRADED" => "GRADES AVAILABLE"
        );
        //What bootstrap button the student button will be. Information about bootstrap buttons can be found here:
        //https://www.w3schools.com/bootstrap/bootstrap_buttons.asp
        $title_to_button_type_submission = array(
            "FUTURE" => "btn-default",
            "BETA" => "btn-default",
            "OPEN" => "btn-primary" ,
            "CLOSED" => "btn-danger",
            "ITEMS BEING GRADED" => "btn-default",
            "GRADED" => 'btn-success'
        );
        //What bootstrap button the instructor/TA button will be
        $title_to_button_type_grading = array(
            "FUTURE" => "btn-default",
            "BETA" => "btn-default",
            "OPEN" => "btn-default" ,
            "CLOSED" => "btn-default",
            "ITEMS BEING GRADED" => "btn-primary",
            "GRADED" => 'btn-danger');
        //The general text of the button under the category
        //It is general since the text could change depending if the user submitted something or not and other factors.
        $title_to_prefix = array(
            "FUTURE" => "ALPHA SUBMIT",
            "BETA" => "BETA SUBMIT",
            "OPEN" => "SUBMIT",
            "CLOSED" => "LATE SUBMIT",
            "ITEMS BEING GRADED" => "VIEW SUBMISSION",
            "GRADED" => "VIEW GRADE"
        );

        $found_assignment = false;
        foreach ($sections_to_list as $title => $gradeable_list) {
            /** @var Gradeable[] $gradeable_list */
            // temporary: want to make future - only visible to
            //  instructor (not released for submission to graders)
            //  and future - grader preview
            //  (released to graders for submission)
            //if ($title == "FUTURE" && !$this->core->getUser()->accessAdmin()) {
            if (($title === "FUTURE" || $title === "BETA") && !$this->core->getUser()->accessGrading()) {
                continue;
            }
            // count the # of electronic gradeables in this category that can be viewed
            $electronic_gradeable_count = 0;
            foreach ($gradeable_list as $gradeable => $g_data) {
                if ($g_data->getType() == GradeableType::ELECTRONIC_FILE && $g_data->getStudentView()) {
                    $electronic_gradeable_count++;
                    continue;
                }
            }
            // if there are no gradeables, or if its a student and no electronic upload gradeables, don't show this category
            if (count($gradeable_list) == 0 ||
                ($electronic_gradeable_count == 0 && !$this->core->getUser()->accessGrading())) {
                continue;
            } else {
                $found_assignment = true;
            }
            $lower_title = str_replace(" ", "_", strtolower($title));
            $return .= <<<HTML
        <tr class="bar"><td colspan="10"></td></tr>
        <tr class="colspan nav-title-row" id="{$lower_title}"><td colspan="4">{$title_to_category_title[$title]}</td></tr>
        <tbody id="{$lower_title}_tbody">
HTML;
            $title_save = $title;
            $btn_title_save = $title_to_button_type_submission[$title];
            foreach ($gradeable_list as $gradeable => $g_data) {
                if (!$this->core->getUser()->accessGrading()){

                    if ($g_data->getActiveVersion() === 0 && $g_data->getCurrentVersionNumber() != 0){
                        $submission_status = array(
                            "SUBMITTED" => "<em style='font-size: .8em;'></em><br>",
                            "AUTOGRADE" => ""
                        );
                    }
                    else if ($g_data->getActiveVersion() === 0 && $g_data->getCurrentVersionNumber() === 0){
                        $submission_status = array(
                            "SUBMITTED" => "<em style='font-size: .8em;'></em><br>",
                            "AUTOGRADE" => ""
                        );
                    }
                    else{
                        if ($g_data->getTotalNonHiddenNonExtraCreditPoints() == array() && ($title_save != "GRADED" && $title_save != "ITEMS BEING GRADED")){
                            $submission_status = array(
                                "SUBMITTED" => "<em style='font-size: .8em;'></em><br>",
                                "AUTOGRADE" => ""
                            );
                        }
                        else if ($g_data->getTotalNonHiddenNonExtraCreditPoints() != array() && ($title_save != "GRADED" && $title_save != "ITEMS BEING GRADED")){
                            $autograde_points_earned = $g_data->getGradedNonHiddenPoints();
                            $autograde_points_total = $g_data->getTotalNonHiddenNonExtraCreditPoints();
                            $submission_status = array(
                                "SUBMITTED" => "",
                                "AUTOGRADE" => "<em style='font-size: .8em;'></em><br>"
                            );
                        }
                        else if ($g_data->getTotalNonHiddenNonExtraCreditPoints() != array() && ($title_save == "GRADED" || $title_save == "ITEMS BEING GRADED")){
                            $submission_status = array(
                                "SUBMITTED" => "",
                                "AUTOGRADE" => ""
                            );
                        }
                        else{
                            $autograde_points_earned = $g_data->getGradedNonHiddenPoints();
                            $autograde_points_total = $g_data->getTotalNonHiddenNonExtraCreditPoints();
                            $submission_status = array(
                                "SUBMITTED" => "",
                            //    "AUTOGRADE" => "<em style='font-size: .8em;'>(" . $autograde_points_earned . "/" . $autograde_points_total . ")</em><br>"
                            );

                        }
                    }
                }
                else{ //don't show submission_status to instructors
                    $submission_status = array(
                        "SUBMITTED" => "<br>",
                        "AUTOGRADE" => ""
                    );
                }
                $title = $title_save;
                $title_to_button_type_submission[$title_save] = $btn_title_save;
                // student users should only see electronic gradeables -- NOTE: for now, we might change this design later
                if ($g_data->getType() != GradeableType::ELECTRONIC_FILE && !$this->core->getUser()->accessGrading()) {
                    continue;
                }
                // if student view false, never show
                if (!$g_data->getStudentView() && !$this->core->getUser()->accessGrading()) {
                    continue;
                }
                if ($g_data->getActiveVersion() < 1){
                    if ($title == "GRADED" || $title == "ITEMS BEING GRADED"){
                        $title = "CLOSED";
                    }
                }
                if ($g_data->useTAGrading() && $g_data->beenTAgraded() && $g_data->getUserViewedDate() !== null){
                    $title_to_button_type_submission['GRADED'] = "btn-default";
                }
                /** @var Gradeable $g_data */
                $date = new \DateTime("now", $this->core->getConfig()->getTimezone());
                if($g_data->getTAViewDate()->format('Y-m-d H:i:s') > $date->format('Y-m-d H:i:s') && !$this->core->getUser()->accessAdmin()){
                    continue;
                }
                $time = " @ H:i";
                $gradeable_grade_range = 'PREVIEW GRADING<br><span style="font-size:smaller;">(grading opens '.$g_data->getGradeStartDate()->format("m/d/Y{$time}").")</span>";
                if ($g_data->getType() == GradeableType::ELECTRONIC_FILE) {
                  if ($g_data->useTAGrading()) {
                    $gradeable_grade_range = 'PREVIEW GRADING<br><span style="font-size:smaller;">(grading opens '.$g_data->getGradeStartDate()->format("m/d/Y{$time}")."</span>)";
                  } else {
                    $gradeable_grade_range = 'VIEW SUBMISSIONS<br><span style="font-size:smaller;">(<em>no manual grading</em></span>)';
                  }
                }
                $temp_regrade_text = "";
                if ($title_save=='ITEMS BEING GRADED') {
                  $gradeable_grade_range = 'GRADE<br><span style="font-size:smaller;">(grades due '.$g_data->getGradeReleasedDate()->format("m/d/Y{$time}").'</span>)';
                  $temp_regrade_text = 'REGRADE<br><span style="font-size:smaller;">(grades due '.$g_data->getGradeReleasedDate()->format("m/d/Y{$time}").'</span>)';
                }
                if ($title_save=='GRADED') {
                  if ($g_data->getType() == GradeableType::ELECTRONIC_FILE) {
                    if ($g_data->useTAGrading()) {
                      $gradeable_grade_range = 'GRADE';
                    } else {
                      $gradeable_grade_range = 'VIEW SUBMISSIONS';
                    }
                  } else {
                    $gradeable_grade_range = 'REGRADE';
                  }
                }
                if(trim($g_data->getInstructionsURL())!=''){
                    $gradeable_title = '<label>'.$g_data->getName().'</label><a class="external" href="'.$g_data->getInstructionsURL().'" target="_blank"><i style="margin-left: 10px;" class="fa fa-external-link"></i></a>';
                }
                else{
                    if ($g_data->getType() == GradeableType::ELECTRONIC_FILE) {
                        # no_team_flag is true if there are no teams else false. Note deleting a gradeable is not allowed is no_team_flag is false.
                        $no_teams_flag=true;
                        $all_teams = $this->core->getQueries()->getTeamsByGradeableId($gradeable);
                        if (!empty($all_teams)) {
                            $no_teams_flag=false;
                        }
                        # no_submission_flag is true if there are no submissions for assignement else false. Note deleting a gradeable is not allowed is no_submission_flag is false.
                        $no_submission_flag=true;
                        $semester = $this->core->getConfig()->getSemester();
                        $course = $this->core->getConfig()->getCourse();
                        $submission_path = "/var/local/submitty/courses/".$semester."/".$course."/"."submissions/".$gradeable;
                        if(is_dir($submission_path)) {
                            $no_submission_flag=false;
                        }
                        if($this->core->getUser()->accessAdmin() && $no_submission_flag && $no_teams_flag) {
                            $form_action=$this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'delete_gradeable', 'id' => $gradeable ));
                            $gradeable_title = <<<HTML
                    <label>{$g_data->getName()}</label>&nbsp;
                    <i class="fa fa-times" style="color:red; cursor:pointer;" aria-hidden="true" onclick='newDeleteGradeableForm("{$form_action}","{$g_data->getName()}");'></i>
HTML;
                        }
                        else {
                            $gradeable_title = '<label>'.$g_data->getName().'</label>';
                        }
                    }
                    else if(($g_data->getType() == GradeableType::NUMERIC_TEXT) || (($g_data->getType() == GradeableType::CHECKPOINTS))) {
                        if($this->core->getUser()->accessAdmin() && $this->core->getQueries()->getNumUsersGraded($gradeable) === 0) {
                            $form_action=$this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'delete_gradeable', 'id' => $gradeable ));
                            $gradeable_title = <<<HTML
                    <label>{$g_data->getName()}</label>&nbsp;
                    <i class="fa fa-times" style="color:red; cursor:pointer;" aria-hidden="true" onclick='newDeleteGradeableForm("{$form_action}","{$g_data->getName()}");'></i>
HTML;
                        }
                        else {
                            $gradeable_title = '<label>'.$g_data->getName().'</label>';
                        }

                    }
                }
                if ($g_data->getType() == GradeableType::ELECTRONIC_FILE){
                    $display_date = ($title == "FUTURE" || $title == "BETA") ? "<span style=\"font-size:smaller;\">(opens ".$g_data->getOpenDate()->format("m/d/Y{$time}")."</span>)" : "<span style=\"font-size:smaller;\">(due ".$g_data->getDueDate()->format("m/d/Y{$time}")."</span>)";
                    if ($title=="GRADED" || $title=="ITEMS BEING GRADED") { $display_date = ""; }
                    if ($g_data->getActiveVersion() >= 1 && $title == "OPEN") { //if the user submitted something on time
                        $button_text = "RESUBMIT {$submission_status["SUBMITTED"]} {$submission_status["AUTOGRADE"]} {$display_date}";
                    }
                    else if($g_data->getActiveVersion() >= 1 && $title_save == "CLOSED") { //if the user submitted something past time
                        $button_text = "LATE RESUBMIT {$submission_status["SUBMITTED"]} {$submission_status["AUTOGRADE"]} {$display_date}";
                    }
                    else if(($title_save == "GRADED" || $title_save == "ITEMS BEING GRADED") && $g_data->getActiveVersion() < 1) {
                    	//to change the text to overdue submission if nothing was submitted on time
                        $button_text = "OVERDUE SUBMISSION {$submission_status["SUBMITTED"]} {$submission_status["AUTOGRADE"]} {$display_date}";
                    } //when there is no TA grade and due date passed
                    else if($title_save == "GRADED" && $g_data->useTAGrading() && !$g_data->beenTAgraded()) {
                        $button_text = "TA GRADE NOT AVAILABLE {$submission_status["SUBMITTED"]}
                        	{$submission_status["AUTOGRADE"]} {$display_date}";
                        $title_to_button_type_submission['GRADED'] = "btn-default";
                    }
                    else if($title_save == "GRADED" && !$g_data->useTAGrading()) {
                        $button_text = "{$title_to_prefix[$title]} {$submission_status["SUBMITTED"]} {$submission_status["AUTOGRADE"]} {$display_date}";
                        $title_to_button_type_submission['GRADED'] = "btn-default";
                    } // electronic gradeable with no ta grading should never be green
                    else {
                    	$button_text = "{$title_to_prefix[$title]} {$submission_status["SUBMITTED"]} {$submission_status["AUTOGRADE"]} {$display_date}";
                    }
                    if ($g_data->hasConfig()) {
                        //calculate the point percentage
                    	if ($g_data->getTotalNonHiddenNonExtraCreditPoints() == 0) {
                    		$points_percent = 0;
                    	}
                    	else {
                    		$points_percent = $g_data->getGradedNonHiddenPoints() / $g_data->getTotalNonHiddenNonExtraCreditPoints();
                    	}
						$points_percent = $points_percent * 100;
						if ($points_percent > 100) {
                            $points_percent = 100;
                        }
                        if (($g_data->isTeamAssignment() && $g_data->getTeam() === null) && (!$this->core->getUser()->accessAdmin())){
                            $gradeable_open_range = <<<HTML
                <a class="btn {$title_to_button_type_submission[$title]} btn-nav" disabled>
                     MUST BE ON A TEAM TO SUBMIT<br>{$display_date}
                </a>
HTML;
                        }
						else if ($g_data->beenAutograded() && $g_data->getTotalNonHiddenNonExtraCreditPoints() != 0 && $g_data->getActiveVersion() >= 1
							&& $title_save == "CLOSED" && $points_percent >= 50) {
						$gradeable_open_range = <<<HTML
                 <a class="btn btn-default btn-nav" href="{$site_url}&component=student&gradeable_id={$gradeable}">
                     {$button_text}
                 </a>
HTML;
						}
						else {
							$gradeable_open_range = <<<HTML
                 <a class="btn {$title_to_button_type_submission[$title]} btn-nav" href="{$site_url}&component=student&gradeable_id={$gradeable}">
                     {$button_text}
                 </a>
HTML;
						}


						//If the button is autograded and has been submitted once, give a progress bar.
						if ($g_data->beenAutograded() && $g_data->getTotalNonHiddenNonExtraCreditPoints() != 0 && $g_data->getActiveVersion() >= 1
							&& ($title_save == "CLOSED" || $title_save == "OPEN"))
						{
							//from https://css-tricks.com/css3-progress-bars/
							if ($points_percent >= 50) {
								$gradeable_open_range .= <<<HTML
								<style type="text/css">
									.meter1 {
										height: 10px;
										position: relative;
										background: rgb(224,224,224);
										padding: 0px;
									}
									.meter1 > span {
							  			display: block;
							  			height: 100%;
							  			background-color: rgb(92,184,92);
							  			position: relative;
									}
								</style>
								<div class="meter1">
  									<span style="width: {$points_percent}%"></span>
								</div>
HTML;
							}
							else {
								$gradeable_open_range .= <<<HTML
								<style type="text/css">
								.meter2 {
									height: 10px;
									position: relative;
									background: rgb(224,224,224);
									padding: 0px;
								}
								.meter2 > span {
								  	display: block;
								  	height: 100%;
								  	background-color: rgb(92,184,92);
								  	position: relative;
								}
								</style>
HTML;
                                //Give them an imaginary progress point
								if ($g_data->getGradedNonHiddenPoints() == 0) {
									$gradeable_open_range .= <<<HTML
									<div class="meter2">
	  								<span style="width: 2%"></span>
									</div>
HTML;
								}
								else {
									$gradeable_open_range .= <<<HTML
									<div class="meter2">
	  								<span style="width: {$points_percent}%"></span>
								</div>
HTML;
								}
							}
						}
                        //This code is taken from the ElectronicGraderController, it used to calculate the TA percentage.
                        $gradeable_core = $this->core->getQueries()->getGradeable($gradeable);
                        $gradeable_id = $gradeable_core->getId();
                        $total_users = array();
                        $no_team_users = array();
                        $graded_components = array();
                        $graders = array();
                        if ($gradeable_core->isGradeByRegistration()) {
                            if(!$this->core->getUser()->accessFullGrading()){
                                $sections = $this->core->getUser()->getGradingRegistrationSections();
                            }
                            else {
                                $sections = $this->core->getQueries()->getRegistrationSections();
                                foreach ($sections as $i => $section) {
                                    $sections[$i] = $section['sections_registration_id'];
                                }
                            }
                            $section_key='registration_section';
                            if (count($sections) > 0) {
                                $graders = $this->core->getQueries()->getGradersForRegistrationSections($sections);
                            }
                        }
                        else {
                            if(!$this->core->getUser()->accessFullGrading()){
                                $sections = $this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable_id, $this->core->getUser()->getId());
                            }
                            else {
                                $sections = $this->core->getQueries()->getRotatingSections();
                                foreach ($sections as $i => $section) {
                                    $sections[$i] = $section['sections_rotating_id'];
                                }
                            }
                            $section_key='rotating_section';
                            if (count($sections) > 0) {
                                $graders = $this->core->getQueries()->getGradersForRotatingSections($gradeable_id, $sections);
                            }
                        }
                        if (count($sections) > 0) {
                            if ($gradeable_core->isTeamAssignment()) {
                                $total_users = $this->core->getQueries()->getTotalTeamCountByGradingSections($gradeable_id, $sections, $section_key);
                                $no_team_users = $this->core->getQueries()->getUsersWithoutTeamByGradingSections($gradeable_id, $sections, $section_key);
                                $graded_components = $this->core->getQueries()->getGradedComponentsCountByTeamGradingSections($gradeable_id, $sections, $section_key);
                            }
                            else {
                                $total_users = $this->core->getQueries()->getTotalUserCountByGradingSections($sections, $section_key);
                                $no_team_users = array();
                                $graded_components = $this->core->getQueries()->getGradedComponentsCountByGradingSections($gradeable_id, $sections, $section_key, $gradeable_core->isTeamAssignment());
                            }
                        }

                        $num_components = $this->core->getQueries()->getTotalComponentCount($gradeable_id);
                        $num_submitted = $this->core->getQueries()->getTotalSubmittedUserCountByGradingSections($gradeable_id, $sections, $section_key);
                        $sections = array();
                        if (count($total_users) > 0) {
                            foreach ($num_submitted as $key => $value) {
                                $sections[$key] = array(
                                    'total_components' => $value * $num_components,
                                    'graded_components' => 0,
                                    'graders' => array()
                                );
                                if ($gradeable_core->isTeamAssignment()) {
                                    $sections[$key]['no_team'] = $no_team_users[$key];
                                }
                                if (isset($graded_components[$key])) {
                                    // Clamp to total components if unsubmitted assigment is graded for whatever reason
                                    $sections[$key]['graded_components'] = min(intval($graded_components[$key]), $sections[$key]['total_components']);
                                }
                                if (isset($graders[$key])) {
                                    $sections[$key]['graders'] = $graders[$key];
                                }
                            }
                        }
                        $components_graded = 0;
                        $components_total = 0;
                        foreach ($sections as $key => $section) {
                            if ($key === "NULL") {
                                continue;
                            }
                            $components_graded += $section['graded_components'];
                            $components_total += $section['total_components'];
                        }
                        $TA_percent = 0;
                        if ($components_total == 0) { $TA_percent = 0; }
                        else {
                            $TA_percent = $components_graded / $components_total;
                            $TA_percent = $TA_percent * 100;
                        }
                        //if $TA_percent is 100, change the text to REGRADE
                        if ($TA_percent == 100 && $title_save=='ITEMS BEING GRADED') {
                            $gradeable_grade_range = <<<HTML
                            <a class="btn btn-default btn-nav" \\
                            href="{$this->core->buildUrl(array('component' => 'grading', 'page' => 'electronic', 'gradeable_id' => $gradeable))}">
                            {$temp_regrade_text}</a>
HTML;
                        } else if ($TA_percent == 100 && $title_save=='GRADED') {
                            $gradeable_grade_range = <<<HTML
                            <a class="btn btn-default btn-nav" \\
                            href="{$this->core->buildUrl(array('component' => 'grading', 'page' => 'electronic', 'gradeable_id' => $gradeable))}">
                            REGRADE</a>
HTML;
                        } else {
                            $button_type = $title_to_button_type_grading[$title_save];
                            if (!$g_data->useTAGrading()) {
                              $button_type = 'btn-default';
                            }
                            $gradeable_grade_range = <<<HTML
                            <a class="btn {$button_type} btn-nav" \\
                            href="{$this->core->buildUrl(array('component' => 'grading', 'page' => 'electronic', 'gradeable_id' => $gradeable))}">
                            {$gradeable_grade_range}</a>
HTML;
                        }
                        //Give the TAs a progress bar too
                        if (($title_save == "GRADED" || $title_save == "ITEMS BEING GRADED") && $components_total != 0 && $g_data->useTAGrading()) {
                            $gradeable_grade_range .= <<<HTML
                            <style type="text/css">
                                .meter3 {
                                    height: 10px;
                                    position: relative;
                                    background: rgb(224,224,224);
                                    padding: 0px;
                                }
                                .meter3 > span {
                                    display: block;
                                    height: 100%;
                                    background-color: rgb(92,184,92);
                                    position: relative;
                                }
                            </style>
                            <div class="meter3">
                                <span style="width: {$TA_percent}%"></span>
                            </div>
HTML;
                        }
                    }
                    else {
                        $gradeable_open_range = <<<HTML
                 <button class="btn {$title_to_button_type_submission[$title]}" style="width:100%;" disabled>
                     Need to run BUILD_{$this->core->getConfig()->getCourse()}.sh
                 </button>
HTML;
                        $gradeable_grade_range = <<<HTML
                <button class="btn {$title_to_button_type_grading[$title]}" style="width:100%;" disabled>
                    Need to run BUILD_{$this->core->getConfig()->getCourse()}.sh
                </button>
HTML;
                    }
                }
                else{
                    $gradeable_open_range = '';
                    if($g_data->getType() == GradeableType::CHECKPOINTS){
                       $gradeable_grade_range = <<<HTML
                <a class="btn {$title_to_button_type_grading[$title]} btn-nav" \\
                href="{$this->core->buildUrl(array('component' => 'grading', 'page' => 'simple', 'action' => 'lab', 'g_id' => $gradeable))}">
                {$gradeable_grade_range}</a>
HTML;
                    }
                    elseif($g_data->getType() == GradeableType::NUMERIC_TEXT){
                        $gradeable_grade_range = <<<HTML
                <a class="btn {$title_to_button_type_grading[$title]} btn-nav" \\
                href="{$this->core->buildUrl(array('component' => 'grading', 'page' => 'simple', 'action' => 'numeric', 'g_id' => $gradeable))}">
                {$gradeable_grade_range}</a>
HTML;
                    }
                }
                // Team management button, only visible on team assignments
                $gradeable_team_range = '';
                if (($g_data->isTeamAssignment()) ) {
                    if ($g_data->getTeam() === null) {
                        if ($date->format('Y-m-d H:i:s') < $g_data->getTeamLockDate()->format('Y-m-d H:i:s')) {
                            $button_type = 'btn-primary';
                            $display_date = "<br><span style=\"font-size:smaller;\">(teams lock {$g_data->getTeamLockDate()->format("m/d/Y{$time}")})</span>";
                        }
                        else {
                            $button_type = 'btn-danger';
                            $display_date = '';
                        }
                        $button_text = 'CREATE TEAM';
                        $teams = $this->core->getQueries()->getTeamsByGradeableId($g_data->getId());
                        foreach($teams as $t) {
                            if ($t->sentInvite($this->core->getUser()->getId())) {
                                $button_text = 'CREATE/JOIN TEAM';
                                break;
                            }
                        }
                    }
                    else {
                        if ($date->format('Y-m-d H:i:s') < $g_data->getTeamLockDate()->format('Y-m-d H:i:s')) {
                            $button_type = 'btn-primary';
                            $display_date = "<br><span style=\"font-size:smaller;\">(teams lock {$g_data->getTeamLockDate()->format("m/d/Y{$time}")})</span>";
                            $button_text = 'MANAGE TEAM';
                        }
                        else {
                            $button_type = 'btn-default';
                            $display_date = '';
                            $button_text = 'VIEW TEAM';
                        }
                    }
                    $gradeable_team_range = <<<HTML
                <a class="btn {$button_type}" style="width:100%;"
                href="{$this->core->buildUrl(array('component' => 'student', 'gradeable_id' => $gradeable, 'page' => 'team'))}">
                {$button_text}{$display_date}
HTML;
                }
                if ($this->core->getUser()->accessAdmin()) {
                    $admin_button = <<<HTML
                <a class="btn btn-default" style="width:100%;" \\
                href="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'edit_gradeable_page', 'id' => $gradeable))}">
                    Edit
                </a>
HTML;
                }
                else {
                    $admin_button = "";
                }
                if (($this->core->getUser()->accessAdmin()) && ($g_data->getType() == GradeableType::ELECTRONIC_FILE)) {
                    $admin_rebuild_button = <<<HTML
                <a class="btn btn-default" style="width:100%;" href="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'rebuild_assignement', 'id' => $gradeable))}">
                    Rebuild
                </a>
HTML;
                }
                else {
                    $admin_rebuild_button = "";
                }
                if ($title_save === "ITEMS BEING GRADED" && $this->core->getUser()->accessAdmin()) {
                    $quick_links = <<<HTML
                        <a class="btn btn-primary" style="width:100%;" href="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'quick_link', 'id' => $gradeable, 'quick_link_action' => 'release_grades_now'))}">
                        RELEASE GRADES NOW
                        </a>
HTML;
                } else if ($title_save === "FUTURE" && $this->core->getUser()->accessAdmin()) {
                    $quick_links = <<<HTML
                        <a class="btn btn-primary" style="width:100%;" href="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'quick_link', 'id' => $gradeable, 'quick_link_action' => 'open_ta_now'))}">
                        OPEN TO TAS NOW
                        </a>
HTML;
                } else if($title_save === "BETA" && $this->core->getUser()->accessAdmin()) {
                    if($g_data->getType() == GradeableType::ELECTRONIC_FILE) {
                        $quick_links = <<<HTML
                        <a class="btn btn-primary" style="width:100%;" href="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'quick_link', 'id' => $gradeable, 'quick_link_action' => 'open_students_now'))}">
                        OPEN NOW
                        </a>
HTML;
                    } else {
                        $quick_links = <<<HTML
                        <a class="btn btn-primary" style="width:100%;" href="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'quick_link', 'id' => $gradeable, 'quick_link_action' => 'open_grading_now'))}">
                        OPEN TO GRADING NOW
                        </a>
HTML;
                    }
                } else if($title_save === "CLOSED" && $this->core->getUser()->accessAdmin()){
                    $quick_links = <<<HTML
                        <a class="btn btn-primary" style="width:100%;" href="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'quick_link', 'id' => $gradeable, 'quick_link_action' => 'open_grading_now'))}">
                        OPEN TO GRADING NOW
                        </a>
HTML;
                } else {
                    $quick_links = "";
                }
                if (!$this->core->getUser()->accessGrading() && !$g_data->getPeerGrading()) {
                    $gradeable_grade_range = "";
                }
                $return .= <<<HTML
            <tr class="gradeable_row">
                <td>{$gradeable_title}</td>
                <td style="padding: 20px;">{$gradeable_team_range}</td>
                <td style="padding: 20px;">{$gradeable_open_range}</td>
HTML;
                if (($this->core->getUser()->accessGrading() && ($this->core->getUser()->getGroup() <= $g_data->getMinimumGradingGroup())) || ($this->core->getUser()->getGroup() === 4 && $g_data->getPeerGrading())) {
                    $return .= <<<HTML
                <td style="padding: 20px;">{$gradeable_grade_range}</td>
                <td style="padding: 20px;">{$admin_button}</td>
                <td style="padding: 20px;">{$admin_rebuild_button}</td>
                <td style="padding: 20px;">{$quick_links}</td>
HTML;
                }
                $return .= <<<HTML
            </tr>
HTML;
            }
            $return .= '</tbody><tr class="colspan"><td colspan="10" style="border-bottom:2px black solid;"></td></tr>';
        }
        if ($found_assignment == false) {
            $return .= <<<HTML
    <div class="container">
    <p>There are currently no assignments posted.  Please check back later.</p>
    </div></table></div>
HTML;
            return $return;
        }
        $return .= <<<HTML
                            </table>
                        </div>
HTML;
        return $return;
    }

    public function deleteGradeableForm() {
        return $this->core->getOutput()->renderTwigTemplate("navigation/DeleteGradeableForm.twig");
    }
}
