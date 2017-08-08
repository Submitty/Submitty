<?php

namespace app\views\admin;

use app\views\AbstractView;

class AdminGradeableView extends AbstractView {
    /**
     * The one and only function that shows the entire page
     *
     * Parameters:
     * $type_of_action dedicates how the page will display the data
     * $initial_data is an array with all the data the view needs to display the page correct initially
     *   $initial_data = array($rotatingGradeables, $gradeableSectionHistory, $num_sections, $graders_all_section, $graders_from_usertypes,
     *   $template_list);
     * $data is an array with all the data the view needs to display the edit page correctly.
     * The contents of $data change depending on the gradeable type
     *   Electronic: $data = array($old_gradeable, $old_components, $has_grades, $electronic_gradeable, $initial_grades_released_compare_date, 
     *   $old_questions);
     *   Checkpoint: $data = array($old_gradeable, $old_components, $has_grades, $initial_ta_grading_compare_date, 
     *   $initial_grades_released_compare_date);
     *   Num/text: $data = array($old_gradeable, $old_components, $has_grades, $num_numeric, $num_text, $initial_ta_grading_compare_date, 
     *          $initial_grades_released_compare_date);
     */
	public function show_add_gradeable($type_of_action, $initial_data = array(""), $data = array("")) {

        $electronic_gradeable = array();
        $TA_beta_date = date('Y-m-d 23:59:59O', strtotime( '-1 days' ));
        $electronic_gradeable['eg_submission_open_date'] = date('Y-m-d 23:59:59O', strtotime( '0 days' ));
        $electronic_gradeable['eg_submission_due_date'] = date('Y-m-d 23:59:59O', strtotime( '+7 days' ));
        $electronic_gradeable['eg_subdirectory'] = "";
        $electronic_gradeable['eg_config_path'] = "";
        $electronic_gradeable['eg_late_days'] = 2;
        $electronic_gradeable['eg_precision'] = 0.5;
        $electronic_gradeable['eg_max_team_size'] = 1;
        $electronic_gradeable['eg_team_lock_date'] = date('Y-m-d 23:59:59O', strtotime( '+7 days' ));
        $team_yes_checked = false;
        $team_no_checked = true;
        $peer_yes_checked = false;
        $peer_no_checked = true;
        $peer_grade_set = 3;
        $TA_grade_open_date = date('Y-m-d 23:59:59O', strtotime( '+10 days' ));
        $TA_grade_release_date = date('Y-m-d 23:59:59O', strtotime( '+14 days' ));
        $default_late_days = $this->core->getConfig()->getDefaultHwLateDays();
        $vcs_base_url = ($this->core->getConfig()->getVcsBaseUrl() !== "") ? $this->core->getConfig()->getVcsBaseUrl() : "None specified.";
        $BASE_URL = "http:/localhost/hwgrading";
        $action = "upload_new_gradeable"; //decides how the page's data is displayed
        $string = "Add"; //Add or Edit
        $button_string = "Add";
        $extra = "";
        $gradeable_submission_id = "";
        $gradeable_name = "";
        $g_instructions_url = "";
        $g_gradeable_type = 0;
        $is_repository = false;
        $use_ta_grading = false;
        $student_view = true;
        $student_submit = true;
        $student_download = false;
        $student_any_version = true;
        $pdf_page = false;
        $pdf_page_student = false;
        $old_questions = array();
        $g_min_grading_group = 0;
        $g_overall_ta_instructions = "";
        $have_old = false;
        $old_components = array();
        $old_components = "{}";
        $num_numeric = $num_text = 0;
        $g_syllabus_bucket = -1;
        $g_grade_by_registration = -1;
        $edit = json_encode($type_of_action === "edit");
        $template_value = "";
        $precision = 0.5;
        $electronic_gradeable['eg_precision'] = $precision;
        $gradeable_id_title = $initial_data[5]; //list of previous gradeables
        $gradeables_array = array();

        foreach ($gradeable_id_title as $g_id_title) { //makes an array of gradeable ids for javascript
            array_push($gradeables_array, $g_id_title['g_id']);
        }
        $js_gradeables_array = json_encode($gradeables_array);

        //if the user is editing a gradeable instead of adding
        if ($type_of_action === "edit") {
            $have_old = true;
            $action = "upload_edit_gradeable";
            $string = "Edit";
            $button_string = "Edit";
            $extra = ($data[2]) ? "<span style='color: red;'>(Grading has started! Edit Questions At Own Peril!)</span>" : "";
            $TA_beta_date = date('Y-m-d H:i:sO', strtotime($data[0]['g_ta_view_start_date']));
            $TA_grade_open_date = date('Y-m-d H:i:sO', strtotime($data[0]['g_grade_start_date']));
            $TA_grade_release_date = date('Y-m-d H:i:sO', strtotime($data[0]['g_grade_released_date']));
            $gradeable_submission_id = $data[0]['g_id'];
            $gradeable_name = $data[0]['g_title'];
            $g_instructions_url = $data[0]['g_instructions_url'];
            $g_overall_ta_instructions = $data[0]['g_overall_ta_instructions'];
            $old_components = $data[1];
            $g_min_grading_group = $data[0]['g_min_grading_group'];
            $g_syllabus_bucket = $data[0]['g_syllabus_bucket'];
            $g_grade_by_registration = $data[0]['g_grade_by_registration'];
            if ($data[0]['g_gradeable_type'] === 0) { //if the gradeable edited is electronic gradeable
                $electronic_gradeable['eg_submission_open_date'] = date('Y-m-d H:i:sO', strtotime($data[3]['eg_submission_open_date']));
                $electronic_gradeable['eg_submission_due_date'] = date('Y-m-d H:i:sO', strtotime($data[3]['eg_submission_due_date']));
                $electronic_gradeable['eg_late_days'] = $data[3]['eg_late_days'];
                $electronic_gradeable['eg_subdirectory'] = $data[3]['eg_subdirectory'];
                $electronic_gradeable['eg_config_path'] = $data[3]['eg_config_path'];
                $precision = $data[3]['eg_precision'];
                $electronic_gradeable['eg_precision'] = $precision;
                $electronic_gradeable['eg_max_team_size'] = $data[3]['eg_max_team_size'];
                $electronic_gradeable['eg_team_lock_date'] = date('Y-m-d H:i:sO', strtotime($data[3]['eg_team_lock_date']));
                $team_yes_checked = $data[3]['eg_team_assignment'];
                $team_no_checked = !$team_yes_checked;
                $is_repository = $data[3]['eg_is_repository'];
                $use_ta_grading = $data[3]['eg_use_ta_grading'];
                $student_view = $data[3]['eg_student_view'];
                $student_submit = $data[3]['eg_student_submit'];
                $student_download = $data[3]['eg_student_download'];
                $student_any_version = $data[3]['eg_student_any_version'];
                $peer_yes_checked = $data[3]['eg_peer_grading'];
                $peer_no_checked = !$peer_yes_checked;
                if(isset($data[3]['eg_peer_grade_set'])){
                    $peer_grade_set = $data[3]['eg_peer_grade_set'];
                }
                $old_questions = $data[5];
                $num_old_questions = count($old_questions);                
                $component_ids = array();
                for ($i = 0; $i < $num_old_questions; $i++) {
                    $json = json_decode($data[1]);
                    $component_ids[] = $json[$i]->gc_id;
                    if (($json[$i]->gc_page) !== 0) {
                        $pdf_page = true;
                        if (($json[$i]->gc_page) === -1) {
                            $pdf_page_student = true;
                        }
                    }
                }
            }
            if ($data[0]['g_gradeable_type'] === 2) { //if the gradeable edited is num/text gradeable
                $num_numeric = $data[3];
                $num_text = $data[4];
            }
        }

        //if the user is using a template
        if ($type_of_action === "add_template") {
            $g_instructions_url = $data[0]['g_instructions_url'];
            $g_overall_ta_instructions = $data[0]['g_overall_ta_instructions'];
            $old_components = $data[1];
            $g_min_grading_group = $data[0]['g_min_grading_group'];
            $g_syllabus_bucket = $data[0]['g_syllabus_bucket'];
            $g_grade_by_registration = $data[0]['g_grade_by_registration'];
            if ($data[0]['g_gradeable_type'] === 0) {
                $electronic_gradeable['eg_subdirectory'] = $data[3]['eg_subdirectory'];
                $electronic_gradeable['eg_config_path'] = $data[3]['eg_config_path'];
                $electronic_gradeable['eg_max_team_size'] = $data[3]['eg_max_team_size'];
                $team_yes_checked = $data[3]['eg_team_assignment'];
                $team_no_checked = !$team_yes_checked;
                $use_ta_grading = $data[3]['eg_use_ta_grading'];
                $student_view = $data[3]['eg_student_view'];
                $student_submit = $data[3]['eg_student_submit'];
                $student_download = $data[3]['eg_student_download'];
                $student_any_version = $data[3]['eg_student_any_version'];
                $peer_yes_checked = $data[3]['eg_peer_grading'];
                $peer_no_checked = !$peer_yes_checked;
                $peer_grade_set = $data[3]['eg_peer_grade_set'];
                $precision = $data[3]['eg_precision'];
                $electronic_gradeable['eg_precision'] = $precision;
                $old_questions = $data[5];
                $num_old_questions = count($old_questions);                
                $component_ids = array();
                for ($i = 0; $i < $num_old_questions; $i++) {
                    $json = json_decode($data[1]);
                    $component_ids[] = $json[$i]->gc_id;
                    if (($json[$i]->gc_page) !== 0) {
                        $pdf_page = true;
                        if (($json[$i]->gc_page) === -1) {
                            $pdf_page_student = true;
                        }
                    }
                }
            }
            if ($data[0]['g_gradeable_type'] === 2) {
                $num_numeric = $data[3];
                $num_text = $data[4];
            }
        }

		$html_output = <<<HTML
		<style type="text/css">

    body {
        overflow: scroll;
    }

    select {
        margin-top:7px;
        width: 60px;
        min-width: 60px;
    }

    #container-rubric {
        width:1200px;
        margin:100px auto;
        margin-top: 130px;
        background-color: #fff;
        border: 1px solid #999;
        border: 1px solid rgba(0,0,0,0.3);
        -webkit-border-radius: 6px;
        -moz-border-radius: 6px;
        border-radius: 6px;outline: 0;
        -webkit-box-shadow: 0 3px 7px rgba(0,0,0,0.3);
        -moz-box-shadow: 0 3px 7px rgba(0,0,0,0.3);
        box-shadow: 0 3px 7px rgba(0,0,0,0.3);
        -webkit-background-clip: padding-box;
        -moz-background-clip: padding-box;
        background-clip: padding-box;
        padding-top: 20px;
        padding-right: 20px;
        padding-left: 20px;
        padding-bottom: 20px;
    }

    .question-icon {
        display: block;
        float: left;
        margin-top: 5px;
        margin-left: 5px;
        position: relative;
        overflow: hidden;
    }

    .question-icon-cross {
        max-width: none;
        position: absolute;
        top:0;
        left:-313px;
    }

    .question-icon-up {
        max-width: none;
        position: absolute;
        top: -96px;
        left: -290px;
    }

    .question-icon-down {
        max-width: none;
        position: absolute;
        top: -96px;
        left: -313px;
    }

    .ui_tpicker_unit_hide {
        display: none;
    }
    
    /* align the radio, buttons and checkboxes with labels */
    input[type="radio"],input[type="checkbox"] {
        margin-top: -1px;
        vertical-align: middle;
    }
    
    fieldset {
        margin: 8px;
        border: 1px solid silver;
        padding: 8px;    
        border-radius: 4px;
    }
    
    legend{
        padding: 2px;  
        font-size: 12pt;
    }
        
</style>
<div id="container-rubric">
    <form id="gradeable-form" class="form-signin" action="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => $action))}" 
          method="post" enctype="multipart/form-data" onsubmit="return checkForm();"> 

        <div class="modal-header" style="overflow: auto;">
            <h3 id="myModalLabel" style="float: left;">{$string} Gradeable {$extra}</h3>
HTML;
if ($type_of_action === "add" || $type_of_action === "add_template"){
  $html_output .= <<<HTML
            <div style="padding-left: 200px;">
                From Template: <select name="gradeable_template" style='width: 170px;' value=''>
            </div>
            <option>--None--</option>
HTML;

    foreach ($gradeable_id_title as $g_id_title){
     $html_output .= <<<HTML
        <option 
HTML;
        if ($type_of_action === "add_template" && $data[0]['g_id']===$g_id_title['g_id']) { $html_output .= "selected"; }
        $html_output .= <<<HTML
        value="{$g_id_title['g_id']}">{$g_id_title['g_title']}</option>
HTML;
    }
  $html_output .= <<<HTML
          </select>          
HTML;
}
  $html_output .= <<<HTML
            <button class="btn btn-primary" type="submit" style="margin-right:10px; float: right;">{$button_string} Gradeable</button>
HTML;
    $html_output .= <<<HTML
        </div>

<div class="modal-body">
<b>Please Read: <a target=_blank href="http://submitty.org/instructor/create_edit_gradeable">Submitty Instructions on "Create or Edit a Gradeable"</a></b>
</div>

		<div class="modal-body" style="/*padding-bottom:80px;*/ overflow:visible;">
HTML;
if ($type_of_action === "edit"){
    $html_output .= <<<HTML
            What is the unique id of this gradeable? (e.g., <kbd>hw01</kbd>, <kbd>lab_12</kbd>, or <kbd>midterm</kbd>): <input style='width: 200px; background-color: #999999' type='text' name='gradeable_id' id="gradeable_id" class="required" value="{$gradeable_submission_id}" placeholder="(Required)"/>
HTML;
}
else {
    $html_output .= <<<HTML
            What is the unique id of this gradeable? (e.g., <kbd>hw01</kbd>, <kbd>lab_12</kbd>, or <kbd>midterm</kbd>): <input style='width: 200px' type='text' name='gradeable_id' id="gradeable_id" class="required" value="{$gradeable_submission_id}" placeholder="(Required)" required/>
HTML;
}
        $html_output .= <<<HTML
            <br />
            What is the title of this gradeable?: <input style='width: 227px' type='text' name='gradeable_title' id='gradeable_title_id' class="required" value="{$gradeable_name}" placeholder="(Required)" required/>
            <br />
            What is the URL to the assignment instructions? (shown to student) <input style='width: 227px' type='text' name='instructions_url' value="{$g_instructions_url}" placeholder="(Optional)" />
            <br />
            What is the <em style='color: orange;'><b>TA Beta Testing Date</b></em>? (gradeable visible to TAs):
            <input name="date_ta_view" id="date_ta_view" class="date_picker" type="text" value="{$TA_beta_date}"
            style="cursor: auto; background-color: #FFF; width: 250px;">
            <br />
            <br /> 
            What is the <a target=_blank href="http://submitty.org/instructor/create_edit_gradeable#types-of-gradeables">type of the gradeable</a>?: <div id="required_type" style="color:red; display:inline;">(Required)</div>

            <fieldset>
                <input type='radio' id="radio_electronic_file" class="electronic_file" name="gradeable_type" value="Electronic File"
HTML;
    if (($type_of_action === "edit" || $type_of_action === "add_template") && $data[0]['g_gradeable_type']===0) { $html_output .= ' checked="checked"'; }
    $html_output .= <<<HTML
            > 
            Electronic File
            <input type='radio' id="radio_checkpoints" class="checkpoints" name="gradeable_type" value="Checkpoints"
HTML;
            if (($type_of_action === "edit" || $type_of_action === "add_template") && $data[0]['g_gradeable_type']===1) { $html_output .= ' checked="checked"'; }
    $html_output .= <<<HTML
            >
            Checkpoints
            <input type='radio' id="radio_numeric" class="numeric" name="gradeable_type" value="Numeric"
HTML;
            if (($type_of_action === "edit" || $type_of_action === "add_template") && $data[0]['g_gradeable_type']===2) { $html_output .= ' checked="checked"'; }
    $html_output .= <<<HTML
            >
            Numeric/Text
            <!-- This is only relevant to Electronic Files -->
            <div class="gradeable_type_options electronic_file" id="electronic_file" >
                <br />
                Is this a team assignment?
                <fieldset>
                    <input type="radio" id = "team_yes_radio" class="team_yes" name="team_assignment" value="true"
HTML;
                if (($type_of_action === "edit" || $type_of_action === "add_template") && $team_yes_checked) { $html_output .= ' checked="checked"'; }
                $html_output .= <<<HTML
                > Yes
                    <input type="radio" id = "team_no_radio" class="team_no" name="team_assignment" value ="false"
HTML;
                if ((($type_of_action === "edit" || $type_of_action === "add_template") && $team_no_checked) || $type_of_action === "add") { $html_output .= ' checked="checked"'; }
                $html_output .= <<<HTML
                > No
                    <div class="team_assignment team_yes" id="team_yes">
                        <br />
                        What is the maximum team size? <input style="width: 50px" name="eg_max_team_size" class="int_val" type="text" value="{$electronic_gradeable['eg_max_team_size']}"/>
                        <br />
                        What is the <em style='color: orange;'><b>Team Lock Date</b></em>? (Instructors can still manually manage teams):
                        <input name="date_team_lock" id="date_team_lock" class="date_picker" type="text" value="{$electronic_gradeable['eg_team_lock_date']}"
                        style="cursor: auto; background-color: #FFF; width: 250px;">
                        <br />
                    </div>
                    <div class="team_assignment team_no" id="team_no"></div>
                </fieldset>      
                <br />
                What is the <em style='color: orange;'><b>Submission Open Date</b></em>? (submission available to students):
                <input id="date_submit" name="date_submit" class="date_picker" type="text" value="{$electronic_gradeable['eg_submission_open_date']}"
                style="cursor: auto; background-color: #FFF; width: 250px;">
                <em style='color: orange;'>must be >= TA Beta Testing Date</em>
                <br />

                What is the <em style='color: orange;'><b>Due Date</b></em>?
                <input id="date_due" name="date_due" class="date_picker" type="text" value="{$electronic_gradeable['eg_submission_due_date']}"
                style="cursor: auto; background-color: #FFF; width: 250px;">
                <em style='color: orange;'>must be >= Submission Open Date</em>
                <br />

                How many late days may students use on this assignment? <input style="width: 50px" name="eg_late_days" class="int_val"
                                                                         type="text"/>
                <br /> <br />

                Are students uploading files or submitting to a Version Control System (VCS) repository?<br />
                <fieldset>

                    <input type="radio" id="upload_file_radio" class="upload_file" name="upload_type" value="upload_file"
HTML;
                    if ($is_repository === false) { $html_output .= ' checked="checked"'; }

                $html_output .= <<<HTML
                    > Upload File(s)

                    <input type="radio" id="repository_radio" class="upload_repo" name="upload_type" value="repository"
HTML;
                    if ($is_repository === true) { $html_output .= ' checked="checked"'; }
                $html_output .= <<<HTML
                    > Version Control System (VCS) Repository
                      
                    <div class="upload_type upload_file" id="upload_file"></div>
                     
                    <div class="upload_type upload_repo" id="repository">
                        <br />
                        <b>Path for the Version Control System (VCS) repository:</b><br />
                        VCS base URL: <kbd>{$vcs_base_url}</kbd><br />
                        The VCS base URL is configured in Course Settings. If there is a base URL, you can define the rest of the path below. If there is no base URL because the entire path changes for each assignment, you can input the full path below. If the entire URL is decided by the student, you can leave this input blank.<br />
                        You are allowed to use the following string replacement variables in format $&#123;&hellip;&#125;<br />
                        <ul style="list-style-position: inside;">
                            <li>gradeable_id</li>
                            <li>user_id OR repo_id, do not use both</li>
                        </ul>
                        ex. <kbd>/&#123;&#36;gradeable_id&#125;/&#123;&#36;user_id&#125;</kbd> or <kbd>https://github.com/test-course/&#123;&#36;gradeable_id&#125;/&#123;&#36;repo_id&#125;</kbd><br />
                        <input style='width: 83%' type='text' name='subdirectory' value="" placeholder="(Optional)"/>
                        <br />
                    </div>
                    
                </fieldset>

		<br />
                <b>Full path to the directory containing the autograding config.json file:</b><br>
                See samples here: <a target=_blank href="https://github.com/Submitty/Tutorial/tree/master/examples">Submitty GitHub sample assignment configurations</a><br>
		<kbd>/usr/local/submitty/more_autograding_examples/upload_only/config</kbd>  (an assignment without autograding)<br>
		<kbd>/var/local/submitty/private_course_repositories/MY_COURSE_NAME/MY_HOMEWORK_NAME/</kbd> (for a custom autograded homework)<br>
		<kbd>/var/local/submitty/courses/{$_GET['semester']}/{$_GET['course']}/config_upload/#</kbd> (for an web uploaded configuration)<br>

                <input style='width: 83%' type='text' name='config_path' value="" class="required" placeholder="(Required)" />
                <br /> <br />

                Should students be able to view submissions?
                <fieldset>
                    <input type="radio" id="yes_student_view" name="student_view" value="true"
HTML;
                    if ($student_view===true) { $html_output .= ' checked="checked"'; }
        $html_output .= <<<HTML
                    /> Yes
                    <input type="radio" id="no_student_view" name="student_view" value="false"
HTML;
                    if ($student_view===false) { $html_output .= ' checked="checked"'; }
        $html_output .= <<<HTML
                    /> No 

                    <div id="student_submit_download_view">

                        <br />
                        Should students be able to make submissions? (Select 'No' if this is a bulk upload pdf quiz/exam.)
                        <input type="radio" id="yes_student_submit" name="student_submit" value="true" 
HTML;
                        if ($student_submit===true) { $html_output .= ' checked="checked"'; }
        $html_output .= <<<HTML
                        /> Yes
                        <input type="radio" id="no_student_submit" name="student_submit" value="false"
HTML;
                        if ($student_submit===false) { $html_output .= ' checked="checked"'; }
        $html_output .= <<<HTML
                        /> No 
                        <br /> <br />

                        Should students be able to download files? (Select 'Yes' to allow download of uploaded pdf quiz/exam.)
                        <input type="radio" id="yes_student_download" name="student_download" value="true"
HTML;
                        if ($student_download===true) { $html_output .= ' checked="checked"'; }
        $html_output .= <<<HTML
                        /> Yes
                        <input type="radio" id="no_student_download" name="student_download" value="false"
HTML;
                        if ($student_download===false) { $html_output .= ' checked="checked"'; }
        $html_output .= <<<HTML
                        /> No
                        <br /> <br />

                        Should students be view/download any or all versions? (Select 'Active version only' if this is an uploaded pdf quiz/exam.)
                        <input type="radio" id="yes_student_any_version" name="student_any_version" value="true"
HTML;
                        if ($student_any_version===true) { $html_output .= ' checked="checked"'; }
        $html_output .= <<<HTML
                        /> Any version
                        <input type="radio" id="no_student_any_version" name="student_any_version" value="false"
HTML;
                        if ($student_any_version===false) { $html_output .= ' checked="checked"'; }
        $html_output .= <<<HTML
                        /> Active version only

                    </div>
                </fieldset>
                <br />

          Will any or all of this assignment be manually graded (e.g., by TAs or the instructor)?
                <input type="radio" id="yes_ta_grade" name="ta_grading" value="true" class="bool_val rubric_questions"
HTML;
                if ($use_ta_grading===true) { $html_output .= ' checked="checked"'; }
        $html_output .= <<<HTML
                /> Yes
                <input type="radio" id="no_ta_grade" name="ta_grading" value="false"
HTML;
                if ($use_ta_grading===false) { $html_output .= ' checked="checked"'; }
        $html_output .= <<<HTML
                /> No 
                <br /><br />
                
                <div id="rubric_questions" class="bool_val rubric_questions">
                Will this assignment have peer grading?: 
                <fieldset>
                    <input type="radio" id="peer_yes_radio" name="peer_grading" value="true" class="peer_yes"
HTML;
        $display_peer_checkboxes = "";
                    if(($type_of_action === "edit" || $type_of_action === "add_template") && $peer_yes_checked) { $html_output .= ' checked="checked"'; }
        $html_output .= <<<HTML
                    /> Yes
                    <input type="radio" id="peer_no_radio" name="peer_grading" value="false" class="peer_no"
HTML;
                    if ((($type_of_action === "edit" || $type_of_action === "add_template") && $peer_no_checked) || $type_of_action === "add") {
                        $html_output .= ' checked="checked"';
                        $display_peer_checkboxes = 'style="display:none"';
                    }
        $display_pdf_page_input = "";
        $html_output .= <<<HTML
                    /> No
                    <div class="peer_input" style="display:none;">
                        <br />
                        How many peers should each student grade?
                        <input style='width: 50px' type='text' name="peer_grade_set" value="{$peer_grade_set}" class='int_val' />
                    </div>
                </fieldset>
                <br />

                Is this a PDF with a page assigned to each component?
                <fieldset>
                    <input type="radio" id="yes_pdf_page" name="pdf_page" value="true" 
HTML;
                    if ($pdf_page===true) { $html_output .= ' checked="checked"'; }
        $html_output .= <<<HTML
                    /> Yes
                    <input type="radio" id="no_pdf_page" name="pdf_page" value="false"
HTML;
                    if ($pdf_page===false) { 
                        $html_output .= ' checked="checked"';
                        $display_pdf_page_input = 'style="display:none"';
                    }
        $html_output .= <<<HTML
                    /> No 

                    <div id="pdf_page">
                        <br />
                        Who will assign pages to components?
                        <input type="radio" id="no_pdf_page_student" name="pdf_page_student" value="false"
HTML;
                        if ($pdf_page_student===false) { $html_output .= ' checked="checked"'; }
        $html_output .= <<<HTML
                        /> Instructor
                        <input type="radio" id="yes_pdf_page_student" name="pdf_page_student" value="true"
HTML;
                        if ($pdf_page_student===true) {
                            $html_output .= ' checked="checked"';
                            $display_pdf_page_input = 'style="display:none"';
                        }
        $html_output .= <<<HTML
                        /> Student
                    </div>

                </fieldset>
                <br />

                Point precision (for manual grading): 
                <input style='width: 50px' type='text' id="point_precision_id" name='point_precision' onchange="fixPointPrecision(this);" value="{$precision}" class="float_val" />
                <br /><br />
                
                <table class="table table-bordered" id="rubricTable" style=" border: 1px solid #AAA;">
                    <thead style="background: #E1E1E1;">
                        <tr>
                            <th>Manual/TA/Peer Grading Rubric</th>
                            <th style="width:120px;">Points</th>
                        </tr>
                    </thead>
                    <tbody style="background: #f9f9f9;">
HTML;

    if (count($old_questions) == 0) {
        $old_questions[0] = array('question_message'      => "",
                                  'question_grading_note' => "",
                                  'student_grading_note'  => "",
                                  'question_total'        => 0,
                                  'question_extra_credit' => 0,
                                  'peer_component'        => 0,
                                  'page_component'        => 1);
    }


    

    //this is a hack
    array_unshift($old_questions, "tmp");
    $index_question = 0;
    foreach ($old_questions as $num => $question) {
        if($num == 0) continue;
        $type_deduct = 0;
        $html_output .= <<<HTML
            <tr class="rubric-row" id="row-{$num}">
HTML;
        $html_output .= <<<HTML
                <td style="overflow: hidden;">
                    <textarea name="comment_title_{$num}" rows="1" class="comment_title complex_type" style="width: 99%; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-right: 1px; height: auto;" 
                              placeholder="Rubric Item Title">{$question['question_message']}</textarea>
                    <textarea name="ta_comment_{$num}" id="individual_{$num}" class="ta_comment complex_type" rows="1" placeholder=" Message to TA (seen only by TAs)"  onkeyup="autoResizeComment(event);"
                                               style="width: 99%; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-bottom: 5px; 
                                               display: block; height: auto;">{$question['question_grading_note']}</textarea>
                    <textarea name="student_comment_{$num}" id="student_{$num}" class="student_comment complex_type" rows="1" placeholder=" Message to Student (seen by both students and TAs)" onkeyup="autoResizeComment(event);"
                              style="width: 99%; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-bottom: 5px; 
                              display: block; height: auto;">{$question['student_grading_note']}</textarea>
                    <div id="deduction_questions_{$num}">
HTML;
    if(!($type_of_action === "edit" || $type_of_action === "add_template")) {
        $html_output .= <<<HTML
            <div id="deduct_id-{$num}-0" name="deduct_{$num}" style="text-align: left; font-size: 8px; padding-left: 5px; display: none;">
            <i class="fa fa-circle" aria-hidden="true"></i> <input type="number" class="points2" name="deduct_points_{$num}_0" value="0" step="0.5" placeholder="±0.5" style="width:50px; resize:none; margin: 5px;"> 
            <textarea rows="1" placeholder="Comment" name="deduct_text_{$num}_0" style="resize: none; width: 81.5%;">Full Credit</textarea> 
            <a onclick="deleteDeduct(this)"> <i class="fa fa-times" aria-hidden="true" style="font-size: 16px; margin: 5px;"></i></a> 
            <a onclick="moveDeductDown(this)"> <i class="fa fa-arrow-down" aria-hidden="true" style="font-size: 16px; margin: 5px;"></i></a> 
            <a onclick="moveDeductUp(this)"> <i class="fa fa-arrow-up" aria-hidden="true" style="font-size: 16px; margin: 5px;"></i></a> 
            <br> 
        </div>
HTML;
    }
    if (($type_of_action === "edit" || $type_of_action === "add_template") && $data[0]['g_gradeable_type'] === 0 && $use_ta_grading === true) {
        $type_deduct = 0;
        $marks = $this->core->getQueries()->getGradeableComponentsMarks($component_ids[$index_question]);
        foreach ($marks as $mark) {
            if ($mark->getPoints() > 0) {
                $type_deduct = 1;
            }
        }
        $first = true;
        foreach ($marks as $mark) {
            if($first === true) {
                $first = false;
                $hidden = "display: none;";
            }
            else {
                $hidden = "";
            }
            if ($type_deduct === 1) {
                $min = 0;
                $max = 1000;
            }
            else {
                $min = -1000;
                $max = 0;
            }
            $html_output .= <<<HTML
                <div id="deduct_id-{$num}-{$mark->getOrder()}" name="deduct_{$num}" style="text-align: left; font-size: 8px; padding-left: 5px; {$hidden}">
                <i class="fa fa-circle" aria-hidden="true"></i> <input type="number" onchange="fixMarkPointValue(this);" class="points2" name="deduct_points_{$num}_{$mark->getOrder()}" value="{$mark->getPoints()}" min="{$min}" max="{$max}" step="0.5" placeholder="±0.5" style="width:50px; resize:none; margin: 5px;"> 
                <textarea rows="1" placeholder="Comment" name="deduct_text_{$num}_{$mark->getOrder()}" style="resize: none; width: 81.5%;">{$mark->getNote()}</textarea> 
                <a onclick="deleteDeduct(this)"> <i class="fa fa-times" aria-hidden="true" style="font-size: 16px; margin: 5px;"></i></a> 
                <a onclick="moveDeductDown(this)"> <i class="fa fa-arrow-down" aria-hidden="true" style="font-size: 16px; margin: 5px;"></i></a> 
                <a onclick="moveDeductUp(this)"> <i class="fa fa-arrow-up" aria-hidden="true" style="font-size: 16px; margin: 5px;"></i></a> 
                <br> 
            </div>
HTML;
        }
    }
        $html_output .= <<<HTML
                    <div class="btn btn-xs btn-primary" id="rubric_add_deduct_{$num}" onclick="addDeduct(this,{$num});" style="overflow: hidden; text-align: left;float: left;">Add Common Deduction/Addition</div></div>
                </td>

                <td style="background-color:#EEE;">
HTML;
        $old_grade = (isset($question['question_total'])) ? $question['question_total'] : 0;
        $html_output .= <<<HTML
        <input type="number" id="grade-{$num}" class="points" name="points_{$num}" value="{$old_grade}" max="1000" step="{$precision}" placeholder="±0.5" onchange="calculatePercentageTotal();" style="width:50px; resize:none;">
HTML;
        $checked = ($question['question_extra_credit']) ? "checked" : "";
        if ($type_deduct === 1) {
            $ded_checked = "";
            $add_checked = "checked";
        }
        else {
            $ded_checked = "checked";
            $add_checked = "";
        }
        $peer_checked = ($question['peer_component']) ? ' checked="checked"' : "";
        $pdf_page = (isset($question['page_component'])) ? $question['page_component'] : 1;
        $html_output .= <<<HTML
                <br />
                Extra Credit:&nbsp;&nbsp;<input onclick='calculatePercentageTotal();' name="eg_extra_{$num}" type="checkbox" class='eg_extra extra' value='on' {$checked}/>
                Deduction/Addition:&nbsp;&nbsp;<input type="radio" id="deduct_radio_ded_id_{$num}" name="deduct_radio_{$num}" value="deduction" onclick="onDeduction(this);" {$ded_checked}> <i class="fa fa-minus-square" aria-hidden="true"> </i>
                <input type="radio" id="deduct_radio_add_id_{$num}" name="deduct_radio_{$num}" value="addition" onclick="onAddition(this);" {$add_checked}> <i class="fa fa-plus-square" aria-hidden="true"> </i>
                <br />
                <div id="peer_checkbox_{$num}" class="peer_input" {$display_peer_checkboxes}>Peer Component:&nbsp;&nbsp;<input type="checkbox" name="peer_component_{$num}" value="on" class="peer_component" {$peer_checked} /></div>
                <div id="pdf_page_{$num}" class="pdf_page_input" {$display_pdf_page_input}>Page:&nbsp;&nbsp;<input type="number" name="page_component_{$num}" value={$pdf_page} class="page_component" max="1000" step="1" style="width:50px; resize:none;" /></div>
HTML;
        if ($num > 1){
        $html_output .= <<<HTML
                <a id="delete-{$num}" class="question-icon" onclick="deleteQuestion({$num});">
                <i class="fa fa-times" aria-hidden="true"></i></a>
                <a id="down-{$num}" class="question-icon" onclick="moveQuestionDown({$num});">
                <i class="fa fa-arrow-down" aria-hidden="true"></i></a>       
                <a id="up-{$num}" class="question-icon" onclick="moveQuestionUp({$num});">
                <i class="fa fa-arrow-up" aria-hidden="true"></i></a>
HTML;
        }
        
        $html_output .= <<<HTML
                </td>
            </tr>
HTML;
        $index_question++;
    }
        $html_output .= <<<HTML
            <tr id="add-question">
                <td colspan="2" style="overflow: hidden; text-align: left;">
                    <div class="btn btn-small btn-success" id="rubric-add-button" onclick="addQuestion()"><i class="fa fa-plus-circle" aria-hidden="true"></i> Rubric Item</div>
                </td>
            </tr>
HTML;
        $html_output .= <<<HTML
                    <tr>
                        <td style="background-color: #EEE; border-top: 2px solid #CCC; border-left: 1px solid #EEE;"><strong>TOTAL POINTS</strong></td>
                        <td style="background-color: #EEE; border-top: 2px solid #CCC;"><strong id="totalCalculation"></strong></td>
                    </tr>
                </tbody>
            </table>
            </div>
HTML;
    $html_output .= <<<HTML
            </div>
            <div class="gradeable_type_options checkpoints" id="checkpoints">
                <br />
                <div class="multi-field-wrapper-checkpoints">
                  <table class="checkpoints-table table table-bordered" style=" border: 1px solid #AAA; max-width:50% !important;">
                        <!-- Headings -->
                        <thead style="background: #E1E1E1;">
                             <tr>
                                <th> Label </th>
                                <th> Extra Credit? </th>
                            </tr>
                        </thead>
                        <tbody style="background: #f9f9f9;">
                      
                        <!-- This is a bit of a hack, but it works (^_^) -->
                        <tr class="multi-field" id="mult-field-0" style="display:none;">
                           <td>
                               <input style="width: 200px" name="checkpoint_label_0" type="text" class="checkpoint_label" value="Checkpoint 0"/> 
                           </td>     
                           <td>     
                                <input type="checkbox" name="checkpoint_extra_0" class="checkpoint_extra extra" value="true" />
                           </td> 
                        </tr>
                      
                       <tr class="multi-field" id="mult-field-1">
                           <td>
                               <input style="width: 200px" name="checkpoint_label_1" type="text" class="checkpoint_label" value="Checkpoint 1"/> 
                           </td>     
                           <td>     
                                <input type="checkbox" name="checkpoint_extra_1" class="checkpoint_extra extra" value="true" />
                           </td> 
                        </tr>
                  </table>
                  <button type="button" id="add-checkpoint_field">Add </button>  
                  <button type="button" id="remove-checkpoint_field" id="remove-checkpoint" style="visibilty:hidden;">Remove</button>   
                </div> 
                <br />
                <!--Do you want a box for an (optional) message from the TA to the student?
                <input type="radio" name="checkpoint_opt_ta_messg" value="yes" /> Yes
                <input type="radio" name="checkpoint_opt_ta_messg" value="no" /> No-->
            </div>
            <div class="gradeable_type_options numeric" id="numeric">
                <br />
                How many numeric items? <input style="width: 50px" id="numeric_num-items" name="num_numeric_items" type="text" value="0" class="int_val" onchange="calculateTotalScore();"/> 
                &emsp;&emsp;
                
                How many text items? <input style="width: 50px" id="numeric_num_text_items" name="num_text_items" type="text" value="0" class="int_val"/>
                <br /> <br />
                
                <div class="multi-field-wrapper-numeric">
                    <h5>Numeric Items</h5>
                    <table class="numerics-table table table-bordered" style=" border: 1px solid #AAA; max-width:50% !important;">
                        <!-- Headings -->
                        <thead style="background: #E1E1E1;">
                             <tr>
                                <th> Label </th>
                                <th> Max Score </th>
                                <th> Extra Credit?</th>
                            </tr>
                        </thead>
                        <!-- Footers -->
                        <tfoot style="background: #E1E1E1;">
                            <tr>
                                <td><strong> MAX SCORE </strong></td>
                                <td><strong id="totalScore"></strong></td>
                                <td><strong id="totalEC"></strong></td>
                            </tr>
                        </tfoot>
                        <tbody style="background: #f9f9f9;">
                        <!-- This is a bit of a hack, but it works (^_^) -->
                        <tr class="multi-field" id="mult-field-0" style="display:none;">
                           <td>
                               <input style="width: 200px" name="numeric_label_0" type="text" class="numeric_label" value="0" /> 
                           </td>  
                            <td>     
                                <input style="width: 60px" type="text" name="max_score_0" class="max_score" value="0" onchange="calculateTotalScore();"/> 
                           </td>                           
                           <td>     
                                <input type="checkbox" name="numeric_extra_0" class="numeric_extra extra" value="" onchange="calculateTotalScore();"/>
                           </td> 
                        </tr>
                    </table>
                    
                    <h5>Text Items</h5>
                    <table class="text-table table table-bordered" style=" border: 1px solid #AAA; max-width:25% !important;">
                        <thead style="background: #E1E1E1;">
                             <tr>
                                <th> Label </th>
                            </tr>
                        </thead>
                        <tbody style="background: #f9f9f9;">
                        <!-- This is a bit of a hack, but it works (^_^) -->
                        <tr class="multi-field" id="mult-field-0" style="display:none;">
                           <td>
                               <input style="width: 200px" name="text_label_0" type="text" class="text_label" value="0"/> 
                           </td>  
                        </tr>
                    </table>
                </div>  
                <br />
                <!--Do you want a box for an (optional) message from the TA to the student?
                <input type="radio" name="opt_ta_messg" value="yes" /> Yes
                <input type="radio" name="opt_ta_messg" value="no" /> No-->
            </div>  
            </fieldset>
            <div id="grading_questions">
            What is the <a target=_blank href="http://submitty.org/instructor/create_edit_gradeable#grading-user-groups">
	    lowest privileged user group</a> that can grade this?
            <select name="minimum_grading_group" class="int_val" style="width:180px;">
HTML;

    $grading_groups = array('1' => 'Instructor','2' => 'Full Access Grader','3' => 'Limited Access Grader');
    foreach ($grading_groups as $num => $role){
        $html_output .= <<<HTML
                <option value='{$num}'
HTML;
        ($g_min_grading_group === $num)? $html_output .= 'selected':'';
        $html_output .= <<<HTML
            >{$role}</option>
HTML;
    }
    
    $html_output .= <<<HTML
            </select>
            <br />
            <div id="ta_instructions_id">
            What overall instructions should be provided to the TA?:<br /><textarea rows="4" cols="200" name="ta_instructions" placeholder="(Optional)" style="width: 500px;">
HTML;
    $tmp = htmlspecialchars($g_overall_ta_instructions);
    $html_output .= <<<HTML
{$tmp}
</textarea>
            </div>
            <br />
            <a target=_blank href="http://submitty.org/instructor/create_edit_gradeable#grading-by-registration-section-or-rotating-section">How should graders be assigned</a> to grade this item?:
            <br />
            <fieldset>
                <input type="radio" name="section_type" value="reg_section" id="registration-section"
HTML;
    ($g_grade_by_registration===true)? $html_output .= 'checked':'';
    $html_output .= <<<HTML
                /> Registration Section
                <input type="radio" name="section_type" value="rotating-section" id="rotating-section" class="graders"
HTML;
    ($g_grade_by_registration===false)? $html_output .= 'checked':'';
    $html_output .= <<<HTML
                /> Rotating Section
HTML;

if ($initial_data[2] > 0) {
        $all_sections = str_replace(array('[', ']'), '',
            htmlspecialchars(json_encode(range(1,$initial_data[2])), ENT_NOQUOTES));
    }
    else {
        $all_sections = "";
    }

    $graders_to_sections = array();

    foreach($initial_data[3] as $grader){
        //parses the data correctly
        $graders_to_sections[$grader['user_id']] = $grader['sections'];
        $graders_to_sections[$grader['user_id']] = ltrim($graders_to_sections[$grader['user_id']], '{');
        $graders_to_sections[$grader['user_id']] = rtrim($graders_to_sections[$grader['user_id']], "}");
    }

$html_output .= <<<HTML
  <div id="rotating-sections" class="graders" style="display:none; width: 1000px; overflow-x:scroll">
  <br />
  <table id="grader-history" style="border: 3px solid black; display:none;">
HTML;
$html_output .= <<<HTML
        <tr>
        <th></th>
HTML;
  foreach($initial_data[0] as $row){
    $html_output .= <<< HTML
      <th style="padding: 8px; border: 3px solid black;">{$row['g_id']}</th>
HTML;
  }

  $html_output .= <<<HTML
        </tr>
        <tr>
HTML;
//display the appropriate graders for each user group 
function display_graders($graders, $have_old, $g_grade_by_registration, $graders_to_sections, $all_sections, &$html_output, $type_of_action){
    foreach($graders as $grader){
       $html_output .= <<<HTML
        <tr>
            <td>{$grader['user_id']}</td>
            <td><input style="width: 227px" type="text" name="grader_{$grader['user_id']}" class="grader" disabled value="
HTML;
        if(($have_old && !$g_grade_by_registration) || $type_of_action === "add_template") {
            $html_output .= (isset($graders_to_sections[$grader['user_id']])) ? $graders_to_sections[$grader['user_id']] : '';
        }
        else{
            $html_output .= $all_sections;
        }
        $html_output .= <<<HTML
"></td>
        </tr>
HTML;
    }
  }
  
  $last = '';
  foreach($initial_data[1] as $row){
    $new_row = false;
    $u_group = $row['user_group'];
    if (strcmp($row['user_id'],$last) != 0){
      $new_row = true;
    }
    if($new_row){
      $html_output .= <<<HTML
          </tr>
          <tr class="g_history g_group_{$u_group}">     
          <th style="padding: 8px; border: 3px solid black;">{$row['user_id']}</th>
HTML;

    }
    //parses $sections correctly
    $sections = ($row['sections_rotating_id']);
    $sections = ltrim($sections, '{');
    $sections = rtrim($sections, "}");
    $html_output .= <<<HTML
          <td style="padding: 8px; border: 3px solid black; text-align: center;">{$sections}</td>      
HTML;
    $last = $row['user_id'];
  }

  $html_output .= <<<HTML
            </table>
        <br /> 
        Available rotating sections: {$initial_data[2]}
        <br /> <br />
        <div id="instructor-graders">
        <table>
                <th>Instructor Graders</th>
HTML;
    display_graders($initial_data[4][0], $have_old, $g_grade_by_registration, $graders_to_sections, $all_sections, $html_output, $type_of_action);
    
  $html_output .= <<<HTML
        </table>
        </div>
        <br />
        <div id="full-access-graders" style="display:none;">
            <table>
                <th>Full Access Graders</th>
HTML;
    
  display_graders($initial_data[4][1], $have_old, $g_grade_by_registration, $graders_to_sections, $all_sections, $html_output, $type_of_action);
    
  $html_output .= <<<HTML
            </table>
HTML;

  $html_output .= <<<HTML
        </div>
        <div id="limited-access-graders" style="display:none;">
            <br />
            <table>
                <th>Limited Access Graders</th>
HTML;

  display_graders($initial_data[4][2], $have_old, $g_grade_by_registration, $graders_to_sections, $all_sections, $html_output, $type_of_action);    
  
    $html_output .= <<<HTML
        </table>

    </div> 
        <br />
    </div>
    </fieldset>
HTML;

    $html_output .= <<<HTML
            <!-- TODO default to the submission + late days for electronic -->
            What is the <em style='color: orange;'><b>Manual Grading Open Date</b></em>? (graders may begin grading)
            <input name="date_grade" id="date_grade" class="date_picker" type="text" value="{$TA_grade_open_date}"
            style="cursor: auto; background-color: #FFF; width: 250px;">
              <em style='color: orange;'>must be >= <span id="ta_grading_compare_date">Due Date (+ max allowed late days)</span></em>
            <br />
            </div>

            What is the <em style='color: orange;'><b>Grades Released Date</b></em>? (manual grades will be visible to students)
            <input name="date_released" id="date_released" class="date_picker" type="text" value="{$TA_grade_release_date}"
            style="cursor: auto; background-color: #FFF; width: 250px;">
            <em style='color: orange;'>must be >= <span id="grades_released_compare_date">Due Date (+ max allowed late days) and Manual Grading Open Date</span></em>
            <br />
            
            What <a target=_blank href="http://submitty.org/instructor/iris_rainbow_grades">syllabus category</a> does this item belong to?:
            
            <select name="gradeable_buckets" style="width: 170px;">
HTML;

    $valid_assignment_type = array('homework','assignment','problem-set',
                                   'quiz','test','exam',
                                   'exercise','lecture-exercise','reading','lab','recitation', 
                                   'project',                                   
                                   'participation','note',
                                   'none (for practice only)');
    foreach ($valid_assignment_type as $type){
        $html_output .= <<<HTML
                <option value="{$type}"
HTML;
        ($g_syllabus_bucket === $type)? $html_output .= 'selected':'';
        $title = ucwords($type);
        $html_output .= <<<HTML
                >{$title}</option>
HTML;
    }
    $html_output .= <<<HTML
            </select>
            <!-- When the form is completed and the "SAVE GRADEABLE" button is pushed
                If this is an electronic assignment:
                    Generate a new config/class.json
                    NOTE: similar to the current format with this new gradeable and all other electonic gradeables
                    Writes the inner contents for BUILD_csciXXXX.sh script
                    (probably can't do this due to security concerns) Run BUILD_csciXXXX.sh script
                If this is an edit of an existing AND there are existing grades this gradeable
                regenerates the grade reports. And possibly re-runs the generate grade summaries?
            -->
        <class="modal-footer">
                <button class="btn btn-primary" type="submit" style="margin-top: 10px; float: right;">{$button_string} Gradeable</button>
HTML;
    
    $html_output .= <<<HTML
        </div>
    </form>
</div>

<link rel="stylesheet" href="http://code.jquery.com/ui/1.10.2/themes/smoothness/jquery-ui.css" />
<link type='text/css' rel='stylesheet' href="http://trentrichardson.com/examples/timepicker/jquery-ui-timepicker-addon.css" />
<script type="text/javascript" language="javascript" src="js/jquery.min.js"></script>
<script type="text/javascript" language="javascript" src="js/jquery-ui.min.js"></script>
<script type="text/javascript" language="javascript" src="js/jquery-ui-timepicker-addon.js"></script>
<script type="text/javascript">

function createCrossBrowserJSDate(val){
        // Create a Date object that is cross-platform supported.
        // Safari's Date object constructor is more restrictive that Chrome and 
        // Firefox and will treat some dates as invalid.  Implementation details
        // vary by browser and JavaScript engine.
        // To solve this, we use Moment.js to standardize the parsing of the 
        // datetime string and convert it into a RFC2822 / IETF date format.
        //
        // For example, ""2013-05-12 20:00:00"" is converted with Moment into 
        // "Sun May 12 2013 00:00:00 GMT-0500 (EST)" and correctly parsed by
        // Safari, Chrome, and Firefox.
        //
        // Ref: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Date/parse
        // Ref: http://stackoverflow.com/questions/16616950/date-function-returning-invalid-date-in-safari-and-firefox
        var timeParseString = "YYYY-MM-DD HH:mm:ss.S" // Expected string format given by the server
        var momentDate = moment(val, timeParseString) // Parse raw datetime string
        return new Date(momentDate.toString()) // Convert moment into RFC2822 and construct browser-specific jQuery Date object
    }

    function calculateTotalScore(){
        var total_score = 0;
        var total_ec = 0;

        $('.numerics-table').find('.multi-field').each(function(){
            max_score = 0;
            extra_credit = false;

            max_score = parseFloat($(this).find('.max_score').val());
            extra_credit = $(this).find('.numeric_extra').is(':checked') == true;

            if (extra_credit === true) total_ec += max_score;
            else total_score += max_score;
        });

        $("#totalScore").html(total_score);
        $("#totalEC").html("(" + total_ec + ")");
    }

    $(document).ready(function() {
        console.log(":(");

        $(function() {
            $( ".date_picker" ).datetimepicker({
                dateFormat: 'yy-mm-dd',
                timeFormat: "HH:mm:ssz",
                showButtonPanel: true,
                showTimezone: false,
                showMillisec: false,
                showMicrosec: false,
                beforeShow: function( input ) {
                    setTimeout(function() {
                        var buttonPane = $( input )
                            .datepicker( "widget" )
                            .find( ".ui-datepicker-buttonpane" );

                        $( "<button>", {
                            text: "Infinity",
                            click: function() {
                                $.datepicker._curInst.input.datepicker('setDate', "9999-12-31 23:59:59-0400").datepicker('hide');
                            }
                        }).appendTo( buttonPane ).addClass("ui-datepicker-clear ui-state-default ui-priority-primary ui-corner-all");
                    }, 1 );
                }
            });
        });

        var numCheckpoints=1;
        
        function addCheckpoint(label, extra_credit){
            var wrapper = $('.checkpoints-table');
            ++numCheckpoints;
            $('#mult-field-0', wrapper).clone(true).appendTo(wrapper).attr('id','mult-field-'+numCheckpoints).find('.checkpoint_label').val(label).focus();
            $('#mult-field-' + numCheckpoints,wrapper).find('.checkpoint_label').attr('name','checkpoint_label_'+numCheckpoints);
            $('#mult-field-' + numCheckpoints,wrapper).find('.checkpoint_extra').attr('name','checkpoint_extra_'+numCheckpoints);
            if(extra_credit){
                $('#mult-field-' + numCheckpoints,wrapper).find('.checkpoint_extra').attr('checked',true); 
            }
            $('#remove-checkpoint_field').show();
            $('#mult-field-' + numCheckpoints,wrapper).show();
        }
        
        function removeCheckpoint(){
            if (numCheckpoints > 0){
                $('#mult-field-'+numCheckpoints,'.checkpoints-table').remove();
                if(--numCheckpoints === 1){
                    $('#remove-checkpoint_field').hide();
                }
            }
        }
        
        $('.multi-field-wrapper-checkpoints').each(function() {
            $("#add-checkpoint_field", $(this)).click(function(e) {
                addCheckpoint('Checkpoint '+(numCheckpoints+1),false);
            });
            $('#remove-checkpoint_field').click(function() {
                removeCheckpoint();
            });
        });
        
        $('#remove-checkpoint_field').hide();

        var numNumeric=0;
        var numText=0;
        
        function addNumeric(label, max_score, extra_credit){
            var wrapper = $('.numerics-table');
            numNumeric++;
            $('#mult-field-0', wrapper).clone(true).appendTo(wrapper).attr('id','mult-field-'+numNumeric).find('.numeric_label').val(label).focus();
            $('#mult-field-' + numNumeric,wrapper).find('.numeric_extra').attr('name','numeric_extra_'+numNumeric);
            $('#mult-field-' + numNumeric,wrapper).find('.numeric_label').attr('name','numeric_label_'+numNumeric);
            $('#mult-field-' + numNumeric,wrapper).find('.max_score').attr('name','max_score_'+numNumeric).val(max_score);
            if(extra_credit){
                $('#mult-field-' + numNumeric,wrapper).find('.numeric_extra').attr('checked',true); 
            }
            $('#mult-field-' + numNumeric,wrapper).show();
            calculateTotalScore();
        }
        
        function removeNumeric(){
            if (numNumeric > 0){
                $('#mult-field-'+numNumeric,'.numerics-table').remove();
            }
            --numNumeric;
        }
        
        function addText(label){
            var wrapper = $('.text-table');
            numText++;
            $('#mult-field-0', wrapper).clone(true).appendTo(wrapper).attr('id','mult-field-'+numText).find('.text_label').val(label).focus();
            $('#mult-field-' + numText,wrapper).find('.text_label').attr('name','text_label_'+numText);
            $('#mult-field-' + numText,wrapper).show();
        }
        function removeText(){
            if (numText > 0){
               $('#mult-field-'+numText,'.text-table').remove(); 
            }
            --numText;
        }
        
        $('#numeric_num_text_items').on('input', function(e){
            var requestedText = this.value;
            if (isNaN(requestedText) || requestedText < 0){
               requestedText = 0;
            }
            while(numText < requestedText){
                addText('');   
            }
            while(numText > requestedText){
               removeText();
            }
        });

        $('#numeric_num-items').on('input',function(e){
           var requestedNumeric = this.value;
           if (isNaN(requestedNumeric) || requestedNumeric < 0){
               requestedNumeric = 0;
           }
           while(numNumeric < requestedNumeric){
                addNumeric(numNumeric+1,0,false);   
           }
           while(numNumeric > requestedNumeric){
               removeNumeric();
           }
        });

        function showHistory(val){
          $('#grader-history').show();
          // hide all rows in history
          $('.g_history').hide();
          // show relevant rows
          for (var i=1; i<=parseInt(val); ++i){
              $('.g_group_'+i).show();
          }
        }

        function showGroups(val){
            var graders = ['','instructor-graders','full-access-graders', 'limited-access-graders']; 
            for(var i=parseInt(val)+1; i<graders.length; ++i){
                $('#'+graders[i]+' :input').prop('disabled',true);
                $('#'+graders[i]).hide();
            }
            for(var i=1; i <= parseInt(val) ; ++i){
                $('#'+graders[i]).show();
                $('#'+graders[i]+' :input').prop('disabled',false);
            }

            // show specific groups
            showHistory(val);
        }
        
        showGroups($('select[name="minimum_grading_group"] option:selected').attr('value'));
        
        $('select[name="minimum_grading_group"]').change(
        function(){
            showGroups(this.value);
        });

        if ($('input:radio[name="ta_grading"]:checked').attr('value') === 'false') {
            $('#rubric_questions').hide();
            $('#grading_questions').hide();
        }

        if ($('input:radio[name="student_view"]:checked').attr('value') === 'false') {
            $('#no_student_submit').prop('checked', true);
            $('#no_student_download').prop('checked',true);
            $('#yes_student_any_version').prop('checked',true);
            $('#student_submit_download_view').hide();
        }

        if ($('input:radio[name="upload_type"]:checked').attr('value') === 'upload_file') {
            $('#repository').hide();
        }

        if ($('input:radio[name="pdf_page"]:checked').attr('value') === 'false') {
            $('#pdf_page').hide();
        }

        if ($('input:radio[name="pdf_page_student"]:checked').attr('value') === 'true') {
            $('.pdf_page_input').hide();
        }

        $('.gradeable_type_options').hide();
        
        if ($('input[name="gradeable_type"]').is(':checked')){
            $('input[name="gradeable_type"]').each(function(){
                if(!($(this).is(':checked')) && ({$edit})){
                    $(this).attr("disabled",true);
                }
            });
        }

        if ($('input[name="team_assignment"]').is(':checked')){
            $('input[name="team_assignment"]').each(function(){
                if(!($(this).is(':checked')) && ({$edit})){
                    $(this).attr("disabled",true);
                }
            });
        }
          
        $('input:radio[name="ta_grading"]').change(function(){
            $('#rubric_questions').hide();
            $('#grading_questions').hide();
            if ($(this).is(':checked')){
                if($(this).val() == 'true'){ 
                    $('#rubric_questions').show();
                    $('#grading_questions').show();
                    $('#ta_instructions_id').hide();
                    $('#grades_released_compare_date').html('Manual Grading Open Date');
                } else {
                    $('#grades_released_compare_date').html('Due Date (+ max allowed late days)');
                }
            }
        });

        $('input:radio[name="peer_grading"]').change(function() {
            $('.peer_input').hide();
            $('#peer_averaging_scheme').hide();
            if ($(this).is(':checked')) {
                if($(this).val() == 'true') {
                    $('.peer_input').show();
                    $('#peer_averaging_scheme').show();
                }
            }
        });

        $('input:radio[name="ta_grading"]').change(function(){
            $('#rubric_questions').hide();
            $('#grading_questions').hide();
            if ($(this).is(':checked')){
                if($(this).val() == 'true'){ 
                    $('#rubric_questions').show();
                    $('#grading_questions').show();
                    $('#ta_instructions_id').hide();
                    $('#grades_released_compare_date').html('Manual Grading Open Date');
                } else {
                    $('#grades_released_compare_date').html('Due Date (+ max allowed late days)');
                }
            }
        });

        $('input:radio[name="student_view"]').change(function() {
            if ($(this).is(':checked')) {
                if ($(this).val() == 'true') {
                    $('#student_submit_download_view').show();
                } else {
                    $('#no_student_submit').prop('checked', true);
                    $('#no_student_download').prop('checked',true);
                    $('#yes_student_any_version').prop('checked',true);
                    $('#student_submit_download_view').hide();
                }
            }
        });

        $('input:radio[name="upload_type"]').change(function() {
            if ($(this).is(':checked')) {
                if ($(this).val() == 'repository') {
                    $('#repository').show();
                } else {
                    $('#repository').hide();
                }
            }
        });

        $('input:radio[name="pdf_page"]').change(function() {
            $('.pdf_page_input').hide();
            $('#pdf_page').hide();
            if ($(this).is(':checked')) {
                if ($(this).val() == 'true') {
                    $('.pdf_page_input').show();
                    $('#pdf_page').show();
                }
            }
        });

        $('input:radio[name="pdf_page_student"]').change(function() {
            $('.pdf_page_input').hide();
            if ($(this).is(':checked')) {
                if ($(this).val() == 'false') {
                    $('.pdf_page_input').show();
                }
            }
        });
        
        $('[name="gradeable_template"]').change(
        function(){
            var arrayUrlParts = [];
            arrayUrlParts["component"] = ["admin"];
            arrayUrlParts["page"] = ["admin_gradeable"];
            arrayUrlParts["action"] = ["upload_new_template"];
            arrayUrlParts["template_id"] = [this.value];

            var new_url = buildUrl(arrayUrlParts);
            window.location.href = new_url;
        });
        
        if({$default_late_days} != -1){
            $('input[name="eg_late_days"]').val('{$default_late_days}');
        }
        
        if($('#radio_electronic_file').is(':checked')){ 
            
            $('input[name="subdirectory"]').val('{$electronic_gradeable['eg_subdirectory']}');
            $('input[name="config_path"]').val('{$electronic_gradeable['eg_config_path']}');
            $('input[name="eg_late_days"]').val('{$electronic_gradeable['eg_late_days']}');
            $('input[name="point_precision"]').val('{$electronic_gradeable['eg_precision']}');
            $('#ta_instructions_id').hide();
            
            if($('#repository_radio').is(':checked')){
                $('#repository').show();
            }
            
            $('#electronic_file').show();
            
            if ($('input:radio[name="ta_grading"]:checked').attr('value') === 'false') {
                $('#rubric_questions').hide();
                $('#grading_questions').hide();
            }

            if($('#team_yes_radio').is(':checked')){
                $('input[name="eg_max_team_size"]').val('{$electronic_gradeable['eg_max_team_size']}');
                $('input[name="date_team_lock"]').val('{$electronic_gradeable['eg_team_lock_date']}');
                $('#team_yes').show();
            }
            else {
                $('#team_yes').hide();
            }
        }
        else if ($('#radio_checkpoints').is(':checked')){
            var components = {$old_components};
            // remove the default checkpoint
            removeCheckpoint(); 
            $.each(components, function(i,elem){
                addCheckpoint(elem.gc_title,elem.gc_is_extra_credit);
            });
            $('#checkpoints').show();
            $('#grading_questions').show();
        }
        else if ($('#radio_numeric').is(':checked')){ 
            var components = {$old_components};
            $.each(components, function(i,elem){
                if(i < {$num_numeric}){
                    addNumeric(elem.gc_title,elem.gc_max_value,elem.gc_is_extra_credit);
                }
                else{
                    addText(elem.gc_title);
                }
            });
            $('#numeric_num-items').val({$num_numeric});
            $('#numeric_num_text_items').val({$num_text});
            $('#numeric').show();
            $('#grading_questions').show();
        }
        if({$edit}){
            $('input[name="gradeable_id"]').attr('readonly', true);
        }

        $('input:radio[name="team_assignment"]').change(
    function(){
        if($('#team_yes_radio').is(':checked')){
            $('input[name="eg_max_team_size"]').val('{$electronic_gradeable['eg_max_team_size']}');
            $('input[name="date_team_lock"]').val('{$electronic_gradeable['eg_team_lock_date']}');
            $('#team_yes').show();
        }
        else {
            $('#team_yes').hide();
        }
    });

         $('input:radio[name="gradeable_type"]').change(
    function(){
        $('#required_type').hide();
        $('.gradeable_type_options').hide();
        if ($(this).is(':checked')){ 
            if($(this).val() == 'Electronic File'){ 
                $('#electronic_file').show();
                $('#ta_instructions_id').hide();
                if ($('input:radio[name="ta_grading"]:checked').attr('value') === 'false') {
                    $('#rubric_questions').hide();
                    $('#grading_questions').hide();
                }

                $('#ta_grading_compare_date').html('Due Date (+ max allowed late days)');
                if ($('input:radio[name="ta_grading"]:checked').attr('value') === 'false') {
                   $('#grades_released_compare_date').html('Due Date (+ max allowed late days)');
                } else { 
                   $('#grades_released_compare_date').html('Manual Grading Open Date');
                }

                if($('#team_yes_radio').is(':checked')){
                    $('input[name="eg_max_team_size"]').val('{$electronic_gradeable['eg_max_team_size']}');
                    $('input[name="date_team_lock"]').val('{$electronic_gradeable['eg_team_lock_date']}');
                    $('#team_yes').show();
                }
                else {
                    $('#team_yes').hide();
                }
            }
            else if ($(this).val() == 'Checkpoints'){ 
                $('#ta_instructions_id').show();
                $('#checkpoints').show();
                $('#grading_questions').show();
                $('#ta_grading_compare_date').html('TA Beta Testing Date');
                $('#grades_released_compare_date').html('Manual Grading Open Date');
            }
            else if ($(this).val() == 'Numeric'){ 
                $('#ta_instructions_id').show();
                $('#numeric').show();
                $('#grading_questions').show();
                $('#ta_grading_compare_date').html('TA Beta Testing Date');
                $('#grades_released_compare_date').html('Manual Grading Open Date');
            }
        }
    });

       if($('#rotating-section').is(':checked')){
            $('#rotating-sections').show();
        }
        $('input:radio[name="section_type"]').change(
        function(){
            $('#rotating-sections').hide();
            if ($(this).is(':checked')){
                if($(this).val() == 'rotating-section'){ 
                    $('#rotating-sections').show();
                }
            }
        });

    });

$('#gradeable-form').on('submit', function(e){
         $('<input />').attr('type', 'hidden')
            .attr('name', 'gradeableJSON')
            .attr('value', JSON.stringify($('form').serializeObject()))
            .appendTo('#gradeable-form');
         if ($("input[name='section_type']:checked").val() == 'reg_section'){
            $('#rotating-sections :input').prop('disabled',true);
         }
});

 $.fn.serializeObject = function(){
        var o = {};
        var a = this.serializeArray();
        var ignore = ["numeric_label_0", "max_score_0", "numeric_extra_0", "numeric_extra_0",
                       "text_label_0", "checkpoint_label_0", "num_numeric_items", "num_text_items"];

        $('.ignore').each(function(){
            ignore.push($(this).attr('name'));
        });
        
        // export appropriate users 
        if ($('[name="minimum_grading_group"]').prop('value') == 1){
          $('#full-access-graders').find('.grader').each(function(){
                      ignore.push($(this).attr('name'));
          });
        }

        if ($('[name="minimum_grading_group"]').prop('value') <= 2){
          $('#limited-access-graders').find('.grader').each(function(){
                      ignore.push($(this).attr('name'));
          });
        }
        
        $(':radio').each(function(){
           if(! $(this).is(':checked')){
               if($(this).attr('class') !== undefined){
                  // now remove all of the child elements names for the radio button
                  $('.' + $(this).attr('class')).find('input, textarea, select').each(function(){
                      ignore.push($(this).attr('name'));
                  });
               }
           } 
        }); 
        
        //parse checkpoints 
        
        $('.checkpoints-table').find('.multi-field').each(function(){
            var label = '';
            var extra_credit = false;
            var skip = false;
            
            $(this).find('.checkpoint_label').each(function(){
               label = $(this).val();
               if ($.inArray($(this).attr('name'),ignore) !== -1){
                   skip = true;
               }
               ignore.push($(this).attr('name'));
            });
            
            if (skip){
                return;
            }
            
            $(this).find('.checkpoint_extra').each(function(){
                extra_credit = $(this).attr('checked') === 'checked';
                ignore.push($(this).attr('name'));
            });
            
            if (o['checkpoints'] === undefined){
                o['checkpoints'] = [];
            }
            o['checkpoints'].push({"label": label, "extra_credit": extra_credit});
        });
        
        
        // parse text items
        
        $('.text-table').find('.multi-field').each(function(){
           var label = '';
           var skip = false;
           
           $(this).find('.text_label').each(function(){
                label = $(this).val();
                if ($.inArray($(this).attr('name'),ignore) !== -1){
                   skip = true;
               }
               ignore.push($(this).attr('name'));
           });
           
           if (skip){
              return;
           }
           
           if (o['text_questions'] === undefined){
               o['text_questions'] = [];
           }
           o['text_questions'].push({'label' : label});
        });
        
        // parse numeric items
                
        $('.numerics-table').find('.multi-field').each(function(){
            var label = '';  
            var max_score = 0;
            var extra_credit = false;
            var skip = false;
            
            $(this).find('.numeric_label').each(function(){
               label = $(this).val();
               if ($.inArray($(this).attr('name'),ignore) !== -1){
                   skip = true;
               }
               ignore.push($(this).attr('name'));
            });

            if (skip){
                return;
            }
            
            $(this).find('.max_score').each(function(){
               max_score = parseFloat($(this).val());
               ignore.push($(this).attr('name'));
            });

            $(this).find('.numeric_extra').each(function(){
                extra_credit = $(this).attr('checked') === 'checked';
                ignore.push($(this).attr('name'));
            });

            if (o['numeric_questions'] === undefined){
                o['numeric_questions'] = [];
            }
            o['numeric_questions'].push({"label": label, "max_score": max_score, "extra_credit": extra_credit});
           
        });
        
        
        $.each(a, function() {
            if($.inArray(this.name,ignore) !== -1) {
                return;
            }
            var val = this.value;
            if($("[name="+this.name+"]").hasClass('int_val')){
                val = parseInt(val);
            }
            else if($("[name="+this.name+"]").hasClass('float_val')){
                val = parseFloat(val);
            }

            else if($("[name="+this.name+"]").hasClass('bool_val')){
                val = (this.value === 'true');
            }
           
            if($("[name="+this.name+"]").hasClass('grader')){
                var tmp = this.name.split('_');
                var grader = tmp[1];
                if (o['grader'] === undefined){
                    o['grader'] = [];
                }
                var arr = {};
                arr[grader] = this.value.trim();
                o['grader'].push(arr);
            }
            else if ($("[name="+this.name+"]").hasClass('points')){
                if (o['points'] === undefined){
                    o['points'] = [];
                }
                o['points'].push(parseFloat(this.value));
            }
            else if($("[name="+this.name+"]").hasClass('complex_type')){
                var classes = $("[name="+this.name+"]").closest('.complex_type').prop('class').split(" ");
                classes.splice( classes.indexOf('complex_type'), 1);
                var complex_type = classes[0];
                
                if (o[complex_type] === undefined){
                    o[complex_type] = [];
                }
                o[complex_type].push(val);
            } 
            else if (o[this.name] !== undefined) {
                if (!o[this.name].push) {
                    o[this.name] = [o[this.name]];
                }
                o[this.name].push(val || '');
            } else {
                o[this.name] = val || '';
            }
        });
        return o;
    };

    function toggleQuestion(question, role) {
        if(document.getElementById(role +"_" + question ).style.display == "block") {
            $("#" + role + "_" + question ).animate({marginBottom:"-80px"});
            setTimeout(function(){document.getElementById(role + "_"+ question ).style.display = "none";}, 175);
        }
        else {
            $("#" + role + "_" + question ).animate({marginBottom:"5px"});
            setTimeout(function(){document.getElementById(role+"_" + question ).style.display = "block";}, 175);
        }
        calculatePercentageTotal();
    }

     // autoresize the comment
    function autoResizeComment(e){
        e.target.style.height ="";
        e.target.style.height = e.target.scrollHeight + "px";
    }

    function selectBox(question){
        var step = $('#point_precision_id').val();
        // should be the increment value
        return '<input type="number" id="grade-'+question+'" class="points" name="points_' + question +'" value="0" max="1000" step="'+step+'" placeholder="±0.5" onchange="calculatePercentageTotal();" style="width:50px; resize:none;">';
    }

    function fixPointPrecision(me) {
        var step = $(me).val();
        var index = 1;
        var exists = true;
        while(exists){
            if($("#grade-"+index).length){
                $("#grade-"+index).attr('step', step);
            }
            else{
                exists = false;
            }
            index++;
        }
    }

    function fixMarkPointValue(me) {
        var max = parseFloat($(me).attr('max'));
        var min = parseFloat($(me).attr('min'));
        var current_value = parseFloat($(me).val());
        if (current_value > max) {
            $(me).val(max);
        } else if (current_value < min) {
            $(me).val(min);
        }
    }

    function calculatePercentageTotal() {
        var total = 0;
        var ec = 0;
        $('input.points').each(function(){
            var elem = $(this).attr('name').replace('points_','eg_extra_');
            if ($(this).val() > 0){
                if (!$('[name="'+elem+'"]').is(':checked') == true) {
                    total += +($(this).val());
                }
                else {
                    ec += +($(this).val());
                }
            }
        });
        document.getElementById("totalCalculation").innerHTML = total + " (" + ec + ")";
    }

    function updateDeductIds(elem, old_id, new_id) {
        elem.find('div[name=deduct_'+old_id+']').each(function () {
            var deduct_id = $(this).attr('id');
            var question_id = deduct_id.split('-')[1];
            var current_id = deduct_id.split('-')[2];
            $(this).attr('name', 'deduct_' + new_id);
            $(this).attr('id', 'deduct_id-'+new_id+'-'+current_id+'');
            $(this).find('input[name=deduct_points_'+old_id+'_'+current_id+']').attr('name', 'deduct_points_'+new_id+'_'+current_id);
            $(this).find('textarea[name=deduct_text_'+old_id+'_'+current_id+']').attr('name', 'deduct_text_'+new_id+'_'+current_id);
        });
    }

    function deleteQuestion(question) {
        if (question <= 0) {
            return;
        }
        var row = $('tr#row-'+ question);
        row.remove();
        var totalQ = parseInt($('.rubric-row').last().attr('id').split('-')[1]);
        for(var i=question+1; i<= totalQ; ++i){
            updateRow(i,i-1);
        }
        calculatePercentageTotal();
    }

    function updateRow(oldNum, newNum) {
        var row = $('tr#row-'+ oldNum);
        row.attr('id', 'row-' + newNum);
        row.find('textarea[name=comment_title_' + oldNum + ']').attr('name', 'comment_title_' + newNum);
        row.find('div.btn').attr('onclick', 'toggleQuestion(' + newNum + ',"individual"' + ')');
        row.find('textarea[name=ta_comment_' + oldNum + ']').attr('name', 'ta_comment_' + newNum).attr('id', 'individual_' + newNum);
        row.find('textarea[name=student_comment_' + oldNum + ']').attr('name', 'student_comment_' + newNum).attr('id', 'student_' + newNum);
        row.find('input[name=points_' + oldNum + ']').attr('name', 'points_' + newNum);
        row.find('input[name=eg_extra_' + oldNum + ']').attr('name', 'eg_extra_' + newNum);
        row.find('div[id=peer_checkbox_' + oldNum +']').attr('id', 'peer_checkbox_' + newNum);
        row.find('input[name=peer_component_'+ oldNum + ']').attr('name', 'peer_component_' + newNum);

        row.find('div[id=pdf_page_' + oldNum +']').attr('id', 'pdf_page_' + newNum);
        row.find('input[name=page_component_' + oldNum + ']').attr('name', 'page_component_' + newNum);

        row.find('a[id=delete-' + oldNum + ']').attr('id', 'delete-' + newNum).attr('onclick', 'deleteQuestion(' + newNum + ')');
        row.find('a[id=down-' + oldNum + ']').attr('id', 'down-' + newNum).attr('onclick', 'moveQuestionDown(' + newNum + ')');
        row.find('a[id=up-' + oldNum + ']').attr('id', 'up-' + newNum).attr('onclick', 'moveQuestionUp(' + newNum + ')');
        row.find('input[name=deduct_radio_'+ oldNum +']').attr('name', 'deduct_radio_' + newNum);
        row.find('input[id=deduct_radio_ded_id_' + oldNum +']').attr('id', 'deduct_radio_ded_id_' + newNum);
        row.find('input[id=deduct_radio_add_id_' + oldNum +']').attr('id', 'deduct_radio_add_id_' + newNum);
        row.find('div[id=deduction_questions_'+oldNum+']').attr('id', 'deduction_questions_'+newNum);
        row.find('div[id=rubric_add_deduct_' + oldNum + ']').attr('id','rubric_add_deduct_' + newNum).attr('onclick', 'addDeduct(this,' + newNum + ')'); 
        updateDeductIds(row,oldNum,newNum);
    }

    function moveQuestionDown(question) {
        if (question < 1) {
            return;
        }

        var currentRow = $('tr#row-' + question);
        var newRow = $('tr#row-' + (question+1));
        var child = 0;
        if (question == 1) {
            child = 1;
        }
        var new_question = parseInt(question) + 1;

        if(!newRow.length) {
            return false;
        }

        if(!newRow.length) {
            return false;
        }

        //Move Question title
        var temp = currentRow.children()[child].children[0].value;
        currentRow.children()[child].children[0].value = newRow.children()[0].children[0].value;
        newRow.children()[0].children[0].value = temp;

        //Move Ta Comment
        temp = currentRow.children()[child].children[1].value;
        currentRow.children()[child].children[1].value = newRow.children()[0].children[1].value;
        newRow.children()[0].children[1].value = temp;

        //Move Student Comment
        temp = currentRow.children()[child].children[2].value;
        currentRow.children()[child].children[2].value = newRow.children()[0].children[2].value;
        newRow.children()[0].children[2].value = temp;

        child += 1;

        //Move points
        temp = currentRow.children()[child].children[0].value;
        currentRow.children()[child].children[0].value = newRow.children()[1].children[0].value;
        newRow.children()[1].children[0].value = temp;

        //Move extra credit box
        temp = currentRow.children()[child].children[2].checked;
        currentRow.children()[child].children[2].checked = newRow.children()[1].children[2].checked;
        newRow.children()[1].children[2].checked = temp;

        //Move peer grading box
        temp = currentRow.find('input[name=peer_component_' + question +']')[0].checked;
        currentRow.find('input[name=peer_component_' + question +']')[0].checked = newRow.find('input[name=peer_component_' + new_question +']')[0].checked;
        newRow.find('input[name=peer_component_' + new_question +']')[0].checked = temp;

        //Move page
        temp = currentRow.find('input[name=page_component_' + question + ']')[0].value;
        currentRow.find('input[name=page_component_' + question +']')[0].value = newRow.find('intput[name=page_component_' + new_question + ']')[0].value;
        newRow.find('intput[name=page_component_' + new_question + ']')[0].value = temp;

        //Move the radio button
        var ded_temp = document.getElementById("deduct_radio_ded_id_" + question).checked;
        var add_temp = document.getElementById("deduct_radio_add_id_" + question).checked;
        document.getElementById("deduct_radio_ded_id_" + question).checked = document.getElementById("deduct_radio_ded_id_" + new_question).checked;
        document.getElementById("deduct_radio_add_id_" + question).checked = document.getElementById("deduct_radio_add_id_" + new_question).checked;
        document.getElementById("deduct_radio_ded_id_" + new_question).checked = ded_temp;
        document.getElementById("deduct_radio_add_id_" + new_question).checked = add_temp;

        //stores the point and text data so it can readded; the html earses it once moved
        var current_deduct_points = [];
        var current_deduct_texts = [];
        currentRow.find('div[name=deduct_'+question+']').each(function () {
            current_deduct_points.push($(this).find("input").val());
            current_deduct_texts.push($(this).find("textarea").val());
        });
        var new_deduct_points = [];
        var new_deduct_texts = [];
        newRow.find('div[name=deduct_'+new_question+']').each(function () {
            new_deduct_points.push($(this).find("input").val());
            new_deduct_texts.push($(this).find("textarea").val());
        });

        //switchs the html between the table rows
        var temp_html = currentRow.find('div[id=deduction_questions_'+question+']').html();
        currentRow.find('div[id=deduction_questions_'+question+']').html(newRow.find('div[id=deduction_questions_'+new_question+']').html());
        newRow.find('div[id=deduction_questions_'+new_question+']').html(temp_html);

        //fixes the ids once switched
        currentRow.find('div[id=rubric_add_deduct_' + new_question + ']').attr('id','rubric_add_deduct_' + question).attr('onclick', 'addDeduct(this,' + question + ')'); 
        updateDeductIds(currentRow,new_question,question);
        newRow.find('div[id=rubric_add_deduct_' + question + ']').attr('id','rubric_add_deduct_' + new_question).attr('onclick', 'addDeduct(this,' + new_question + ')'); 
        updateDeductIds(newRow,question,new_question);

        //readds the data
        currentRow.find('div[name=deduct_'+question+']').each(function (index) {
            $(this).find("input").val(new_deduct_points[index]);
            $(this).find("textarea").val(new_deduct_texts[index]);
        });
        newRow.find('div[name=deduct_'+new_question+']').each(function (index) {
            $(this).find("input").val(current_deduct_points[index]);
            $(this).find("textarea").val(current_deduct_texts[index]);
        });
    }

    function moveQuestionUp(question) {
        if (question < 1) {
            return;
        }

        var currentRow = $('tr#row-' + question);
        var newRow = $('tr#row-' + (question-1));
        var child = 0;

        //Move Question title
        var temp = currentRow.children()[0].children[0].value; 
        currentRow.children()[0].children[0].value = newRow.children()[child].children[0].value;
        newRow.children()[child].children[0].value = temp;

        //Move Ta Comment
        temp = currentRow.children()[0].children[1].value; 
        currentRow.children()[0].children[1].value = newRow.children()[child].children[1].value;
        newRow.children()[child].children[1].value = temp;

        //Move Student Comment
        temp = currentRow.children()[0].children[2].value; 
        currentRow.children()[0].children[2].value = newRow.children()[child].children[2].value;
        newRow.children()[child].children[2].value = temp;

        child += 1;

        //Move points
        temp = currentRow.children()[1].children[0].value; 
        currentRow.children()[1].children[0].value = newRow.children()[child].children[0].value;
        newRow.children()[child].children[0].value = temp;

        //Move extra credit box
        temp = currentRow.children()[1].children[2].checked;
        currentRow.children()[1].children[2].checked = newRow.children()[child].children[2].checked;
        newRow.children()[child].children[2].checked = temp;

        //Move peer grading box
        temp = currentRow.find('input[name=peer_component_' + question +']')[0].checked;
        currentRow.find('input[name=peer_component_' + question +']')[0].checked = newRow.find('input[name=peer_component_' + (question-1) +']')[0].checked;
        newRow.find('input[name=peer_component_' + (question-1) +']')[0].checked = temp;

        //Move page
        temp = currentRow.find('input[name=page_component_' + question +']')[0].value;
        currentRow.find('input[name=page_component_' + question +']')[0].value = newRow.find('input[name=page_component_' + (question-1) +']')[0].value;
        newRow.find('input[name=page_component_' + (question-1) +']')[0].value = temp;

        //Move the radio button
        var ded_temp = document.getElementById("deduct_radio_ded_id_" + question).checked;
        var add_temp = document.getElementById("deduct_radio_add_id_" + question).checked;
        document.getElementById("deduct_radio_ded_id_" + question).checked = document.getElementById("deduct_radio_ded_id_" + (question-1)).checked;
        document.getElementById("deduct_radio_add_id_" + question).checked = document.getElementById("deduct_radio_add_id_" + (question-1)).checked;
        document.getElementById("deduct_radio_ded_id_" + (question-1)).checked = ded_temp;
        document.getElementById("deduct_radio_add_id_" + (question-1)).checked = add_temp;

        //stores the point and text data so it can readded; the html earses it once moved
        var current_deduct_points = [];
        var current_deduct_texts = [];
        currentRow.find('div[name=deduct_'+question+']').each(function () {
            current_deduct_points.push($(this).find("input").val());
            current_deduct_texts.push($(this).find("textarea").val());
        });
        var new_deduct_points = [];
        var new_deduct_texts = [];
        newRow.find('div[name=deduct_'+(question-1)+']').each(function () {
            new_deduct_points.push($(this).find("input").val());
            new_deduct_texts.push($(this).find("textarea").val());
        });

        //switchs the html between the table rows
        var temp_html = currentRow.find('div[id=deduction_questions_'+question+']').html();
        currentRow.find('div[id=deduction_questions_'+question+']').html(newRow.find('div[id=deduction_questions_'+(question-1)+']').html());
        newRow.find('div[id=deduction_questions_'+(question-1)+']').html(temp_html);

        //fixes the ids once switched
        currentRow.find('div[id=rubric_add_deduct_' + (question-1) + ']').attr('id','rubric_add_deduct_' + question).attr('onclick', 'addDeduct(this,' + question + ')'); 
        updateDeductIds(currentRow,(question-1),question);
        newRow.find('div[id=rubric_add_deduct_' + question + ']').attr('id','rubric_add_deduct_' + (question-1)).attr('onclick', 'addDeduct(this,' + (question-1) + ')'); 
        updateDeductIds(newRow,question,(question-1));

        //readds the data
        currentRow.find('div[name=deduct_'+question+']').each(function (index) {
            $(this).find("input").val(new_deduct_points[index]);
            $(this).find("textarea").val(new_deduct_texts[index]);
        });
        newRow.find('div[name=deduct_'+(question-1)+']').each(function (index) {
            $(this).find("input").val(current_deduct_points[index]);
            $(this).find("textarea").val(current_deduct_texts[index]);
        });
    }

    function addQuestion(){
        //get the last question number
        var num = parseInt($('.rubric-row').last().attr('id').split('-')[1]);
        var newQ = num+1;
        var sBox = selectBox(newQ);
        var display = "";
        var displayPage = "";
        if($('input[id=peer_no_radio]').is(':checked')) {
            display = 'style="display:none"';
        }
        if($('input[id=no_pdf_page]').is(':checked') || $('input[id=yes_pdf_page_student]').is(':checked')) {
            displayPage = 'style="display:none"';
        }
        $('#row-'+num).after('<tr class="rubric-row" id="row-'+newQ+'"> \
            <td style="overflow: hidden; border-top: 5px solid #dddddd;"> \
                <textarea name="comment_title_'+newQ+'" rows="1" class="comment_title complex_type" style="width: 99%; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-right: 1px; height: auto;" placeholder="Rubric Item Title"></textarea> \
                <textarea name="ta_comment_'+newQ+'" id="individual_'+newQ+'" rows="1" class="ta_comment complex_type" placeholder=" Message to TA/Grader (seen only by TAs/Graders)"  onkeyup="autoResizeComment(event);" \
                          style="width: 99%; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-bottom: 5px; height: auto;"></textarea> \
                <textarea name="student_comment_'+newQ+'" id="student_'+newQ+'" rows="1" class="student_comment complex_type" placeholder=" Message to Student (seen by both students and graders)"  onkeyup="autoResizeComment(event);" \
                          style="width: 99%; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-bottom: 5px; height: auto;"></textarea> \
                <div id=deduction_questions_'+newQ+'> \
                <div class="btn btn-xs btn-primary" id="rubric_add_deduct_'+newQ+'" onclick="addDeduct(this,'+newQ+')" style="overflow: hidden; text-align: left;float: left;">Add Common Deduction/Addition</div> </div> \
            </td> \
            <td style="background-color:#EEE; border-top: 5px solid #dddddd;">' + sBox + ' \
                <br /> \
                Extra Credit:&nbsp;&nbsp;<input onclick="calculatePercentageTotal();" name="eg_extra_'+newQ+'" type="checkbox" class="eg_extra extra" value="on"/> \
                Deduction/Addition:&nbsp;&nbsp;<input type="radio" id="deduct_radio_ded_id_'+newQ+'" name="deduct_radio_'+newQ+'" value="deduction" onclick="onDeduction(this);" checked> <i class="fa fa-minus-square" aria-hidden="true"> </i> \
                <input type="radio" id="deduct_radio_add_id_'+newQ+'" name="deduct_radio_'+newQ+'" value="addition" onclick="onAddition(this);"> <i class="fa fa-plus-square" aria-hidden="true"> </i> \
                <br /> \
                <div id="peer_checkbox_'+newQ+'" class="peer_input" '+display+'>Peer Component:&nbsp;&nbsp;<input type="checkbox" name="peer_component_'+newQ+'" value="on" class="peer_component" /></div> \
                <div id="pdf_page_'+newQ+'" class="pdf_page_input" '+displayPage+'>Page:&nbsp;&nbsp;<input type="number" name="page_component_'+newQ+'" value="1" class="page_component" max="1000" step="1" style="width:50px; resize:none;"/></div> \
                <a id="delete-'+newQ+'" class="question-icon" onclick="deleteQuestion('+newQ+');"> \
                    <i class="fa fa-times" aria-hidden="true"></i></a> \
                <a id="down-'+newQ+'" class="question-icon" onclick="moveQuestionDown('+newQ+');"> \
                    <i class="fa fa-arrow-down" aria-hidden="true"></i></a> \
                <a id="up-'+newQ+'" class="question-icon" onclick="moveQuestionUp('+newQ+');"> \
                    <i class="fa fa-arrow-up" aria-hidden="true"></i></a> \
            </td> \
        </tr>');
        $("#rubric_add_deduct_" + newQ).before(' \
            <div id="deduct_id-'+newQ+'-0" name="deduct_'+newQ+'" style="text-align: left; font-size: 8px; padding-left: 5px; display: none;"> \
            <i class="fa fa-circle" aria-hidden="true"></i> <input type="number" class="points2" name="deduct_points_'+newQ+'_0" value="0" step="0.5" placeholder="±0.5" style="width:50px; resize:none; margin: 5px;"> \
            <textarea rows="1" placeholder="Comment" name="deduct_text_'+newQ+'_0" style="resize: none; width: 81.5%;">Full Credit</textarea> \
            <a onclick="deleteDeduct(this)"> <i class="fa fa-times" aria-hidden="true" style="font-size: 16px; margin: 5px;"></i></a> \
            <a onclick="moveDeductDown(this)"> <i class="fa fa-arrow-down" aria-hidden="true" style="font-size: 16px; margin: 5px;"></i></a> \
            <a onclick="moveDeductUp(this)"> <i class="fa fa-arrow-up" aria-hidden="true" style="font-size: 16px; margin: 5px;"></i></a> \
            <br> \
        </div> \
            ');
    }

    function deleteDeduct(me) {
        var question_id = me.parentElement.id.split('-')[1];
        var current_id = me.parentElement.id.split('-')[2];
        var current_row = $('#deduct_id-'+question_id+'-'+current_id);
        current_row.remove();
        var last_deduct = $('[name=deduct_'+question_id+']').last().attr('id');
        var totalD = -1;
        if (last_deduct == null) {
            totalD = -1;
        } 
        else {
            totalD = parseInt($('[name=deduct_'+question_id+']').last().attr('id').split('-')[2]);
        }
        current_id = parseInt(current_id);
        for(var i=current_id+1; i<= totalD; ++i){
            updateDeduct(i,i-1, question_id);
        }
    }

    function updateDeduct(old_num, new_num, question_num) {
        var current_deduct = $('#deduct_id-'+question_num+'-'+old_num);
        current_deduct.find('input[name=deduct_points_'+question_num+'_'+old_num+']').attr('name', 'deduct_points_'+question_num+'_'+new_num);
        current_deduct.find('textarea[name=deduct_text_'+question_num+'_'+old_num+']').attr('name', 'deduct_text_'+question_num+'_'+new_num);
        current_deduct.attr('id', 'deduct_id-'+question_num+'-'+new_num);
    }

    function moveDeductDown(me) {
        var question_id = me.parentElement.id.split('-')[1];
        var current_id = me.parentElement.id.split('-')[2];
        current_id = parseInt(current_id);
        //checks if the element exists
        if (!($('#deduct_id-'+question_id+'-'+(current_id+1)).length)) {
            return false;
        }
        var current_row = $('#deduct_id-'+question_id+'-'+current_id);
        var current_textarea_value = current_row.find("textarea").val();
        var current_input_value = current_row.find("input").val();

        var new_row = $('#deduct_id-'+question_id+'-'+(current_id+1));
        var new_textarea_value = new_row.find("textarea").val();
        var new_input_value = new_row.find("input").val();

        var temp_textarea_value = new_textarea_value;
        var temp_input_value = new_input_value;

        new_row.find("textarea").val(current_textarea_value);
        new_row.find("input").val(current_input_value);

        current_row.find("textarea").val(temp_textarea_value);
        current_row.find("input").val(temp_input_value);
    }

    function moveDeductUp(me) {
        var question_id = me.parentElement.id.split('-')[1];
        var current_id = me.parentElement.id.split('-')[2];
        current_id = parseInt(current_id);
        if (current_id == 0 || current_id == 1) {
            return false;
        }
        var current_row = $('#deduct_id-'+question_id+'-'+current_id);
        var current_textarea_value = current_row.find("textarea").val();
        var current_input_value = current_row.find("input").val();

        var new_row = $('#deduct_id-'+question_id+'-'+(current_id-1));
        var new_textarea_value = new_row.find("textarea").val();
        var new_input_value = new_row.find("input").val();

        var temp_textarea_value = new_textarea_value;
        var temp_input_value = new_input_value;

        new_row.find("textarea").val(current_textarea_value);
        new_row.find("input").val(current_input_value);

        current_row.find("textarea").val(temp_textarea_value);
        current_row.find("input").val(temp_input_value);
    }

    function onDeduction(me) {
        var current_row = $(me.parentElement.parentElement);
        var current_question = parseInt(current_row.attr('id').split('-')[1]);
        current_row.find('textarea[name=deduct_text_'+current_question+'_0]').val('Full Credit');
        current_row.find('div[name=deduct_'+current_question+']').each(function () {
            $(this).find("input").attr('min', -1000);
            $(this).find("input").attr('max', 0);
            if ($(this).find("input").val() > 0) {
                $(this).find("input").val($(this).find("input").val() * -1);
            }            
        });
    }

    function onAddition(me) {
        var current_row = $(me.parentElement.parentElement);
        var current_question = parseInt(current_row.attr('id').split('-')[1]);
        current_row.find('textarea[name=deduct_text_'+current_question+'_0]').val('No Credit');
        current_row.find('div[name=deduct_'+current_question+']').each(function () {
            $(this).find("input").attr('min', 0);
            $(this).find("input").attr('max', 1000);
            if ($(this).find("input").val() < 0) {
                $(this).find("input").val($(this).find("input").val() * -1);
            }            
        });
    }

    function addDeduct(me, num){
        var last_num = -10;
        var min = 0;
        var max = 0;
        var current_row = $(me.parentElement.parentElement.parentElement);
        var radio_value = current_row.find('input[name=deduct_radio_'+num+']:checked').val();
        if(radio_value == "deduction") {
            min = -1000;
            max = 0;
        }
        else if(radio_value == "addition") {
            min = 0;
            max = 1000;
        }
        else {
            min = 0;
            max = 0;
        }
        var current = $('[name=deduct_'+num+']').last().attr('id');
        if (current == null) {
            last_num = -1;
        } 
        else {
            last_num = parseInt($('[name=deduct_'+num+']').last().attr('id').split('-')[2]);
        }
        var new_num = last_num + 1;
        $("#rubric_add_deduct_" + num).before('\
<div id="deduct_id-'+num+'-'+new_num+'" name="deduct_'+num+'" style="text-align: left; font-size: 8px; padding-left: 5px;">\
<i class="fa fa-circle" aria-hidden="true"></i> <input onchange="fixMarkPointValue(this);" type="number" class="points2" name="deduct_points_'+num+'_'+new_num+'" value="0" min="'+min+'" max="'+max+'" step="0.5" placeholder="±0.5" style="width:50px; resize:none; margin: 5px;"> \
<textarea rows="1" placeholder="Comment" name="deduct_text_'+num+'_'+new_num+'" style="resize: none; width: 81.5%;"></textarea> \
<a onclick="deleteDeduct(this)"> <i class="fa fa-times" aria-hidden="true" style="font-size: 16px; margin: 5px;"></i></a> \
<a onclick="moveDeductDown(this)"> <i class="fa fa-arrow-down" aria-hidden="true" style="font-size: 16px; margin: 5px;"></i></a> \
<a onclick="moveDeductUp(this)"> <i class="fa fa-arrow-up" aria-hidden="true" style="font-size: 16px; margin: 5px;"></i></a> \
<br> \
</div>');
    }

    $('input:radio[name="gradeable_type"]').change(
    function(){
        $('#required_type').hide();
        $('.gradeable_type_options').hide();
        if ($(this).is(':checked')){ 
            if($(this).val() == 'Electronic File'){ 
                $('#electronic_file').show();
                if ($('input:radio[name="ta_grading"]:checked').attr('value') === 'false') {
                    $('#rubric_questions').hide();
                    $('#grading_questions').hide();
                }

                $('#ta_grading_compare_date').html('Due Date (+ max allowed late days)');
                if ($('input:radio[name="ta_grading"]:checked').attr('value') === 'false') {
                   $('#grades_released_compare_date').html('Due Date (+ max allowed late days)');
                } else { 
                   $('#grades_released_compare_date').html('Manual Grading Open Date');
                }

                if($('#team_yes_radio').is(':checked')){
                    $('input[name=eg_max_team_size]').val('{$electronic_gradeable['eg_max_team_size']}');
                    $('input[name=date_team_lock]').val('{$electronic_gradeable['eg_team_lock_date']}');
                    $('#team_yes').show();
                }
                else {
                    $('#team_yes').hide();
                }
            }
            else if ($(this).val() == 'Checkpoints'){ 
                $('#checkpoints').show();
                $('#grading_questions').show();
                $('#ta_grading_compare_date').html('TA Beta Testing Date');
                $('#grades_released_compare_date').html('Manual Grading Open Date');
            }
            else if ($(this).val() == 'Numeric'){ 
                $('#numeric').show();
                $('#grading_questions').show();
                $('#ta_grading_compare_date').html('TA Beta Testing Date');
                $('#grades_released_compare_date').html('Manual Grading Open Date');
            }
        }
    });

    $(function () {
        $("#alert-message").dialog({
            modal: true,
            autoOpen: false,
            buttons: {
                Ok: function () {
                     $(this).dialog("close");
                 }
             }
         });
    });

    function checkForm() {
        var gradeable_id = $('#gradeable_id').val();
        var gradeable_title = $('gradeable_title_id').val();
        var date_submit = Date.parse($('#date_submit').val());
        var date_due = Date.parse($('#date_due').val());
        var date_ta_view = Date.parse($('#date_ta_view').val());
        var date_grade = Date.parse($('#date_grade').val());
        var date_released = Date.parse($('#date_released').val());
        var subdirectory = $('input[name="subdirectory"]').val();
        var config_path = $('input[name=config_path]').val();
        var has_space = gradeable_id.includes(" ");
        var test = /^[a-zA-Z0-9_-]*$/.test(gradeable_id);
        var unique_gradeable = false;
        var bad_max_score = false;
        var check1 = $('#radio_electronic_file').is(':checked');
        var check2 = $('#radio_checkpoints').is(':checked');
        var check3 = $('#radio_numeric').is(':checked');
        var checkRegister = $('#registration-section').is(':checked');
        var checkRotate = $('#rotating-section').is(':checked');
        var all_gradeable_ids = $js_gradeables_array;
        if($('#peer_yes_radio').is(':checked')) {
            var found_peer_component = false;
            var found_reg_component = false;
            $("input[name^='peer_component']").each(function() {
                console.log(this);
                if (this.checked) {
                    found_peer_component = true;
                }
                else {
                    found_reg_component = true;
                }
            });
            if (!found_peer_component) {
                alert("At least one component must be for peer_grading");
                return false;
            }
            if (!found_reg_component) {
                alert("At least one component must be for manual grading");
                return false;
            }
        }
        if($('#yes_pdf_page').is(':checked')) {
            $("input[name^='page_component']").each(function() {
                console.log(this);
                console.log(this.value);
            });
            // return false;
        }
        if($('#team_yes_radio').is(':checked')) {
            if ($("input[name^='eg_max_team_size']").val() < 2) {
                alert("Maximum team size must be at least 2");
                return false;
            }
        }
        if (!($edit)) {
            var x;
            for (x = 0; x < all_gradeable_ids.length; x++) {
                if (all_gradeable_ids[x] === gradeable_id) {
                    alert("Gradeable already exists");
                    return false;
                }
            }
        }
        if (!test || has_space || gradeable_id == "" || gradeable_id === null) {
            $( "#alert-message" ).dialog( "open" );
            return false;
        }
        if(check1) {
            if(date_submit < date_ta_view) {
                alert("DATE CONSISTENCY:  Submission Open Date must be >= TA Beta Testing Date");
                return false;
            }   
            if(date_due < date_submit) {
                alert("DATE CONSISTENCY:  Due Date must be >= Submission Open Date");
                return false;
            }
            if ($('input:radio[name="upload_type"]:checked').attr('value') === 'repository') {
                var subdirectory_parts = subdirectory.split("{");
                var x=0;
                // if this is a vcs path extension, make sure it starts with '/'
                if ("{$vcs_base_url}" !== "None specified." && subdirectory_parts[0][0] !== "/") {
                    alert("VCS path needs to start with '/'");
                    return false;
                }
                // check that path is made up of valid variables
                var allowed_variables = ["\$gradeable_id", "\$user_id", "\$repo_id"];
                var used_user_id = false;
                for (x = 1; x < subdirectory_parts.length; x++) {
                    subdirectory_part = subdirectory_parts[x].substring(0, subdirectory_parts[x].lastIndexOf("}"));
                    if (allowed_variables.indexOf(subdirectory_part) === -1) {
                        alert("For the VCS path, '" + subdirectory_part + "' is not a valid variable name.")
                        return false;
                    }
                    if (subdirectory_part === "\$user_id") {
                        used_user_id = true;
                    }
                    if (used_user_id && subdirectory_part === "\$repo_id") {
                        alert("You cannot use both \$user_id and \$repo_id");
                        return false;
                    }
                }
                
            }
            if(config_path == "" || config_path === null) {
                alert("The config path should not be empty");
                return false;
            }
            // if view false while either submit or download true
            if ($('input:radio[name="student_view"]:checked').attr('value') === 'false' &&
               ($('input:radio[name="student_submit"]:checked').attr('value') === 'true' ||
                $('input:radio[name="student_download"]:checked').attr('value') === 'true')) {
                alert("Student_view cannot be false while student_submit or student_download is true");
                return false;
            }
        }
        if ($('input:radio[name="ta_grading"]:checked').attr('value') === 'true') {
            if(date_grade < date_due) {
                alert("DATE CONSISTENCY:  Manual Grading Open Date must be >= Due Date (+ max allowed late days)");
                return false;
            }
            if(date_released < date_due) {
                alert("DATE CONSISTENCY:  Grades Released Date must be >= Manual Grading Open Date");
                return false;
            }
        }
        else {
            if(check1) {
                if(date_released < date_due) {
                    alert("DATE CONSISTENCY:  Grades Released Date must be >= Due Date (+ max allowed late days)");
                    return false;
                }
            }
        }
        if($('input:radio[name="ta_grading"]:checked').attr('value') === 'true' || check2 || check3) {
            if(date_grade < date_ta_view) {
                alert("DATE CONSISTENCY:  Manual Grading Open Date must be >= TA Beta Testing Date");
                return false;
            }
            if(date_released < date_grade) {
                alert("DATE CONSISTENCY:  Grade Released Date must be >= Manual Grading Open Date");
                return false;
            }

            if(!checkRegister && !checkRotate) {
                alert("A type of way for TAs to grade must be selected");
                return false;
            }
        }
        if(!check1 && !check2 && !check3) {
            alert("A type of gradeable must be selected");
            return false;
        }

        var numOfNumeric = 0;
        var wrapper = $('.numerics-table');
        var i;
        if (check3) {
                for (i = 0; i < $('#numeric_num-items').val(); i++) {
                    numOfNumeric++;
                    if ($('#mult-field-' + numOfNumeric,wrapper).find('.max_score').attr('name','max_score_'+numOfNumeric).val() == 0) {
                        alert("Max score cannot be 0 [Question "+ numOfNumeric + "]");
                        return false;
                }
            }
        }
        
    }
calculatePercentageTotal();
calculateTotalScore();
    </script>
HTML;
    $html_output .= <<<HTML
<div id="alert-message" title="WARNING">
  <p>Gradeable ID must not be blank and only contain characters <strong> a-z A-Z 0-9 _ - </strong> </p>
</div>
HTML;

	return $html_output;
	}



}
