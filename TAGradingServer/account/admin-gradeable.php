<?php
use \lib\Database;
use \lib\Functions;

include "../header.php";

check_administrator();

if($user_is_administrator){
    $have_old = $has_grades = false;
    $current_date = date('Y/m/d 23:59:59');
    $yesterday = date('Y/m/d 23:59:59', strtotime( '-1 days' ));
    $old_gradeable = array(
        'g_id' => -1,
        'g_title' => "",
        'g_overall_ta_instructions' => '',
        'g_team_assignment' => false,
        'g_gradeable_type' => 0,
        'g_grade_by_registration' => false,
        'g_grade_start_date' => date('Y/m/d 23:59:59', strtotime( '+7 days' )),
        'g_grade_released_date' => date('Y/m/d 23:59:59', strtotime( '+14 days' )),
        'g_syllabus_bucket' => '',
        'g_min_grading_group' => ''
    );
    $old_questions = $old_components = $electronic_gradeable = array();
    $num_numeric = $num_text = 0;
    $g_gradeable_type = $is_repository = $g_syllabus_bucket = $g_min_grading_group = $default_late_days = -1;
    $use_ta_grading = true;
    $g_overall_ta_instructions = $g_id = '';
    $edit = json_encode(isset($_GET['action']) && $_GET['action'] == 'edit');
    
    if (isset($_GET['action']) && $_GET['action'] == 'edit') {
        $g_id = $_GET['id'];
        Database::query("SELECT * FROM gradeable WHERE g_id=?",array($g_id));
        if (count(Database::rows()) == 0) {
            die("No gradeable found");
        }
        $old_gradeable = Database::row();
        Database::query("SELECT * FROM gradeable_component WHERE g_id=? ORDER BY gc_order", array($g_id));
        $old_components = json_encode(Database::rows());
        $have_old = true;
        
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
       
       //if electonic file then add all of the old questions
       if($old_gradeable['g_gradeable_type']==0){
            //get the electronic file stuff
            $db->query("SELECT * FROM electronic_gradeable WHERE g_id=?", array($g_id));
            $electronic_gradeable = $db->row();
            $use_ta_grading = $electronic_gradeable['eg_use_ta_grading'];
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
       }
    }
    else{
            $default_late_days = __DEFAULT_LATE_DAYS__;
    }

    $useAutograder = (__USE_AUTOGRADER__) ? "true" : "false";
    $account_subpages_unlock = true;
    
    function selectBox($question, $grade = 0) {
        $retVal = "<select name='points_{$question}' class='points' onchange='calculatePercentageTotal();'>";
        for($i = -100; $i <= 100; $i += 0.5) {
            $selected = ($grade == $i) ? "selected" : "";
            $retVal .= "<option {$selected}>{$i}</option>";
        }
        $retVal .= "</select>";
        return $retVal;
    }

    if (!$have_old) {
        $gradeable_name = "";
        $gradeable_submission_id = "";
        $g_team_assignment = json_encode($old_gradeable['g_team_assignment']);
        $g_grade_by_registration = $old_gradeable['g_grade_by_registration'];
        $string = "Add";
        $action = strtolower($string);
    }
    else {
        $gradeable_name = $old_gradeable['g_title'];
        $gradeable_submission_id = $old_gradeable['g_id'];
        $g_overall_ta_instructions = $old_gradeable['g_overall_ta_instructions'];
        $g_gradeable_type = $old_gradeable['g_gradeable_type'];
        $g_team_assignment = $old_gradeable['g_team_assignment'];
        $g_grade_by_registration = $old_gradeable['g_grade_by_registration'];
        $g_syllabus_bucket = $old_gradeable['g_syllabus_bucket'];
        $g_grade_start_date = $old_gradeable['g_grade_start_date'];
        $g_grade_released_date = $old_gradeable['g_grade_released_date'];
        $g_min_grading_group = $old_gradeable['g_min_grading_group'];
        $string = "Save";
        $action = 'edit';
    }

    $have_old = json_encode($have_old);
    $extra = ($has_grades) ? "<span style='color: red;'>(Grading has started! Edit Questions At Own Peril!)</span>" : "";
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
    .required:-ms-input-placeholder { color: red; 
        
</style>

<div id="container-rubric">
    <form id="gradeable-form" class="form-signin" action="{$BASE_URL}/account/submit/admin-gradeable.php?action={$action}&id={$old_gradeable['g_id']}" 
          method="post" enctype="multipart/form-data"> 

        <input type='hidden' class="ignore" name="csrf_token" value="{$_SESSION['csrf']}" />
        <div class="modal-header" style="overflow: auto;">
            <h3 id="myModalLabel" style="float: left;">{$string} Gradeable {$extra}</h3>
            <button class="btn btn-primary" type="submit" style="margin-right:10px; float: right;">{$string} Gradeable</button>
        </div>
        <div class="modal-body" style="/*padding-bottom:80px;*/ overflow:visible;">
            What is the unique id of this gradeable?: <input style='width: 200px' type='text' name='gradeable_id' class="required" value="{$gradeable_submission_id}" placeholder="(Required)"/>
            <br />
            What is the title of this gradeable?: <input style='width: 227px' type='text' name='gradeable_title' class="required" value="{$gradeable_name}" placeholder="(Required)" />
            <br />
            
       <!-- <br />
        Is this a team assignment?:
        <input type="radio" name="team_assignment" value="yes"
HTML;
    
    echo ($g_team_assignment===true)?'checked':''; 
    print <<<HTML
        > Yes
            <input type="radio" name="team_assignment" value ="no" 
HTML;
    echo ($g_team_assignment===false)?'checked':'' ;
    print <<<HTML
            > No -->
            <br />   
            What is the type of your gradeable?: <div id="required_type" style="color:red; display:inline;">(Required)</div>

            <fieldset>
                <input type='radio' id="radio_electronic_file" class="electronic_file" name="gradeable_type" value="Electronic File"
HTML;
    echo ($g_gradeable_type === 0)?'checked':'';
    print <<<HTML
            > 
            Electronic File
            <input type='radio' id="radio_checkpoints" class="checkpoints" name="gradeable_type" value="Checkpoints"
HTML;
            echo ($g_gradeable_type === 1)?'checked':'';
    print <<<HTML
            >
            Checkpoints
            <input type='radio' id="radio_numeric" class="numeric" name="gradeable_type" value="Numeric"
HTML;
            echo ($g_gradeable_type === 2)?'checked':'';
    print <<<HTML
            >
            Numeric/Text
            <!-- This is only relevant to Electronic Files -->
            <div class="gradeable_type_options electronic_file" id="electronic_file" >    
                <br />
                What date does the submission open to students?: <input id="date_submit" name="date_submit" class="datepicker" type="text"
                style="cursor: auto; background-color: #FFF; width: 250px;">
                <br />
                What is the URL to the assignment instructions? (shown to student) 
                <input style='width: 227px' type='text' name='instructions_url' placeholder="(Optional)" value="" />
                <br />
                What is the due date? <input id="date_due" name="date_due" class="datepicker" type="text"
                style="cursor: auto; background-color: #FFF; width: 250px;">
                <br />
                How many late days may students use on this assignment? <input style="width: 50px" name="eg_late_days" class="int_val"
                                                                         type="text"/>
                <br/>
                
                <fieldset>
                    <input type="radio" class="upload_file" name="upload_type" value="Upload File"
HTML;
                    echo ($is_repository===false)?'checked':'';
        print <<<HTML
                    > Upload File(s)
                    <input type="radio" id="repository_radio" class="upload_repo" name="upload_type" value="Repository"
HTML;
                    echo ($is_repository===true)?'checked':'';
        print <<<HTML
                    > Repository
                    
                    <div class="upload_type upload_file" id="upload_file">
                    </div>
                    
                    <div class="upload_type upload_repo" id="repository">
                        <br />
                        Which subdirectory? <input style='width: 227px' type='text' name='subdirectory' value="" />
                        <br />
                    </div>
                    
                </fieldset>

                Path to autograding config: 
                <input style='width: 227px' type='text' name='config_path' value="" class="required" placeholder="(Required)" />
                <br />
                Point precision: 
                <input style='width: 50px' type='text' name='point_precision' value="0.5" class="float_val" />
                <br /> <br />
                
                Use TA grading? 
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
                <br /> <br />
                <table class="table table-bordered" id="rubricTable" style=" border: 1px solid #AAA;">
                    <thead style="background: #E1E1E1;">
                        <tr>
                            <th>Question</th>
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
                    <textarea name="comment_title_{$num}" rows="1" class="comment_title complex_type" style="width: 800px; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-right: 1px;" 
                              >{$question['question_message']}</textarea>
                    <textarea name="ta_comment_{$num}" id="individual_{$num}" class="ta_comment complex_type" rows="1" placeholder=" Message to TA"  onkeyup="autoResizeComment(event);"
                                               style="width: 940px; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-bottom: 5px; 
                                               display: block;">{$question['question_grading_note']}</textarea>
                    <textarea name="student_comment_{$num}" id="student_{$num}" class="student_comment complex_type" rows="1" placeholder=" Message to Student" onkeyup="autoResizeComment(event);"
                              style="width: 940px; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-bottom: 5px; 
                              display: block;">{$question['student_grading_note']}</textarea>
                </td>

                <td style="background-color:#EEE;">
HTML;
        $old_grade = (isset($question['question_total'])) ? $question['question_total'] : 0;
        print selectBox($num, $old_grade);
        $checked = ($question['question_extra_credit']) ? "checked" : "";
        print <<<HTML
                    <input onclick='calculatePercentageTotal();' name="eg_extra_{$num}" type="checkbox" class='eg_extra extra' value='on' {$checked}/>
HTML;
        print <<<HTML
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
                <td style="overflow: hidden;">
                    <div class="btn btn-small btn-success" id="rubric-add-button" onclick="addQuestion()"><i class="icon-plus icon-white"></i> Question</div>
                </td>
                <td style="border-left: 1px solid #F9F9F9;"></td>
            </tr>
HTML;
        print <<<HTML
                <tr>
                    <td style="border-left: 1px solid #F9F9F9;"></td>
                    <td style="border-left: 1px solid #F9F9F9;"></td>
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
                               <input style="width: 200px" name="checkpoint_label_0" type="text" class="checkpoint_label complex_type" value="Checkpoint 0"/> 
                           </td>     
                           <td>     
                                <input type="checkbox" name="checkpoint_extra_0" class="checkpoint_extra extra" value="true" />
                           </td> 
                        </tr>
                      
                       <tr class="multi-field" id="mult-field-1">
                           <td>
                               <input style="width: 200px" name="checkpoint_label_1" type="text" class="checkpoint_label complex_type" value="Checkpoint 1"/> 
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
                               <input style="width: 200px" name="numeric_label_0" type="text" class="numeric_label complex_type" value="0"/> 
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
                               <input style="width: 200px" name="text_label_0" type="text" class="text_label complex_type" value="0"/> 
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
            What is the lowest privileged user group that can grade this?
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
            Who is assigned to grade this item?:
            <br />
            <fieldset>
                <input type="radio" name="section_type" value="reg_section"
HTML;
    echo (($action==='edit' && $g_grade_by_registration===true) || $action != 'edit')?'checked':'';
    print <<<HTML
                /> Registration Section
                <input type="radio" name="section_type" value="rotating-section" id="rotating-section" class="graders"
HTML;
    echo ($action==='edit' && $g_grade_by_registration===false)?'checked':'';
    print <<<HTML
                /> Rotating Section
HTML;

    $db->query("SELECT COUNT(*) AS cnt FROM sections_rotating", array());
    $num_rotating_sections = $db->row()['cnt'];
    $all_sections = str_replace(array('[', ']'), '', 
                    htmlspecialchars(json_encode(range(1,$num_rotating_sections)), ENT_NOQUOTES));

    $db->query("
    SELECT 
        u.user_id, array_agg(sections_rotating ORDER BY sections_rotating ASC) AS sections
    FROM 
        users AS u INNER JOIN grading_rotating AS gr ON u.user_id = gr.user_id
    WHERE 
        g_id=?
    AND 
        u.user_group BETWEEN 2 AND 3
    GROUP BY 
        u.user_id
    ",array($g_id));
    
    $graders_to_sections = array();
    
    foreach($db->rows() as $grader){
        $graders_to_sections[$grader['user_id']] = str_replace(array('[', ']'), '', 
                                                   htmlspecialchars(json_encode(pgArrayToPhp($grader['sections'])), ENT_NOQUOTES));
    }
    
    print <<<HTML
    <div id="rotating-sections" class="graders" style="display:none;">
        <br />
        Available rotating sections: {$num_rotating_sections}
        
HTML;
    
    print <<<HTML
        <div id="full-access-graders" style="display:none;">
            <br />
            <table>
                <th>Full Access Graders</th>
HTML;
    
   $db->query("SELECT user_id FROM users WHERE user_group=?", array(2));
      
    foreach($db->rows() as $fa_grader){
        print <<<HTML
        <tr>
            <td>{$fa_grader['user_id']}</td>
            <td><input style="width: 227px" type="text" name="grader_{$fa_grader['user_id']}" class="grader" value="
HTML;
        if($action==='edit' && !$g_grade_by_registration) {
            print (isset($graders_to_sections[$fa_grader['user_id']])) ? $graders_to_sections[$fa_grader['user_id']] : '';
        } 
        else{
            print $all_sections; 
        }
        print <<<HTML
            "></td>
        </tr>
HTML;
    }
    
    print <<<HTML
            </table>
        </div>
        <div id="limited-access-graders" style="display:none;">
            <br />
            <table>
                <th>Limited Access Graders</th>
HTML;

   $db->query("SELECT user_id FROM users WHERE user_group=?", array(3));
      
    foreach($db->rows() as $la_grader){
        print <<<HTML
        <tr>
            <td>{$la_grader['user_id']}</td>
            <td><input style="width: 227px" type="text" name="grader_{$la_grader['user_id']}" class="grader" value="
HTML;
        if($action==='edit' && !$g_grade_by_registration) {
            print (isset($graders_to_sections[$la_grader['user_id']])) ? $graders_to_sections[$la_grader['user_id']] : '';
        } 
        else{
            print $all_sections; 
        }
        print <<<HTML
"></td>
        </tr>
HTML;
    }

    print <<<HTML
        </table>

    </div> 
        <br />
    </div>
    </fieldset>
HTML;

    print <<<HTML
            <!-- TODO default to the submission + late days for electronic -->
            What date can the TAs start grading this?: <input name="date_grade" id="date_grade" class="datepicker" type="text"
                style="cursor: auto; background-color: #FFF; width: 250px;">
            
            <br />
            What date will the TA grades be released to the students? 
            <input name="date_released" id="date_released" class="datepicker" type="text" 
                   style="cursor: auto; background-color: #FFF; width: 250px;">    
            
            <br />
            What syllabus/iris "bucket" does this item belong to?:
            
            <select name="gradeable_buckets" style="width: 170px;">
HTML;

    $valid_assignment_type = array('homework','assignment','quiz','test','reading','participation',
                                   'exam','lab','recitation','problem-set','project', 'none (for practice only)');
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
                <button class="btn btn-primary" type="submit" style="margin-top: 10px;">{$string} Gradeable</button>
        </div>
    </form>
</div>

<script type="text/javascript">
    $.fn.serializeObject = function(){
        var o = {};
        var a = this.serializeArray();
        var ignore = [];

        $('.ignore').each(function(){
            ignore.push($(this).attr('name'));
        });
        
        ignore.push("numeric_label_0");
        ignore.push("max_score_0");
        ignore.push("numeric_extra_0");
        ignore.push("text_label_0");
        ignore.push("checkpoint_label_0");
        
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
            else if ($("[name="+this.name+"]").hasClass('max_score')){
                if (o['max_score'] === undefined){
                    o['max_score'] = [];
                }
                o['max_score'].push(parseFloat(this.value));
            }
            else if ($("[name="+this.name+"]").hasClass('points')){
                if (o['points'] === undefined){
                    o['points'] = [];
                }
                o['points'].push(parseFloat(this.value));
            }
            else if($("[name="+this.name+"]").hasClass('extra')){
                var tmp = this.name.split('_');
                var bucket = tmp[0] + '_' + tmp[1];
                if (o[bucket] === undefined){
                    o[bucket] = [];
                }
                val = parseInt(tmp[2]);
                o[bucket].push(val);
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
    });

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
        
        if ( $('input:radio[name="ta_grading"]:checked').attr('value')==='false'){
            $('#rubric_questions').hide();
        }
        
        $('input:radio[name="ta_grading"]').change(
        function(){
            $('#rubric_questions').hide();
            if ($(this).is(':checked')){
                if($(this).val() == 'true'){ 
                    $('#rubric_questions').show();
                }
            }
        });
        
        function showGroups(val){
            var graders = ['','','full-access-graders', 'limited-access-graders']; 
            for(var i=parseInt(val)+1; i<graders.length; ++i){
                $('#'+graders[i]).hide();
            }
            for(var i=0; i <= parseInt(val) ; ++i){
                $('#'+graders[i]).show();
            }
        }
        
        showGroups($('select[name="minimum_grading_group"] option:selected').attr('value'));
        
        $('select[name="minimum_grading_group"]').change(
        function(){
            showGroups(this.value);
        });
        
        if({$default_late_days} != -1){
            $('input[name=eg_late_days]').val('{$default_late_days}');
        }
        
        if($('#radio_electronic_file').is(':checked')){ 
            $('input[name=instructions_url]').val('{$electronic_gradeable['eg_instructions_url']}');
            $('input[name=date_submit]').datetimepicker('setDate', (new Date("{$electronic_gradeable['eg_submission_open_date']}")));
            $('input[name=date_due]').datetimepicker('setDate', (new Date("{$electronic_gradeable['eg_submission_due_date']}")));
            $('input[name=subdirectory]').val('{$electronic_gradeable['eg_subdirectory']}');
            $('input[name=config_path]').val('{$electronic_gradeable['eg_config_path']}');
            $('input[name=eg_late_days]').val('{$electronic_gradeable['eg_late_days']}');
            $('input[name=point_precision]').val('{$electronic_gradeable['eg_precision']}');
            
            if($('#repository_radio').is(':checked')){
                $('#repository').show();
            }
            
            $('#electronic_file').show();
        }
        else if ($('#radio_checkpoints').is(':checked')){
            var components = {$old_components};
            // remove the default checkpoint
            removeCheckpoint(); 
            $.each(components, function(i,elem){
                addCheckpoint(elem.gc_title,elem.gc_is_extra_credit);
            });
            $('#checkpoints').show();
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
        }
        if({$have_old}){
            $('input[name=gradeable_id]').attr('readonly', true);
        }
    });

    var datepicker = $('.datepicker');
    datepicker.datetimepicker({
        timeFormat: "HH:mm:ss",
        showTimezone: false
    });
    
    if(!{$have_old}){
        $('#date_submit').datetimepicker('setDate', (new Date("{$yesterday}")));
        $('#date_due').datetimepicker('setDate', (new Date("{$current_date}")));
        
    }
    
    $('#date_grade').datetimepicker('setDate', (new Date("{$old_gradeable['g_grade_start_date']}")));
    $('#date_released').datetimepicker('setDate', (new Date("{$old_gradeable['g_grade_released_date']}")));

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
    $('input:radio[name="gradeable_type"]').change(
    function(){
        $('#required_type').hide();
        $('.gradeable_type_options').hide();
        if ($(this).is(':checked')){ 
            if($(this).val() == 'Electronic File'){ 
                $('#electronic_file').show();
            }
            else if ($(this).val() == 'Checkpoints'){ 
                $('#checkpoints').show();
            }
            else if ($(this).val() == 'Numeric'){ 
                $('#numeric').show();
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
                <textarea name="comment_title_'+newQ+'" rows="1" class="comment_title complex_type" style="width: 800px; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-right: 1px;"></textarea> \
                <textarea name="ta_comment_'+newQ+'" id="individual_'+newQ+'" rows="1" class="ta_comment complex_type" placeholder=" Message to TA"  onkeyup="autoResizeComment(event);" \
                          style="width: 940px; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-bottom: 5px;"></textarea> \
                <textarea name="student_comment_'+newQ+'" id="student_'+newQ+'" rows="1" class="student_comment complex_type" placeholder=" Message to Student"  onkeyup="autoResizeComment(event);" \
                          style="width: 940px; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-bottom: 5px;"></textarea> \
            </td> \
            <td style="background-color:#EEE;">' + sBox + ' \
                <input onclick="calculatePercentageTotal();" name="eg_extra_'+newQ+'" type="checkbox" class="eg_extra extra" value="on"/> \
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
    
    // autoresize the comment box
    function autoResizeComment(e){
        e.target.style.height ="";
        e.target.style.height = e.target.scrollHeight + "px";
    }

    function selectBox(question){
        var retVal = '<select name="points_' + question + '" class="points" onchange="calculatePercentageTotal()">';
        for(var i = -100; i <= 100; i++) {
            if(i==0){
                retVal = retVal + '<option selected="selected">' + (i * 0.5) + '</option>';
            }
            else{
                retVal = retVal + '<option>' + (i * 0.5) + '</option>';
            }
        }
        retVal = retVal + '</select>';
        return retVal;
    }

    function calculatePercentageTotal() {
        var total = 0;
        var ec = 0;
        $('select.points').each(function(){
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
    calculatePercentageTotal();
    </script>
HTML;

}

include "../footer.php";