<?php

namespace app\views\admin;

use app\views\AbstractView;

class AdminGradeableView extends AbstractView {
	public function show_add_gradeable($have_old_edit) {
        $electronic_gradeable = array();
        $electronic_gradeable['eg_submission_open_date'] = date('Y/m/d 23:59:59', strtotime( '0 days' )); //"";
        $electronic_gradeable['eg_submission_due_date'] = date('Y/m/d 23:59:59', strtotime( '+7 days' )); //"";"";
        $electronic_gradeable['eg_subdirectory'] = "";
        $electronic_gradeable['eg_config_path'] = "";
        $electronic_gradeable['eg_late_days'] = 2;
        $electronic_gradeable['eg_precision'] = 0.5;
        $default_late_days = 2;
		$BASE_URL = "http://192.168.56.101/hwgrading";
		$action = "add";
		$string = "Add"; //Add or edit
		$button_string = "add";
		$extra = "";
		$gradeable_submission_id = "";
		$gradeable_name = "";
		$g_instructions_url = "";
		$g_gradeable_type = 0;
		$is_repository = false;
		$use_ta_grading=true;
        $old_questions = array("Apple", "Pen", "Applepen");
        $g_min_grading_group = 0;
        $g_overall_ta_instructions = "";
        $have_old = false;
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
    .required:-ms-input-placeholder { color: red; 
        
</style>
<div id="container-rubric">
    <form id="delete-gradeable" action="{$BASE_URL}/account/submit/admin-gradeables.php?course={$_GET['course']}&semester={$_GET['semester']}&action=delete&id=-1" method="post">
        <input type='hidden' class="ignore" name="csrf_token" value="{$_SESSION['csrf']}" />
    </form>
    <form id="gradeable-form" class="form-signin" action="{$BASE_URL}/account/submit/admin-gradeable.php?course={$_GET['course']}&semester={$_GET['semester']}&action={$action}&id=-1" 
          method="post" enctype="multipart/form-data" onsubmit=""> 

        <input type='hidden' class="ignore" name="csrf_token" value="{$_SESSION['csrf']}" />
        <div class="modal-header" style="overflow: auto;">
            <h3 id="myModalLabel" style="float: left;">{$string} Gradeable {$extra}</h3>
HTML;
if (!$have_old_edit){
  $html_output .= <<<HTML
            <div style="padding-left: 200px;">
              From Template: <select name="gradeable_template" style='width: 170px;' value='' >
            </div>
            <option>--None--</option>
HTML;

//    foreach ($gradeable_id_title as $g_id_title){
//     $html_output .= <<<HTML
//        <option value="{$g_id_title['g_id']}">{$g_id_title['g_title']}</option>
//HTML;
//    }
  $html_output .= <<<HTML
          </select>          
HTML;
}
  $html_output .= <<<HTML
            <button class="btn btn-primary" type="submit" style="margin-right:10px; float: right;">{$button_string} Gradeable</button>
HTML;
    if (false && $have_old_edit) {
        $html_output .= <<<HTML
                <button type="button" class="btn btn-danger" onclick="deleteForm();" style="margin-right:10px; float: right;">Delete Gradeable</button>
HTML;
    }
    $html_output .= <<<HTML
        </div>


<div class="modal-body">
<b>Please Read: <a target=_blank href="http://submitty.org/instructor/create_edit_gradeable">Submitty Instructions on "Create or Edit a Gradeable"</a></b>
</div>

		<div class="modal-body" style="/*padding-bottom:80px;*/ overflow:visible;">
            What is the unique id of this gradeable? (e.g., <kbd>hw01</kbd>, <kbd>lab_12</kbd>, or <kbd>midterm</kbd>): <input style='width: 200px' type='text' name='gradeable_id' id="gradeable_id"class="required" value="{$gradeable_submission_id}" placeholder="(Required)"/>
            <br />
            What is the title of this gradeable?: <input style='width: 227px' type='text' name='gradeable_title' class="required" value="{$gradeable_name}" placeholder="(Required)" />
            <br />
            What is the URL to the assignment instructions? (shown to student) <input style='width: 227px' type='text' name='instructions_url' value="{$g_instructions_url}" placeholder="(Optional)" />
            <br />
            What is the <em style='color: orange;'><b>TA Beta Testing Date</b></em>? (gradeable visible to TAs):
            <input name="date_ta_view" id="date_ta_view" class="datepicker" type="text"
            style="cursor: auto; background-color: #FFF; width: 250px;">
            <br />
            <br />   
            What is the <a target=_blank href="http://submitty.org/instructor/create_edit_gradeable#types-of-gradeables">type of the gradeable</a>?: <div id="required_type" style="color:red; display:inline;">(Required)</div>

            <fieldset>
                <input type='radio' id="radio_electronic_file" class="electronic_file" name="gradeable_type" value="Electronic File"
HTML;
    //echo ($g_gradeable_type === 0)?'checked':'';
    $html_output .= <<<HTML
            > 
            Electronic File
            <input type='radio' id="radio_checkpoints" class="checkpoints" name="gradeable_type" value="Checkpoints"
HTML;
            //echo ($g_gradeable_type === 1)?'checked':'';
    $html_output .= <<<HTML
            >
            Checkpoints
            <input type='radio' id="radio_numeric" class="numeric" name="gradeable_type" value="Numeric"
HTML;
            //echo ($g_gradeable_type === 2)?'checked':'';
    $html_output .= <<<HTML
            >
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
                    <input type="radio" class="upload_file" name="upload_type" value="Upload File"
HTML;
                    //echo ($is_repository === false)?'checked':'';
        $html_output .= <<<HTML
                    > Upload File(s)
                    <input type="radio" id="repository_radio" class="upload_repo" name="upload_type" value="Repository"
HTML;
                    //echo ($is_repository===true)?'checked':'';
        $html_output .= <<<HTML
                    > Repository
                    
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
                See samples here: <a target=_blank href="https://github.com/Submitty/Submitty/tree/master/sample_files/sample_assignment_config">Submitty GitHub sample assignment configurations</a><br>
		<kbd>/usr/local/submitty/sample_files/sample_assignment_config/no_autograding/</kbd>  (for an upload only homework)<br>
		<kbd>/var/local/submitty/private_course_repositories/MY_COURSE_NAME/MY_HOMEWORK_NAME/</kbd> (for a custom autograded homework)<br>
		<kbd>/var/local/submitty/courses/{$_GET['semester']}/{$_GET['course']}/config_upload/#</kbd> (for an web uploaded configuration)<br>

                <input style='width: 83%' type='text' name='config_path' value="" class="required" placeholder="(Required)" />
                <br /> <br />

                Will this assignment also be graded by the TAs?
                <input type="radio" id="yes_ta_grade" name="ta_grading" value="true" class="bool_val rubric_questions"
HTML;
                //echo ($use_ta_grading===true)?'checked':'';
        $html_output .= <<<HTML
                /> Yes
                <input type="radio" id="no_ta_grade" name="ta_grading" value="false"
HTML;
                //echo ($use_ta_grading===false)?'checked':'';
        $html_output .= <<<HTML
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
        $html_output .= <<<HTML
            <tr class="rubric-row" id="row-{$num}">
HTML;
        $html_output .= <<<HTML
                <td style="overflow: hidden;">
                    <textarea name="comment_title_{$num}" rows="1" class="comment_title complex_type" style="width: 99%; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-right: 1px;" 
                              placeholder="Rubric Item Title"> Comment Title Num Placeholder</textarea>
                    <textarea name="ta_comment_{$num}" id="individual_{$num}" class="ta_comment complex_type" rows="1" placeholder=" Message to TA (seen only by TAs)"  onkeyup="autoResizeComment(event);"
                                               style="width: 99%; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-bottom: 5px; 
                                               display: block;">Question Grading Note Placeholder</textarea>
                    <textarea name="student_comment_{$num}" id="student_{$num}" class="student_comment complex_type" rows="1" placeholder=" Message to Student (seen by both students and TAs)" onkeyup="autoResizeComment(event);"
                              style="width: 99%; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-bottom: 5px; 
                              display: block;">Student Grading Note Placeholder</textarea>
                </td>

                <td style="background-color:#EEE;">
HTML;
        $old_grade = (isset($question['question_total'])) ? $question['question_total'] : 0;
        //$html_output .= selectBox($num, $old_grade);
        //$checked = ($question['question_extra_credit']) ? "checked" : "";
        $checked = "";
        $html_output .= <<<HTML
                <br />
                Extra Credit:&nbsp;&nbsp;<input onclick='calculatePercentageTotal();' name="eg_extra_{$num}" type="checkbox" class='eg_extra extra' value='on' {$checked}/>
                <br />
HTML;
        if ($num > 1){
        $html_output .= <<<HTML
                <a id="delete-{$num}" class="question-icon" onclick="deleteQuestion({$num});">
                <img class="question-icon-cross" src="../toolbox/include/bootstrap/img/glyphicons-halflings.png"></a>
                <a id="down-{$num}" class="question-icon" onclick="moveQuestionDown({$num});">
                <img class="question-icon-down" src="../toolbox/include/bootstrap/img/glyphicons-halflings.png"></a>
        
                <a id="up-{$num}" class="question-icon" onclick="moveQuestionUp({$num});">
                <img class="question-icon-up" src="../toolbox/include/bootstrap/img/glyphicons-halflings.png"></a>
HTML;
        }
        
        $html_output .= <<<HTML
                </td>
            </tr>
HTML;
    }
        $html_output .= <<<HTML
            <tr id="add-question">
                <td colspan="2" style="overflow: hidden;">
                    <div class="btn btn-small btn-success" id="rubric-add-button" onclick="addQuestion()"><i class="icon-plus icon-white"></i> Rubric Item</div>
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
                How many numeric items? <input style="width: 50px" id="numeric_num-items" name="num_numeric_items" type="text" value="0" class="int_val"/> 
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
                        <tbody style="background: #f9f9f9;">
                        <!-- This is a bit of a hack, but it works (^_^) -->
                        <tr class="multi-field" id="mult-field-0" style="display:none;">
                           <td>
                               <input style="width: 200px" name="numeric_label_0" type="text" class="numeric_label" value="0"/> 
                           </td>  
                            <td>     
                                <input style="width: 60px" type="text" name="max_score_0" class="max_score" value="0" /> 
                           </td>                           
                           <td>     
                                <input type="checkbox" name="numeric_extra_0" class="numeric_extra extra" value="" />
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
        echo ($g_min_grading_group === $num)?'selected':'';
        $html_output .= <<<HTML
            >{$role}</option>
HTML;
    }
    
    $html_output .= <<<HTML
            </select>
            <br />
            What overall instructions should be provided to the TA?:<br /><textarea rows="4" cols="200" name="ta_instructions" placeholder="(Optional)" style="width: 500px;">
HTML;
    echo htmlspecialchars($g_overall_ta_instructions);
    $html_output .= <<<HTML
</textarea>
            
            <br />
            <a target=_blank href="http://submitty.org/instructor/create_edit_gradeable#grading-by-registration-section-or-rotating-section">How should TAs be assigned</a> to grade this item?:
            <br />
            <fieldset>
                <input type="radio" name="section_type" value="reg_section"
HTML;
    //echo (($have_old && $g_grade_by_registration===true) || $action != 'edit')?'checked':'';
    $html_output .= <<<HTML
                /> Registration Section
                <input type="radio" name="section_type" value="rotating-section" id="rotating-section" class="graders"
HTML;
    //echo ($have_old && $g_grade_by_registration===false)?'checked':'';
    $html_output .= <<<HTML
                /> Rotating Section
HTML;
$html_output .= <<<HTML
  <div id="rotating-sections" class="graders" style="display:none; width: 1000px; overflow-x:scroll">
  <br />
  <table id="grader-history" style="border: 3px solid black; display:none;">
HTML;
$html_output .= <<<HTML
        <tr>
        <th></th>
HTML;
  /*
  foreach($gradeables as $row){
    $html_output .= <<< HTML
      <th style="padding: 8px; border: 3px solid black;">{$row['g_id']}</th>
HTML;
  }
  */

  $html_output .= <<<HTML
        </tr>
        <tr>
HTML;
function display_graders($graders, $have_old, $g_grade_by_registration, $graders_to_sections, $all_sections){
    foreach($graders as $grader){
       $html_output .= <<<HTML
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
  
  /*
  foreach($db->rows() as $row){
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
    //$sections = implode(", ", pgArrayToPhp($row['sections_rotating_id']));
    $html_output .= <<<HTML
          <td style="padding: 8px; border: 3px solid black; text-align: center;">sections</td>      
HTML;
    $last = $row['user_id'];
  }
  */
  $html_output .= <<<HTML
            </table>
        <br /> 
        Available rotating sections: num_rotating_sections
        <br /> <br />
        <div id="instructor-graders">
        <table>
                <th>Instructor Graders</th>
HTML;
    //$db->query("SELECT user_id FROM users WHERE user_group=? ORDER BY user_id ASC", array(1));
    //display_graders($db->rows(), $have_old, $g_grade_by_registration, $graders_to_sections, $all_sections);
    
  $html_output .= <<<HTML
        </table>
        </div>
        <br />
        <div id="full-access-graders" style="display:none;">
            <table>
                <th>Full Access Graders</th>
HTML;
    
  //$db->query("SELECT user_id FROM users WHERE user_group=? ORDER BY user_id ASC", array(2));
  //display_graders($db->rows(), $have_old, $g_grade_by_registration, $graders_to_sections, $all_sections);
    
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

  //$db->query("SELECT user_id FROM users WHERE user_group=? ORDER BY user_id ASC", array(3));
  //display_graders($db->rows(), $have_old, $g_grade_by_registration, $graders_to_sections, $all_sections);    
  
    $html_output .= <<<HTML
        </table>

    </div> 
        <br />
    </div>
    </fieldset>
HTML;

    $html_output .= <<<HTML
            <!-- TODO default to the submission + late days for electronic -->
            What is the <em style='color: orange;'><b>TA Grading Open Date</b></em>? (TAs may begin grading)
            <input name="date_grade" id="date_grade" class="datepicker" type="text"
            style="cursor: auto; background-color: #FFF; width: 250px;">
              <em style='color: orange;'>must be >= <span id="ta_grading_compare_date">initial_ta_grading_compare_date</span></em>
            <br />
            </div>

            What is the <em style='color: orange;'><b>Grades Released Date</b></em>? (TA grades will be visible to students)
            <input name="date_released" id="date_released" class="datepicker" type="text" 
            style="cursor: auto; background-color: #FFF; width: 250px;">
            <em style='color: orange;'>must be >= <span id="grades_released_compare_date">initial_grades_released_compare_date</span></em>
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
        //echo ($g_syllabus_bucket === $type)?'selected':'';
        $title = ucwords($type);
        $html_output .= <<<HTML
                >{$title}</option>
HTML;
    }
    $html_output .= <<<HTML
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
        $html_output .= <<<HTML
                <button type="button" class="btn btn-danger" onclick="deleteForm();" style="margin-top:10px; margin-right: 10px; float: right;">Delete Gradeable</button>
HTML;
    }
    
    $html_output .= <<<HTML
        </div>
    </form>
</div>

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
        
        if({$default_late_days} != -1){
            $('input[name=eg_late_days]').val('{$default_late_days}');
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
            $('#checkpoints').show();
            $('#grading_questions').show();
        }
        else if ($('#radio_numeric').is(':checked')){ 
            $('#numeric').show();
            $('#grading_questions').show();
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

    var datepicker = $('.datepicker');
    datepicker.datetimepicker({
        timeFormat: "HH:mm:ss",
        showTimezone: false
    });

    $('#date_submit').datetimepicker('setDate', createCrossBrowserJSDate("{$electronic_gradeable['eg_submission_open_date']}"));
    $('#date_due').datetimepicker('setDate', createCrossBrowserJSDate("{$electronic_gradeable['eg_submission_due_date']}"));

 $.fn.serializeObject = function(){
        var o = {};
        var a = this.serializeArray();
        var ignore = ["numeric_label_0", "max_score_0", "numeric_extra_0", "numeric_extra_0",
                       "text_label_0", "checkpoint_label_0", "num_numeric_items", "num_text_items"];
                       
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
        alert(ignore);
        
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
    }
</script>
HTML;

	return $html_output;
	}



}