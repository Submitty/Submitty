<?php
use \lib\Database;
use \lib\Functions;

include "../header.php";

check_administrator();

if($user_is_administrator){
    $have_old = $has_grades = $have_old_edit = false;
    $current_date = date('Y/m/d 23:59:59');
    $yesterday = date('Y/m/d 23:59:59', strtotime( '-1 days' ));
    $old_gradeable = array(
        'g_id' => -1,
        'g_title' => "",
        'g_overall_ta_instructions' => '',
        'g_team_assignment' => false,
        'g_gradeable_type' => 0,
        'g_grade_by_registration' => false,
        'g_ta_view_start_date' => date('Y/m/d 23:59:59', strtotime( '-1 days' )),
        'g_grade_start_date' => date('Y/m/d 23:59:59', strtotime( '+10 days' )),
        'g_grade_released_date' => date('Y/m/d 23:59:59', strtotime( '+14 days' )),
        'g_syllabus_bucket' => '',
        'g_min_grading_group' => '',
        'g_instructions_url' => ''
    );
    $old_questions = $old_components = $electronic_gradeable = array();
    $electronic_gradeable['eg_submission_open_date'] = date('Y/m/d 23:59:59', strtotime( '0 days' )); //"";
    $electronic_gradeable['eg_submission_due_date'] = date('Y/m/d 23:59:59', strtotime( '+7 days' )); //"";"";
    $electronic_gradeable['eg_subdirectory'] = "";
    $electronic_gradeable['eg_config_path'] = "";
    $electronic_gradeable['eg_late_days'] = __DEFAULT_HW_LATE_DAYS__;
    $electronic_gradeable['eg_precision'] = 0.5;
    $old_components = "{}";
    
    $num_numeric = $num_text = 0;
    $g_gradeable_type = $g_syllabus_bucket = $g_min_grading_group = $default_late_days = -1;
    $team_assignment = false;
    $is_repository = false;
    $use_ta_grading = false;
    $g_overall_ta_instructions = $g_id = '';
    $edit = json_encode(isset($_GET['action']) && $_GET['action'] == 'edit');
    
    $initial_ta_grading_compare_date = "UNSPECIFIED";
    $initial_grades_released_compare_date = "TA Beta Testing Date";

    if (isset($_GET['action']) && ($_GET['action'] == 'edit' || $_GET['action'] == 'template')) {
        $g_id = $_GET['id'];
        Database::query("SELECT * FROM gradeable WHERE g_id=?",array($g_id));
        if (count(Database::rows()) == 0) {
            die("No gradeable found");
        }
        $old_gradeable = Database::row();
        Database::query("SELECT * FROM gradeable_component WHERE g_id=? ORDER BY gc_order", array($g_id));
        $old_components = json_encode(Database::rows());
        $have_old = true;
        $have_old_edit = (isset($_GET['action']) && ($_GET['action'] == 'edit'));
        
        // get the number of text and numeric fields for the form
        if ($old_gradeable['g_gradeable_type']==2){
            $params=array($g_id);
            $db->query("SELECT COUNT(*) AS cnt FROM gradeable AS g INNER JOIN gradeable_component AS gc 
                        ON g.g_id=gc.g_id WHERE g.g_id=? AND gc_is_text='false'", $params);
            $num_numeric = $db->row()['cnt'];
            $db->query("SELECT COUNT(*) AS cnt FROM gradeable AS g INNER JOIN gradeable_component AS gc 
                        ON g.g_id=gc.g_id WHERE g.g_id=? AND gc_is_text='true'", $params);
            $num_text = $db->row()['cnt'];
        }
        
        //figure out if the gradeable has grades or not
        $db->query("SELECT COUNT(*) as cnt FROM gradeable AS g INNER JOIN gradeable_component AS gc ON g.g_id=gc.g_id 
                    INNER JOIN gradeable_component_data AS gcd ON gcd.gc_id=gc.gc_id WHERE g.g_id=?",array($g_id));
        $has_grades= $db->row()['cnt'];

        //get team assignment boolean
        $team_assignment = $old_gradeable['g_team_assignment'];

       //if electonic file then add all of the old questions
       if($old_gradeable['g_gradeable_type']==0){
            //get the electronic file stuff
            $db->query("SELECT * FROM electronic_gradeable WHERE g_id=?", array($g_id));
            $electronic_gradeable = $db->row();
            $use_ta_grading = $electronic_gradeable['eg_use_ta_grading'];

            $initial_ta_grading_compare_date = "Due Date (+ max allowed late days)";

            if ($use_ta_grading) {
              $initial_grades_released_compare_date = "TA Grading Open Date";
            } else {
              $initial_grades_released_compare_date = "Due Date";
            }

            $is_repository = $electronic_gradeable['eg_is_repository'];
            $late_days = $electronic_gradeable['eg_late_days'];
            $db->query("SELECT gc_title, gc_ta_comment, gc_student_comment, gc_max_value, gc_is_extra_credit FROM gradeable_component 
                        WHERE g_id=? GROUP BY gc_id ORDER BY gc_order ASC",array($g_id));
            $tmp_questions = $db->rows();
            foreach($tmp_questions as $question){
                array_push($old_questions, array('question_message' => $question['gc_title'],
                                                'question_grading_note' => $question['gc_ta_comment'],
                                                'student_grading_note'  => $question['gc_student_comment'],
                                                'question_total'        => $question['gc_max_value'],
                                                'question_extra_credit' => $question['gc_is_extra_credit']));
            }
       } else {
         // numeric or checkpoint
         $initial_ta_grading_compare_date = "TA Beta Testing Date";
         $initial_grades_released_compare_date = "TA Grading Open Date";
       }

    }
    else{
            $default_late_days = __DEFAULT_HW_LATE_DAYS__;
    }

    $useAutograder = (__USE_AUTOGRADER__) ? "true" : "false";
    $account_subpages_unlock = true;
    
    function selectBox($question, $grade = 0) {
        $retVal ='<input type="number" id="grade-'."{$question}".'" class="points" name="points_'."{$question}".'" value="'."{$grade}".'" min="-1000" max="1000" step="0.5" placeholder="±0.5" onchange="calculatePercentageTotal();" style="width:50px; resize:none;">';
        return $retVal;
    }

    if (!$have_old) {
        $gradeable_name = "";
        $gradeable_submission_id = "";
        $g_instructions_url = "";
        $g_team_assignment = json_encode($old_gradeable['g_team_assignment']);
        $g_grade_by_registration = $old_gradeable['g_grade_by_registration'];
        $string = $button_string = "Add";
        $action = strtolower($string);
    }
    else {
        $gradeable_name = ($have_old_edit) ? $old_gradeable['g_title'] : '';
        $gradeable_submission_id = ($have_old_edit) ? $old_gradeable['g_id']: '';
        $g_instructions_url = $old_gradeable['g_instructions_url'];
        $g_overall_ta_instructions = $old_gradeable['g_overall_ta_instructions'];
        $g_gradeable_type = $old_gradeable['g_gradeable_type'];
        $g_team_assignment = $old_gradeable['g_team_assignment'];
        $g_grade_by_registration = $old_gradeable['g_grade_by_registration'];
        $g_syllabus_bucket = $old_gradeable['g_syllabus_bucket'];
        $g_ta_view_start_date = $old_gradeable['g_ta_view_start_date'];
        $g_grade_start_date = $old_gradeable['g_grade_start_date'];
        $g_grade_released_date = $old_gradeable['g_grade_released_date'];
        $g_min_grading_group = $old_gradeable['g_min_grading_group'];
        
        if ($have_old_edit){
          $string = "Edit";
          $button_string = "Save";
          $action = 'edit';
        }
        else{
          $string = $button_string = "Add";
          $action = strtolower($string);
        }
    }
    
    // for templates 
    if(!$have_old_edit){
      // generate the drop down for all existing gradeables
      $db->query("SELECT g_id, g_title FROM gradeable ORDER BY g_title ASC", array());
      $gradeable_id_title = $db->rows();
    }

    $have_old = json_encode($have_old);
    $old_edit = json_encode($have_old_edit);
    $extra = ($have_old_edit && $has_grades) ? "<span style='color: red;'>(Grading has started! Edit Questions At Own Peril!)</span>" : "";
    print <<<HTML

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
    }

    .question-icon {
        display: block;
        float: left;
        margin-top: 5px;
        margin-left: 5px;
        position: relative;
        width: 12px;
        height: 12px;
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
    
    /* align the radio, buttons and checkboxes with labels */
    input[type="radio"],input[type="checkbox"] {
        margin-top: -1px;
        vertical-align: middle;
    }
    .gradeable_type_options, .upload_type{
        display: none;
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
    
    .required::-webkit-input-placeholder { color: red; }
    .required:-moz-placeholder { color: red; }
    .required::-moz-placeholder { color: red; }
    .required:-ms-input-placeholder { color: red; }
        
</style>

<div id="container-rubric">
    <form id="delete-gradeable" action="{$BASE_URL}/account/submit/admin-gradeables.php?course={$_GET['course']}&semester={$_GET['semester']}&action=delete&id={$old_gradeable['g_id']}" method="post">
        <input type='hidden' class="ignore" name="csrf_token" value="{$_SESSION['csrf']}" />
    </form>
    <form id="gradeable-form" class="form-signin" action="{$BASE_URL}/account/submit/admin-gradeable.php?course={$_GET['course']}&semester={$_GET['semester']}&action={$action}&id={$old_gradeable['g_id']}" 
          method="post" enctype="multipart/form-data" onsubmit="return checkForm()"> 

        <input type='hidden' class="ignore" name="csrf_token" value="{$_SESSION['csrf']}" />
        <div class="modal-header" style="overflow: auto;">
            <h3 id="myModalLabel" style="float: left;">{$string} Gradeable {$extra}</h3>
HTML;
if (!$have_old_edit){
  print <<<HTML
            <div style="padding-left: 200px;">
              From Template: <select name="gradeable_template" style='width: 170px;' value='' >
            </div>
            <option>--None--</option>
HTML;

    foreach ($gradeable_id_title as $g_id_title){
      print <<<HTML
          <option value="{$g_id_title['g_id']}">{$g_id_title['g_title']}</option>
HTML;
    }
  print <<<HTML
          </select>          
HTML;
}
  print <<<HTML
            <button class="btn btn-primary" type="submit" style="margin-right:10px; float: right;">{$button_string} Gradeable</button>
HTML;
    if (false && $have_old_edit) {
        print <<<HTML
                <button type="button" class="btn btn-danger" onclick="deleteForm();" style="margin-right:10px; float: right;">Delete Gradeable</button>
HTML;
    }
    print <<<HTML
        </div>


<div class="modal-body">
<b>Please Read: <a target=_blank href="http://submitty.org/instructor/create_edit_gradeable">Submitty Instructions on "Create or Edit a Gradeable"</a></b>
</div>

        <div class="modal-body" style="/*padding-bottom:80px;*/ overflow:visible;">
            What is the unique id of this gradeable? (e.g., <kbd>hw01</kbd>, <kbd>lab_12</kbd>, or <kbd>midterm</kbd>): <input style='width: 200px' type='text' name='gradeable_id' id="gradeable_id" class="required" value="{$gradeable_submission_id}" placeholder="(Required)"/>
            <br />
            What is the title of this gradeable?: <input style='width: 227px' type='text' name='gradeable_title' class="required" value="{$gradeable_name}" placeholder="(Required)" />
            <br />
            What is the URL to the assignment instructions? (shown to student) <input style='width: 227px' type='text' name='instructions_url' value="{$g_instructions_url}" placeholder="(Optional)" />
            <br />
            What is the <em style='color: orange;'><b>TA Beta Testing Date</b></em>? (gradeable visible to TAs):
            <input name="date_ta_view" id="date_ta_view" class="datepicker" type="text"
            style="cursor: auto; background-color: #FFF; width: 250px;">
            <br />
HTML;
    $team_yes_checked = ($g_team_assignment===true) ? 'checked': '';
    $team_no_checked = ($g_team_assignment===false) ? 'checked': '';
    $type_0_checked = ($g_gradeable_type === 0) ? 'checked': '';
    $type_1_checked = ($g_gradeable_type === 1) ? 'checked': '';
    $type_2_checked = ($g_gradeable_type === 2) ? 'checked': '';
    $upload_files = 'checked'; //($is_repository === false) ? 'checked':'';
    $use_repository = ($is_repository === true) ? 'checked':'';

    print <<<HTML
            Is this a team assignment?:
            <fieldset>
            <input type="radio" id = "team_yes_radio" class="team_yes" name="team_assignment" value="yes" {$team_yes_checked}> Yes
            <input type="radio" class="team_no" name="team_assignment" value ="no" {$team_no_checked}> No
                <div class="team_assignment team_yes" id="team_date">
                    <!--    
                    <br />
                    What is the <em style='color: orange;'><b>Team Finalization Date</b></em>? <input name="date_teams_final" id="date_teams_final" class="datepicker" type="text" style="cursor: auto; background-color: #FFF; width: 250px;">
                    <br />
                    -->
                </div>
                    
                <div class="team_assignment team_no" id="team_no">
                </div>
            </fieldset>

            What is the <a target=_blank href="http://submitty.org/instructor/create_edit_gradeable#types-of-gradeables">type of the gradeable</a>?: <div id="required_type" style="color:red; display:inline;">(Required)</div>

            <fieldset>
                <input type='radio' id="radio_electronic_file" class="electronic_file" name="gradeable_type" value="Electronic File" {$type_0_checked}> 
            Electronic File
            <input type='radio' id="radio_checkpoints" class="checkpoints" name="gradeable_type" value="Checkpoints" {$type_1_checked}>
            Checkpoints
            <input type='radio' id="radio_numeric" class="numeric" name="gradeable_type" value="Numeric" {$type_2_checked}>
            Numeric/Text
            <!-- This is only relevant to Electronic Files -->
            <div class="gradeable_type_options electronic_file" id="electronic_file" >    
                <br />
                What is the <em style='color: orange;'><b>Submission Open Date</b></em>? (submission available to students):
                <input id="date_submit" name="date_submit" class="datepicker" type="text"
                style="cursor: auto; background-color: #FFF; width: 250px;">
                <em style='color: orange;'>must be >= TA Beta Testing Date</em>
                <br />

                What is the <em style='color: orange;'><b>Due Date</b></em>?
                <input id="date_due" name="date_due" class="datepicker" type="text"
                style="cursor: auto; background-color: #FFF; width: 250px;">
                <em style='color: orange;'>must be >= Submission Open Date</em>
                <br />

                How many late days may students use on this assignment? <input style="width: 50px" name="eg_late_days" class="int_val"
                                                                         type="text"/>
                <em style='color: orange;'>NOTE: must be 0 for gradeables with no TA grading</em>
                <br /> <br />
                

                Are students uploading files or commiting code to an SVN repository?<br />
                <fieldset>
                    <input type="radio" class="upload_file" name="upload_type" value="Upload File" {$upload_files}> 
                    Upload File(s)
                    <!--<input type="radio" id="repository_radio" class="upload_repo" name="upload_type" value="Repository" {$use_repository}> 
                    Repository-->
                    
                    <div class="upload_type upload_file" id="upload_file">
                    </div>
                    
                    <div class="upload_type upload_repo" id="repository">
                        <br />
                        Which subdirectory of the repository?<input style='width: 227px' type='text' name='subdirectory' value="src" />
                        <br />
                    </div>
                    
                </fieldset>

		<br />
                <b>Full path to the directory containing the autograding config.json file:</b><br>
                See samples here: <a target=_blank href="https://github.com/Submitty/Tutorial/tree/master/examples">Submitty Tutorial example autograding configurations</a><br>
                See samples here: <a target=_blank href="https://github.com/Submitty/Submitty/tree/master/more_autograding_examples">Additional example autograding configurations</a><br>
		<kbd>/usr/local/submitty/more_autograding_examples/upload_only/config/</kbd>  (for an assignment with no autograding)<br>
		<kbd>/var/local/submitty/private_course_repositories/MY_COURSE_NAME/MY_HOMEWORK_NAME/</kbd> (for a custom autograded homework)<br>
		<kbd>/var/local/submitty/courses/{$_GET['semester']}/{$_GET['course']}/config_upload/#</kbd> (for an web uploaded configuration)<br>

                <input style='width: 83%' type='text' name='config_path' value="" class="required" placeholder="(Required)" />
                <br /> <br />

                Will this assignment also be graded by the TAs?
                <input type="radio" id="yes_ta_grade" name="ta_grading" value="true" class="bool_val rubric_questions"
HTML;
                echo ($use_ta_grading===true)?'checked':'';
        print <<<HTML
                /> Yes
                <input type="radio" id="no_ta_grade" name="ta_grading" value="false"
HTML;
                echo ($use_ta_grading===false)?'checked':'';
        print <<<HTML
                /> No
                <div id="rubric_questions" class="bool_val rubric_questions">

                Point precision (for TA grading): 
                <input style='width: 50px' type='text' name='point_precision' value="0.5" class="float_val" />
                <br /> 
                

                <table class="table table-bordered" id="rubricTable" style=" border: 1px solid #AAA;">
                    <thead style="background: #E1E1E1;">
                        <tr>
                            <th>TA Grading Rubric</th>
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
                                     'question_extra_credit' => 0);
    }

    //this is a hack
    array_unshift($old_questions, "tmp");
    
    foreach ($old_questions as $num => $question) {
        if($num == 0) continue;
        print <<<HTML
            <tr class="rubric-row" id="row-{$num}">
HTML;
        print <<<HTML
                <td style="overflow: hidden;">
                    <textarea name="comment_title_{$num}" rows="1" class="comment_title complex_type" style="width: 99%; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-right: 1px;" 
                              placeholder="Rubric Item Title">{$question['question_message']}</textarea>
                    <textarea name="ta_comment_{$num}" id="individual_{$num}" class="ta_comment complex_type" rows="1" placeholder=" Message to TA (seen only by TAs)"  onkeyup="autoResizeComment(event);"
                                               style="width: 99%; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-bottom: 5px; 
                                               display: block;">{$question['question_grading_note']}</textarea>
                    <textarea name="student_comment_{$num}" id="student_{$num}" class="student_comment complex_type" rows="1" placeholder=" Message to Student (seen by both students and TAs)" onkeyup="autoResizeComment(event);"
                              style="width: 99%; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-bottom: 5px; 
                              display: block;">{$question['student_grading_note']}</textarea>
                </td>

                <td style="background-color:#EEE;">
HTML;
        $old_grade = (isset($question['question_total'])) ? $question['question_total'] : 0;
        print selectBox($num, $old_grade);
        $checked = ($question['question_extra_credit']) ? "checked" : "";
        print <<<HTML
                <br />
                Extra Credit:&nbsp;&nbsp;<input onclick='calculatePercentageTotal();' name="eg_extra_{$num}" type="checkbox" class='eg_extra extra' value='on' {$checked}/>
                <br />
HTML;
        if ($num > 1){
        print <<<HTML
                <a id="delete-{$num}" class="question-icon" onclick="deleteQuestion({$num});">
                <img class="question-icon-cross" src="../toolbox/include/bootstrap/img/glyphicons-halflings.png"></a>
                <a id="down-{$num}" class="question-icon" onclick="moveQuestionDown({$num});">
                <img class="question-icon-down" src="../toolbox/include/bootstrap/img/glyphicons-halflings.png"></a>
        
                <a id="up-{$num}" class="question-icon" onclick="moveQuestionUp({$num});">
                <img class="question-icon-up" src="../toolbox/include/bootstrap/img/glyphicons-halflings.png"></a>
HTML;
        }
        
        print <<<HTML
                </td>
            </tr>
HTML;
    }
        print <<<HTML
            <tr id="add-question">
                <td colspan="2" style="overflow: hidden;">
                    <div class="btn btn-small btn-success" id="rubric-add-button" onclick="addQuestion()"><i class="icon-plus icon-white"></i> Rubric Item</div>
                </td>
            </tr>
HTML;
        print <<<HTML
                    <tr>
                        <td style="background-color: #EEE; border-top: 2px solid #CCC; border-left: 1px solid #EEE;"><strong>TOTAL POINTS</strong></td>
                        <td style="background-color: #EEE; border-top: 2px solid #CCC;"><strong id="totalCalculation"></strong></td>
                    </tr>
                </tbody>
            </table>
            </div>
HTML;
    print <<<HTML
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
                How many numeric items? <input style="width: 50px" id="numeric_num-items" name="num_numeric_items" type="text" value="0" onchange="calculateTotalScore();" class="int_val"/> 
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
                               <input style="width: 200px" name="numeric_label_0" type="text" class="numeric_label" value="0"/> 
                           </td>  
                            <td>     
                                <input style="width: 60px" type="text" name="max_score_0" class="max_score" onchange="calculateTotalScore();" value="0"/> 
                           </td>                           
                           <td>     
                                <input type="checkbox" name="numeric_extra_0" class="numeric_extra extra" onclick="calculateTotalScore();" value=""/>
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
        print <<<HTML
                <option value='{$num}'
HTML;
        echo ($g_min_grading_group === $num)?'selected':'';
        print <<<HTML
            >{$role}</option>
HTML;
    }
    
    print <<<HTML
            </select>
            <br />
            What overall instructions should be provided to the TA?:<br /><textarea rows="4" cols="200" name="ta_instructions" placeholder="(Optional)" style="width: 500px;">
HTML;
    echo htmlspecialchars($g_overall_ta_instructions);
    print <<<HTML
</textarea>
            
            <br />
            <a target=_blank href="http://submitty.org/instructor/create_edit_gradeable#grading-by-registration-section-or-rotating-section">How should TAs be assigned</a> to grade this item?:
            <br />
            <fieldset>
                <input type="radio" name="section_type" value="reg_section"
HTML;
    echo (($have_old && $g_grade_by_registration===true) || $action != 'edit')?'checked':'';
    print <<<HTML
                /> Registration Section
                <input type="radio" name="section_type" value="rotating-section" id="rotating-section" class="graders"
HTML;
    echo ($have_old && $g_grade_by_registration===false)?'checked':'';
    print <<<HTML
                /> Rotating Section
HTML;

    $db->query("SELECT COUNT(*) AS cnt FROM sections_rotating", array());
    $num_rotating_sections = $db->row()['cnt'];
    if ($num_rotating_sections > 0) {
        $all_sections = str_replace(array('[', ']'), '',
            htmlspecialchars(json_encode(range(1,$num_rotating_sections)), ENT_NOQUOTES));
    }
    else {
        $all_sections = "";
    }


    $db->query("
    SELECT 
        u.user_id, array_agg(sections_rotating_id ORDER BY sections_rotating_id ASC) AS sections
    FROM 
        users AS u INNER JOIN grading_rotating AS gr ON u.user_id = gr.user_id
    WHERE 
        g_id=?
    AND 
        u.user_group BETWEEN 1 AND 3
    GROUP BY 
        u.user_id
    ",array($g_id));
    
    $graders_to_sections = array();
    
    foreach($db->rows() as $grader){
        $graders_to_sections[$grader['user_id']] = str_replace(array('[', ']'), '',
                                                   htmlspecialchars(json_encode(pgArrayToPhp($grader['sections'])), ENT_NOQUOTES));
    }
    
print <<<HTML
  <div id="rotating-sections" class="graders" style="display:none; width: 1000px; overflow-x:scroll">
  <br />
  <table id="grader-history" style="border: 3px solid black; display:none;">
HTML;

$db->query("SELECT 
              g_id 
            FROM 
              gradeable 
            WHERE 
              g_grade_by_registration = 'f' 
            ORDER BY 
              g_grade_start_date ASC", array());
              
$gradeables = $db->rows();

// create header 
  print <<<HTML
        <tr>
        <th></th>
HTML;
  
  foreach($gradeables as $row){
    print <<< HTML
      <th style="padding: 8px; border: 3px solid black;">{$row['g_id']}</th>
HTML;
  }

  print <<<HTML
        </tr>
        <tr>
HTML;
  
// get gradeables graded by rotating section in the past and the sections each grader graded
  $db->query("
  SELECT
    gu.g_id, gu.user_id, gu.user_group, gr.sections_rotating_id, g_grade_start_date
  FROM (SELECT g.g_id, u.user_id, u.user_group, g_grade_start_date
          FROM (SELECT user_id, user_group FROM users WHERE user_group BETWEEN 1 AND 3) AS u CROSS JOIN (
            SELECT
              DISTINCT g.g_id,
              g_grade_start_date
            FROM gradeable AS g
            LEFT JOIN
              grading_rotating AS gr ON g.g_id = gr.g_id
            WHERE g_grade_by_registration = 'f') AS g ) as gu
        LEFT JOIN (
              SELECT
                g_id, user_id, array_agg(sections_rotating_id) as sections_rotating_id
              FROM
                grading_rotating
              GROUP BY
              g_id, user_id) AS gr ON gu.user_id=gr.user_id AND gu.g_id=gr.g_id
              ORDER BY user_group, user_id, g_grade_start_date", array());
  
  $last = '';
  
  function display_graders($graders, $have_old, $g_grade_by_registration, $graders_to_sections, $all_sections){
    foreach($graders as $grader){
       print <<<HTML
        <tr>
            <td>{$grader['user_id']}</td>
            <td><input style="width: 227px" type="text" name="grader_{$grader['user_id']}" class="grader" disabled value="
HTML;
        if($have_old && !$g_grade_by_registration) {
            print (isset($graders_to_sections[$grader['user_id']])) ? $graders_to_sections[$grader['user_id']] : '';
        }
        else{
            print $all_sections;
        }
        print <<<HTML
"></td>
        </tr>
HTML;
    }
  }
  
  foreach($db->rows() as $row){
    $new_row = false;
    $u_group = $row['user_group'];
    if (strcmp($row['user_id'],$last) != 0){
      $new_row = true;
    }
    if($new_row){
      print <<<HTML
          </tr>
          <tr class="g_history g_group_{$u_group}">     
          <th style="padding: 8px; border: 3px solid black;">{$row['user_id']}</th>
HTML;
    }
    $sections = implode(", ", pgArrayToPhp($row['sections_rotating_id']));
    print <<<HTML
          <td style="padding: 8px; border: 3px solid black; text-align: center;">{$sections}</td>      
HTML;
    $last = $row['user_id'];
  }
  
  print <<<HTML
            </table>
        <br /> 
        Available rotating sections: {$num_rotating_sections}
        <br /> <br />
        <div id="instructor-graders">
        <table>
                <th>Instructor Graders</th>
HTML;
    $db->query("SELECT user_id FROM users WHERE user_group=? ORDER BY user_id ASC", array(1));
    display_graders($db->rows(), $have_old, $g_grade_by_registration, $graders_to_sections, $all_sections);
    
  print <<<HTML
        </table>
        </div>
        <br />
        <div id="full-access-graders" style="display:none;">
            <table>
                <th>Full Access Graders</th>
HTML;
    
  $db->query("SELECT user_id FROM users WHERE user_group=? ORDER BY user_id ASC", array(2));
  display_graders($db->rows(), $have_old, $g_grade_by_registration, $graders_to_sections, $all_sections);
    
  print <<<HTML
            </table>
HTML;

  print <<<HTML
        </div>
        <div id="limited-access-graders" style="display:none;">
            <br />
            <table>
                <th>Limited Access Graders</th>
HTML;

  $db->query("SELECT user_id FROM users WHERE user_group=? ORDER BY user_id ASC", array(3));
  display_graders($db->rows(), $have_old, $g_grade_by_registration, $graders_to_sections, $all_sections);    
  
    print <<<HTML
        </table>

    </div> 
        <br />
    </div>
    </fieldset>
HTML;

    print <<<HTML
            <!-- TODO default to the submission + late days for electronic -->
            What is the <em style='color: orange;'><b>TA Grading Open Date</b></em>? (TAs may begin grading)
            <input name="date_grade" id="date_grade" class="datepicker" type="text"
            style="cursor: auto; background-color: #FFF; width: 250px;">
              <em style='color: orange;'>must be >= <span id="ta_grading_compare_date">{$initial_ta_grading_compare_date}</span></em>
            <br />
            </div>

            What is the <em style='color: orange;'><b>Grades Released Date</b></em>? (TA grades will be visible to students)
            <input name="date_released" id="date_released" class="datepicker" type="text" 
            style="cursor: auto; background-color: #FFF; width: 250px;">
            <em style='color: orange;'>must be >= <span id="grades_released_compare_date">{$initial_grades_released_compare_date}</span></em>
            <br />
            
            What <a target=_blank href="http://submitty.org/instructor/rainbow_grades">syllabus category</a> does this item belong to?:
            
            <select name="gradeable_buckets" style="width: 170px;">
HTML;

    $valid_assignment_type = array('homework','assignment','problem-set',
                                   'quiz','test','exam',
                                   'exercise','lecture-exercise','reading','lab','recitation', 
                                   'project',                                   
                                   'participation','note',
                                   'none (for practice only)');
    foreach ($valid_assignment_type as $type){
        print <<<HTML
                <option value="{$type}"
HTML;
        echo ($g_syllabus_bucket === $type)?'selected':'';
        $title = ucwords($type);
        print <<<HTML
                >{$title}</option>
HTML;
    }
    print <<<HTML
            </select>
            <br />
            <!-- When the form is completed and the "SAVE GRADEABLE" button is pushed
                If this is an electronic assignment:
                    Generate a new config/class.json
                    NOTE: similar to the current format with this new gradeable and all other electonic gradeables
                    Writes the inner contents for BUILD_csciXXXX.sh script
                    (probably can't do this due to security concerns) Run BUILD_csciXXXX.sh script
                If this is an edit of an existing AND there are existing grades this gradeable
                regenerates the grade reports. And possibly re-runs the generate grade summaries?
            -->
        </div>
        <div class="modal-footer">
                <button class="btn btn-primary" type="submit" style="margin-top: 10px; float: right;">{$button_string} Gradeable</button>
HTML;
    if (false && $have_old_edit) {
        print <<<HTML
                <button type="button" class="btn btn-danger" onclick="deleteForm();" style="margin-top:10px; margin-right: 10px; float: right;">Delete Gradeable</button>
HTML;
    }
    
    print <<<HTML
        </div>
    </form>
</div>

<script type="text/javascript">
    function deleteForm() {
        var r = confirm("Are you sure you want to delete this gradeable?");
        if (r == true) {
            $("#delete-gradeable").submit();
        }
        else {
            return false;
        }
    }

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

    // export to JSON
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

    $('#gradeable-form').on('submit', function(e){
         $('<input />').attr('type', 'hidden')
            .attr('name', 'gradeableJSON')
            .attr('value', JSON.stringify($('form').serializeObject()))
            .appendTo('#gradeable-form');
         if ($("input[name='section_type']:checked").val() == 'reg_section'){
            $('#rotating-sections :input').prop('disabled',true);
         }
    });

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
        
        $('.team_assignment').hide();

        $('.gradeable_type_options').hide();
        
        if ($('input[name=gradeable_type]').is(':checked')){
            $('input[name=gradeable_type]').each(function(){
                if(!($(this).is(':checked')) && {$edit}){
                    $(this).attr("disabled",true);
                }
            });
        }
        
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
        
        function getUrlVars() {
          var vars = {};
          var parts = window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi,    
          function(m,key,value) {
            vars[key] = value;
          });
          return vars;
      }
        
        $('[name="gradeable_template"]').change(
        function(){
          var no_params = window.location.href;
          if(window.location.href.indexOf('&id') >= 0){
            no_params = window.location.href.substring(0,window.location.href.indexOf('&id'));
          }

          var new_url =  no_params;
          
          if($(this).prop('selectedIndex')> 0){
            new_url += '&id='+this.value + '&action=template';
          }
          
          window.location.href = new_url;
        });
        
        if({$have_old} && !{$old_edit}){
          $('[name="gradeable_template"]').val(getUrlVars()['id']);
        }
        
        if ($('input:radio[name="ta_grading"]:checked').attr('value') === 'false') {
            $('#rubric_questions').hide();
            $('#grading_questions').hide();
        }
        
        $('input:radio[name="ta_grading"]').change(function(){
            $('#rubric_questions').hide();
            $('#grading_questions').hide();
            if ($(this).is(':checked')){
                if($(this).val() == 'true'){ 
                    $('#rubric_questions').show();
                    $('#grading_questions').show();
                    $('#grades_released_compare_date').html('TA Grading Open Date');
                } else {
                    $('#grades_released_compare_date').html('Due Date');
                }
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
        
        if({$default_late_days} != -1){
            $('input[name=eg_late_days]').val('{$default_late_days}');
        }

        if($('#team_yes_radio').is(':checked')){
            $('#team_date').show();
        }
        
        if($('#radio_electronic_file').is(':checked')){ 
            $('input[name=date_submit]').datetimepicker('setDate', createCrossBrowserJSDate("{$electronic_gradeable['eg_submission_open_date']}"));
            $('input[name=date_due]').datetimepicker('setDate', createCrossBrowserJSDate("{$electronic_gradeable['eg_submission_due_date']}"));
            $('input[name=subdirectory]').val('{$electronic_gradeable['eg_subdirectory']}');
            $('input[name=config_path]').val('{$electronic_gradeable['eg_config_path']}');
            $('input[name=eg_late_days]').val('{$electronic_gradeable['eg_late_days']}');
            $('input[name=point_precision]').val('{$electronic_gradeable['eg_precision']}');
            
            if($('#repository_radio').is(':checked')){
                $('#repository').show();
            }
            
            $('#electronic_file').show();
            
            if ($('input:radio[name="ta_grading"]:checked').attr('value') === 'false') {
                $('#rubric_questions').hide();
                $('#grading_questions').hide();
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
            $('input[name=gradeable_id]').attr('readonly', true);
        }
    });

    var datepicker = $('.datepicker');
    datepicker.datetimepicker({
        timeFormat: "HH:mm:ss",
        showTimezone: false
    });
    
    if(!{$have_old}){
        $('#date_submit').datetimepicker('setDate', createCrossBrowserJSDate("{$electronic_gradeable['eg_submission_open_date']}"));
        $('#date_due').datetimepicker('setDate', createCrossBrowserJSDate("{$electronic_gradeable['eg_submission_due_date']}"));
    }

    $('#date_ta_view').datetimepicker('setDate', createCrossBrowserJSDate("{$old_gradeable['g_ta_view_start_date']}"));
    $('#date_grade').datetimepicker('setDate', createCrossBrowserJSDate("{$old_gradeable['g_grade_start_date']}"));
    $('#date_released').datetimepicker('setDate', createCrossBrowserJSDate("{$old_gradeable['g_grade_released_date']}"));

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
    
    if({$have_old}){
        $('#required_type').hide();
    }

    // Shows the radio inputs dynamically
    $('input:radio[name="team_assignment"]').change(
    function(){
        $('.team_assignment').hide();
        if ($(this).is(':checked')){ 
            if($(this).val() == 'yes'){ 
                $('#team_date').show();
            }
            else if ($(this).val() == 'no'){ 
                $('#team_no').show();
            }
        }
    });

    // Shows the radio inputs dynamically
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
                   $('#grades_released_compare_date').html('Due Date');
                } else { 
                   $('#grades_released_compare_date').html('TA Grading Open Date');
                }
            }
            else if ($(this).val() == 'Checkpoints'){ 
                $('#checkpoints').show();
                $('#grading_questions').show();
                $('#ta_grading_compare_date').html('TA Beta Testing Date');
                $('#grades_released_compare_date').html('TA Grading Open Date');
            }
            else if ($(this).val() == 'Numeric'){ 
                $('#numeric').show();
                $('#grading_questions').show();
                $('#ta_grading_compare_date').html('TA Beta Testing Date');
                $('#grades_released_compare_date').html('TA Grading Open Date');
            }
        }
    });
    
    // Shows the radio inputs dynamically
    $('input:radio[name="upload_type"]').change(
    function(){
        $('.upload_type').hide();
        if ($(this).is(':checked')){ 
            if($(this).val() == 'Upload File'){ 
                $('#upload_file').show();
            }
            else if ($(this).val() == 'Repository'){ 
                $('#repository').show();
            }
        }
    });

    function addQuestion(){
        //get the last question number
        var num = parseInt($('.rubric-row').last().attr('id').split('-')[1]);
        var newQ = num+1;
        var sBox = selectBox(newQ);
        $('#row-'+num).after('<tr class="rubric-row" id="row-'+newQ+'"> \
            <td style="overflow: hidden;"> \
                <textarea name="comment_title_'+newQ+'" rows="1" class="comment_title complex_type" style="width: 99%; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-right: 1px;" placeholder="Rubric Item Title"></textarea> \
                <textarea name="ta_comment_'+newQ+'" id="individual_'+newQ+'" rows="1" class="ta_comment complex_type" placeholder=" Message to TA (seen only by TAs)"  onkeyup="autoResizeComment(event);" \
                          style="width: 99%; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-bottom: 5px;"></textarea> \
                <textarea name="student_comment_'+newQ+'" id="student_'+newQ+'" rows="1" class="student_comment complex_type" placeholder=" Message to Student (seen by both students and TAs)"  onkeyup="autoResizeComment(event);" \
                          style="width: 99%; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-bottom: 5px;"></textarea> \
            </td> \
            <td style="background-color:#EEE;">' + sBox + ' \
                <br /> \
                Extra Credit:&nbsp;&nbsp;<input onclick="calculatePercentageTotal();" name="eg_extra_'+newQ+'" type="checkbox" class="eg_extra extra" value="on"/> \
                <br /> \
                <a id="delete-'+newQ+'" class="question-icon" onclick="deleteQuestion('+newQ+');"> \
                    <img class="question-icon-cross" src="../toolbox/include/bootstrap/img/glyphicons-halflings.png"></a> \
                <a id="down-'+newQ+'" class="question-icon" onclick="moveQuestionDown('+newQ+');"> \
                    <img class="question-icon-down" src="../toolbox/include/bootstrap/img/glyphicons-halflings.png"></a> \
                <a id="up-'+newQ+'" class="question-icon" onclick="moveQuestionUp('+newQ+');"> \
                <img class="question-icon-up" src="../toolbox/include/bootstrap/img/glyphicons-halflings.png"></a> \
            </td> \
        </tr>');
    }
    
    // autoresize the comment
    function autoResizeComment(e){
        e.target.style.height ="";
        e.target.style.height = e.target.scrollHeight + "px";
    }

    function selectBox(question){
        // should be the increment value
        return '<input type="number" id="grade-'+question+'" class="points" name="points_' + question +'" value="0" min="-1000" max="1000" step="0.5" placeholder="±0.5" onchange="calculatePercentageTotal();" style="width:50px; resize:none;">';
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
        row.find('select[name=points_' + oldNum + ']').attr('name', 'points_' + newNum);
        row.find('input[name=eg_extra_' + oldNum + ']').attr('name', 'eg_extra_' + newNum);
        row.find('a[id=delete-' + oldNum + ']').attr('id', 'delete-' + newNum).attr('onclick', 'deleteQuestion(' + newNum + ')');
        row.find('a[id=down-' + oldNum + ']').attr('id', 'down-' + newNum).attr('onclick', 'moveQuestionDown(' + newNum + ')');
        row.find('a[id=up-' + oldNum + ']').attr('id', 'up-' + newNum).attr('onclick', 'moveQuestionUp(' + newNum + ')');
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

        var temp = currentRow.children()[child].children[0].value;
        currentRow.children()[child].children[0].value = newRow.children()[0].children[0].value;
        newRow.children()[0].children[0].value = temp;

        temp = currentRow.children()[child].children[2].value;
        currentRow.children()[child].children[2].value = newRow.children()[0].children[2].value;
        newRow.children()[0].children[2].value = temp;

        child += 1;

        temp = currentRow.children()[child].children[0].value;
        currentRow.children()[child].children[0].value = newRow.children()[1].children[0].value;
        newRow.children()[1].children[0].value = temp;

        temp = currentRow.children()[child].children[1].checked;
        currentRow.children()[child].children[1].checked = newRow.children()[1].children[1].checked;
        newRow.children()[1].children[1].checked = temp;
    }

    function moveQuestionUp(question) {
        if (question < 1) {
            return;
        }

        var currentRow = $('tr#row-' + question);
        var newRow = $('tr#row-' + (question-1));
        var child = 0;

        var temp = currentRow.children()[0].children[0].value;
        currentRow.children()[0].children[0].value = newRow.children()[child].children[0].value;
        newRow.children()[child].children[0].value = temp;

        temp = currentRow.children()[0].children[2].value;
        currentRow.children()[0].children[2].value = newRow.children()[child].children[2].value;
        newRow.children()[child].children[2].value = temp;

        child += 1;

        temp = currentRow.children()[1].children[0].value;
        currentRow.children()[1].children[0].value = newRow.children()[child].children[0].value;
        newRow.children()[child].children[0].value = temp;

        temp = currentRow.children()[1].children[1].checked;
        currentRow.children()[1].children[1].checked = newRow.children()[child].children[1].checked;
        newRow.children()[child].children[1].checked = temp;
    }

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

    //checks the form to see if it is valid
    function checkForm()
    {
        var gradeable_id = $('#gradeable_id').val();
        var date_submit = Date.parse($('#date_submit').val());
        var date_due = Date.parse($('#date_due').val());
        var date_ta_view = Date.parse($('#date_ta_view').val());
        var date_grade = Date.parse($('#date_grade').val());
        var date_released = Date.parse($('#date_released').val());
        var config_path = $('input[name=config_path]').val();
        var has_space = gradeable_id.includes(" ");
        var test = /^[a-zA-Z0-9_-]*$/.test(gradeable_id);
        var unique_gradeable = false;
        var bad_max_score = false;
        var check1 = document.getElementById('radio_electronic_file').checked;
        var check2 = document.getElementById('radio_checkpoints').checked;
        var check3 = document.getElementById('radio_numeric').checked;

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
            if(config_path == "" || config_path === null) {
                alert("The config path should not be empty");
                return false;
            }
        }
        if ($('input:radio[name="ta_grading"]:checked').attr('value') === 'true') {
            if(date_grade < date_due) {
                alert("DATE CONSISTENCY:  TA Grading Open Date must be >= Due Date");
                return false;
            }
            if(date_released < date_due) {
                alert("DATE CONSISTENCY:  Grades Released Date must be >= TA Grading Open Date");
                return false;
            }
        }
        else {
            if(check1) {
                if(date_released < date_due) {
                    alert("DATE CONSISTENCY:  Grades Released Date must be >= Due Date");
                    return false;
                }
            }
        }
        if($('input:radio[name="ta_grading"]:checked').attr('value') === 'true' || check2 || check3) {
            if(date_grade < date_ta_view) {
                alert("DATE CONSISTENCY:  TA Grading Open Date must be >= TA Beta Testing Date");
                return false;
            }
            if(date_released < date_grade) {
                alert("DATE CONSISTENCY:  Grade Released Date must be >= TA Grading Open Date");
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
        if(check3) {
            for (i = 0; i < $('#numeric_num-items').val(); i++) {
                numOfNumeric++;
                if ($('#mult-field-' + numOfNumeric,wrapper).find('.max_score').attr('name','max_score_'+numOfNumeric).val() == 0) {
                    alert("Max score cannot be 0 [Question "+ numOfNumeric + "]");
                    return false;
                }
            }
        }
        
    }

    }
    calculateTotalScore();
    calculatePercentageTotal();
    </script>
HTML;
	print <<<HTML
<div id="alert-message" title="WARNING">
  <p>Gradeable ID must not be blank and only contain characters <strong> a-z A-Z 0-9 _ - </strong> </p>
</div>
HTML;

}

include "../footer.php";
