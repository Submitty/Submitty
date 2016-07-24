<?php
use \lib\Database;
use \lib\Functions;

include "../header.php";

check_administrator();

if($user_is_administrator){
    $have_old = $has_grades = false;
    $old_gradeable = array(
        'g_id' => -1,
        'g_title' => "",
        'g_overall_ta_instructions' => '',
        'g_team_assignment' => false,
        'g_gradeable_type' => 0,
        'g_grade_by_registration' => false,
        'g_grade_start_date' => date('Y/m/d 23:59:59'),
        'g_grade_released_date' => date('Y/m/d 23:59:59'),
        'g_syllabus_bucket' => '',
        'g_min_grading_group' => ''
    );
    $old_questions = $old_components = $electronic_gradeable = array();
    $num_numeric = $num_text = 0;
    $g_gradeable_type = $is_repository = $g_syllabus_bucket = $g_min_grading_group = -1;
    $use_ta_grading = true;
    $g_overall_ta_instructions = $g_id = '';
    
    
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

    $useAutograder = (__USE_AUTOGRADER__) ? "true" : "false";
    $account_subpages_unlock = true;
    
    function selectBox($question, $grade = 0) {
        $retVal = "<select name='point-{$question}' class='points' onchange='calculatePercentageTotal();'>";
        for($i = 0; $i <= 100; $i += 0.5) {
            $selected = ($grade == $i) ? "selected" : "";
            $retVal .= "<option {$selected}>{$i}</option>";
        }
        $retVal .= "</select>";
        return $retVal;
    }

    $gradeables = array();
    $db->query("SELECT g_id from gradeable ORDER BY g_id", array());
    foreach ($db->rows() as $row) {
        $gradeables[$row['g_id']] = $row['g_id'];
    }

    if (!$have_old) {
        $gradeableNumberQuery = (count($gradeables) > 0) ? end($gradeables) + 1 : 1;
        $gradeable_name = "Gradeable {$gradeableNumberQuery}";
        $gradeable_submission_id = "gradeable".Functions::pad($gradeableNumberQuery);
        $g_team_assignment = json_encode($old_gradeable['g_team_assignment']);
        $g_grade_by_registration = $old_gradeable['g_grade_by_registration'];
        $string = "Add";
        $action = strtolower($string);
    }
    else {
        $gradeableNumberQuery = 0;
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
        $string = "Edit";
        $action = strtolower($string);
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
    .gradeable-type-options, .upload-type{
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
</style>

<div id="container-rubric">
    <form id="gradeable-form" class="form-signin" action="{$BASE_URL}/account/submit/admin-gradeable.php?action={$action}&id={$old_gradeable['g_id']}" 
          method="post" enctype="multipart/form-data"> 

        <input type='hidden' name="csrf_token" value="{$_SESSION['csrf']}" />
        <div class="modal-header" style="overflow: auto;">
            <h3 id="myModalLabel" style="float: left;">{$string} Gradeable {$extra}</h3>
            <!-- <button class="btn import-json" type="button" style="float: right;">Import From JSON</button>-->
            <button class="btn btn-primary" type="submit" style="margin-right:10px; float: right;">{$string} Gradeable</button>
        </div>
        <div class="modal-body" style="/*padding-bottom:80px;*/ overflow:visible;">
            What is the unique id of this gradeable?: <input style='width: 200px' type='text' name='gradeable_id' value="{$gradeable_submission_id}" />
            <br />
            What is the title of this gradeable?: <input style='width: 227px' type='text' name='gradeable_title' value="{$gradeable_name}" />
            <br />
            What overall instructions should be provided to the TA?:<br /><textarea rows="4" cols="200" name="ta_instructions" placeholder="(Optional)" style="width: 500px;">
HTML;
    echo htmlspecialchars($g_overall_ta_instructions);  
    print <<<HTML
</textarea>
        <br />
        Is this a team assignment?:
        <input type="radio" name="team-assignment" value="yes"
HTML;
    
    echo ($g_team_assignment===true)?'checked':''; 
    print <<<HTML
        > Yes
            <input type="radio" name="team-assignment" value ="no" 
HTML;
    echo ($g_team_assignment===false)?'checked':'' ;
    print <<<HTML
            > No
            <br /> <br />   
            What is the type of your gradeable?:

            <fieldset>
                <input type='radio' id="radio-electronic-file" class="electronic-file" name="gradeable-type" value="Electronic File"
HTML;
    echo ($g_gradeable_type === 0)?'checked':'';
    print <<<HTML
            > 
            Electronic File
            <input type='radio' id="radio-checkpoints" class="checkpoints" name="gradeable-type" value="Checkpoints"
HTML;
            echo ($g_gradeable_type === 1)?'checked':'';
    print <<<HTML
            >
            Checkpoints
            <input type='radio' id="radio-numeric" class="numeric" name="gradeable-type" value="Numeric"
HTML;
            echo ($g_gradeable_type === 2)?'checked':'';
    print <<<HTML
            >
            Numeric/Text
            <!-- This is only relevant to Electronic Files -->
            <div class="gradeable-type-options electronic-file" id="electronic-file" >    
                <br />
                What date does the submission open to students?: <input name="date_submit" class="datepicker" type="text"
                style="cursor: auto; background-color: #FFF; width: 250px;">
                <br />
                What is the URL to the assignment instructions? (shown to student) 
                <input style='width: 227px' type='text' name='instructions-url' placeholder="(Optional)" value="" />
                <br />
                What is the due date? <input name="date_due" class="datepicker" type="text"
                style="cursor: auto; background-color: #FFF; width: 250px;">
                <br />
                <!-- TODO: set default late days -->
                How many late days may students use on this assignment? <input style="width: 50px" name="eg_late_days" 
                                                                         type="text"/>
                <br/>
                
                <fieldset>
                    <input type="radio" class="upload-file" name="upload-type" value="Upload File"
HTML;
                    echo ($is_repository===false)?'checked':'';
        print <<<HTML
                    > Upload File(s)
                    <input type="radio" id="repository_radio" class="upload-repo" name="upload-type" value="Repository"
HTML;
                    echo ($is_repository===true)?'checked':'';
        print <<<HTML
                    > Repository
                    
                    <div class="upload-type upload-file" id="upload-file">
                        <!--<br />
                        How many total "drop zones" (directories or paths) for upload? 
                        <input style="width: 50px" name="num-drop-zones" type="text" value="1"/> 
                        <br />
                        Limit on total sum of size of files uploaded 
                        <input style="width: 50px" name="total-file-size" type="text" value="1 MB"/> (default is... ?) 
                        <br />-->
                    </div>
                    
                    <div class="upload-type upload-repo" id="repository">
                        <br />
                        Which subdirectory? <input style='width: 227px' type='text' name='subdirectory' value="" />
                        <br />
                    </div>
                    
                </fieldset>
                <!-- Path to .h config may or not be included -->
                Path to autograding config: 
                <input style='width: 227px' type='text' name='config-path' value="" />
                <br />
                Point precision: 
                <input style='width: 50px' type='text' name='point-precision' value="" />
                <br /> <br />
                
                Use TA grading? 
                <input type="radio" id="yes_ta_grade" name="ta-grading" value="yes" 
HTML;
                echo ($use_ta_grading===true)?'checked':'';
        print <<<HTML
                /> Yes
                <input type="radio" id="no_ta_grade" name="ta-grading" value="no" 
HTML;
                echo ($use_ta_grading===false)?'checked':'';
        print <<<HTML
                /> No
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
        if (__USE_AUTOGRADER__) {
            $old_questions[0] = array('question_message'      => "AUTO-GRADING",
                                         'question_grading_note' => "",
                                         'student_grading_note' =>"",
                                         'question_total'        => 0,
                                         'question_extra_credit' => 0);
            $old_questions[1] = array('question_message'      => "AUTO-GRADING EXTRA CREDIT",
                                         'question_grading_note' => "",
                                         'student_grading_note'  => "",
                                         'question_total'        => 0,
                                         'question_extra_credit' => 1);
        }
        $old_questions[2] = array('question_message'      => "",
                                     'question_grading_note' => "",
                                     'student_grading_note'  => "",
                                     'question_total'        => 0,
                                     'question_extra_credit' => 0);
    }

    foreach ($old_questions as $num => $question) {
        $disabled = ($num<2) ? "disabled" : "";
        $readonly = ($num<2) ? "readonly" : "";
        print <<<HTML
            <tr class="rubric-row" id="row-{$num}">
HTML;
        $display_ta = ($question['question_grading_note'] != "") ? 'block' : 'none';

        print <<<HTML
                <td style="overflow: hidden;">
                    <textarea name="comment-{$num}" rows="1" style="width: 800px; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-right: 1px;" 
                              {$readonly}>{$question['question_message']}</textarea>
                    <div class="btn btn-mini btn-default" onclick="toggleQuestion({$num}, 'individual')" style="margin-top:-5px;">TA Note</div>
                    <div class="btn btn-mini btn-default" onclick="toggleQuestion({$num}, 'student')" style="margin-top:-5px;">Student Note</div>
                    <textarea name="ta-{$num}" id="individual-{$num}" rows="1" placeholder=" Message to TA" 
                                               style="width: 940px; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-bottom: 5px; 
                                               display: {$display_ta};">{$question['question_grading_note']}</textarea>
                    <!-- Some fields need to change here TODO -->
                    <textarea name="student-{$num}" id="student-{$num}" rows="1" placeholder=" Message to Student" 
                              style="width: 940px; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-bottom: 5px; 
                              display: {$display_ta};">{$question['student_grading_note']}</textarea>
                </td>

                <td style="background-color:#EEE;">
HTML;
        $old_grade = (isset($question['question_total'])) ? $question['question_total'] : 0;
        print selectBox($num, $old_grade);
        $checked = ($question['question_extra_credit'] == 1) ? "checked" : "";
        print (($question['question_extra_credit'] == 1 && $disabled == "disabled") ? "<input type='hidden' name='ec-{$num}' value='on' />" : "");
        print <<<HTML
                    <input onclick='calculatePercentageTotal();' name="ec-{$num}" type="checkbox" {$checked} {$disabled} />
HTML;
        if($num>1){
            print <<<HTML
                    <br />
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
HTML;
    print <<<HTML
            </div>
            <div class="gradeable-type-options checkpoints" id="checkpoints">
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
                               <input style="width: 200px" name="checkpoint-label-0" type="text" class="checkpoint-label" value="Checkpoint 0"/> 
                           </td>     
                           <td>     
                                <input type="checkbox" name="checkpoint-extra-0" class="checkpoint-extra" value="true" />
                           </td> 
                        </tr>
                      
                       <tr class="multi-field" id="mult-field-1">
                           <td>
                               <input style="width: 200px" name="checkpoint-label-1" type="text" class="checkpoint-label" value="Checkpoint 1"/> 
                           </td>     
                           <td>     
                                <input type="checkbox" name="checkpoint-extra-1" class="checkpoint-extra" value="true" />
                           </td> 
                        </tr>
                  </table>
                  <button type="button" id="add-checkpoint-field">Add </button>  
                  <button type="button" id="remove-checkpoint-field" id="remove-checkpoint" style="visibilty:hidden;">Remove</button>   
                </div> 
                <br />
                Do you want a box for an (optional) message from the TA to the student?
                <input type="radio" name="checkpt-opt-ta-messg" value="yes" /> Yes
                <input type="radio" name="checkpt-opt-ta-messg" value="no" /> No
            </div>
            <div class="gradeable-type-options numeric" id="numeric">
                <br />
                How many numeric items? <input style="width: 50px" id="numeric-num-items" name="num-numeric-items" type="text" value="0"/> 
                &emsp;&emsp;
                
                How many text items? <input style="width: 50px" id="numeric-num-text-items" name="num-text-items" type="text" value="0"/>
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
                               <input style="width: 200px" name="numeric-label-0" type="text" class="numeric-label" value="0"/> 
                           </td>  
                            <td>     
                                <input style="width: 60px" type="text" name="max-score-0" class="max-score" value="0" /> 
                           </td>                           
                           <td>     
                                <input type="checkbox" name="numeric-extra-0" class="numeric-extra" value="" />
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
                               <input style="width: 200px" name="text-label-0" type="text" class="text-label" value="0"/> 
                           </td>  
                        </tr>
                    </table>
                </div>  
                <br /> <br />
                Do you want a box for an (optional) message from the TA to the student?
                <input type="radio" name="opt-ta-messg" value="yes" /> Yes
                <input type="radio" name="opt-ta-messg" value="no" /> No
            </div>  
            </fieldset>
            <br/>

            Who is assigned to grade this item?:
            <br /> <br />
            <input type="radio" name="section-type" value="reg-section"
HTML;
    echo ($action==='edit' && $g_grade_by_registration===true)?'checked':'';
    print <<<HTML
            /> Registration Section
            <input type="radio" name="section-type" value="rotating-section" id="rotating-section" class="graders"
HTML;
    echo ($action==='edit' && $g_grade_by_registration===false)?'checked':'';
    print <<<HTML
            /> Rotating Section
            <br />
            <!-- For each TA/mentor 
                 Checkboxes (select, zero, one, or more for the available sections)
                 Single checkbox per user to indicate if this grader can see/edit
                 the grades for other sections
                 
                 NOTE: Course policy defaults per user:
                        Instructor:  has admin access to create gradeables, can always see and edit all gradeables
                        [need generic name --  maybe “teaching assistant”]  Our graduate TAs:  by default can see and 
                        edit all gradeables in all sections, but this can be disabled per gradeable
                        [need generic name -- maybe “grader”]  Our undergraduate mentors/UTAs:   by default can’t see 
                        or edit any gradeables in any sections, but per gradeable can read/write access be granted.
                NOTE:  Flag as error if some sections have no grader    
            -->
HTML;

    $db->query("SELECT COUNT(*) AS cnt FROM sections_rotating", array());
    $num_rotating_sections = $db->row()['cnt'];
    $all_sections = str_replace(array('[', ']'), '', 
                    htmlspecialchars(json_encode(range(1,$num_rotating_sections)), ENT_NOQUOTES));

    
    // write a sql query to relate graders to all of their grading sections
    // i.e. grader => [sections]
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
        <br /> <br />
HTML;
    
    //  ONE for TAs  
    print <<<HTML
            <table>
                <th>Full Access Graders</th>
HTML;
    
   $db->query("SELECT user_id FROM users WHERE user_group=?", array(2));
      
    foreach($db->rows() as $fa_grader){
        print <<<HTML
        <tr>
            <td>{$fa_grader['user_id']}</td>
            <td><input style="width: 227px" type="text" name="grader-{$fa_grader['user_id']}" value="
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
            <br/>
            <table>
                <th>Limited Access Graders</th>
HTML;

   $db->query("SELECT user_id FROM users WHERE user_group=?", array(3));
      
    foreach($db->rows() as $la_grader){
        print <<<HTML
        <tr>
            <td>{$la_grader['user_id']}</td>
            <td><input style="width: 227px" type="text" name="grader-{$la_grader['user_id']}" class="graders" value="
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
HTML;

    print <<<HTML
            <!-- TODO default to the submission + late days for electronic -->
            What date can the TAs start grading this?: <input name="date_grade" id="date_grade" class="datepicker" type="text"
                style="cursor: auto; background-color: #FFF; width: 250px;">
            
            <br />
            <!-- TODO default to never -->    
            What date will the TA grades be released to the students? 
            <input name="date_released" id="date_released" class="datepicker" type="text" 
                   style="cursor: auto; background-color: #FFF; width: 250px;">    
            
            <br />
            What syllabus/iris "bucket" does this item belong to?:
            
            <select name="gradeable-buckets" style="width: 170px;">
                <!--<option value="homework"-->
HTML;

    $valid_assignment_type = array('homework','assignment','quiz','test','reading','participation',
                                   'exam','lab','recitation','problem-set','project');
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
            What is the lowest privileged user group that can grade this?
            <select name="minimum-grading-group" style="width:180px;">
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
        ignore.push('csrf_token');
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
            if (o[this.name] !== undefined) {
                if (!o[this.name].push) {
                    o[this.name] = [o[this.name]];
                }
                o[this.name].push(this.value || '');
            } else {
                o[this.name] = this.value || '';
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
            $('#mult-field-0', wrapper).clone(true).appendTo(wrapper).attr('id','mult-field-'+numCheckpoints).find('.checkpoint-label').val(label).focus();
            $('#mult-field-' + numCheckpoints,wrapper).find('.checkpoint-label').attr('name','checkpoint-label-'+numCheckpoints);
            $('#mult-field-' + numCheckpoints,wrapper).find('.checkpoint-extra').attr('name','checkpoint-extra-'+numCheckpoints);
            if(extra_credit){
                $('#mult-field-' + numCheckpoints,wrapper).find('.checkpoint-extra').attr('checked',true); 
            }
            $('#remove-checkpoint-field').show();
            $('#mult-field-' + numCheckpoints,wrapper).show();
        }
        
        function removeCheckpoint(){
            if (numCheckpoints > 0){
                $('#mult-field-'+numCheckpoints,'.checkpoints-table').remove();
                if(--numCheckpoints === 1){
                    $('#remove-checkpoint-field').hide();
                }
            }
        }
        
        $('.multi-field-wrapper-checkpoints').each(function() {
            $("#add-checkpoint-field", $(this)).click(function(e) {
                addCheckpoint('Checkpoint '+(numCheckpoints+1),false);
            });
            $('#remove-checkpoint-field').click(function() {
                removeCheckpoint();
            });
        });
        
        $('#remove-checkpoint-field').hide();

        var numNumeric=0;
        var numText=0;
        
        function addNumeric(label, max_score, extra_credit){
            var wrapper = $('.numerics-table');
            numNumeric++;
            $('#mult-field-0', wrapper).clone(true).appendTo(wrapper).attr('id','mult-field-'+numNumeric).find('.numeric-label').val(label).focus();
            $('#mult-field-' + numNumeric,wrapper).find('.numeric-extra').attr('name','numeric-extra-'+numNumeric);
            $('#mult-field-' + numNumeric,wrapper).find('.numeric-label').attr('name','numeric-label-'+numNumeric);
            $('#mult-field-' + numNumeric,wrapper).find('.max-score').attr('name','max-score-'+numNumeric).val(max_score);
            if(extra_credit){
                $('#mult-field-' + numNumeric,wrapper).find('.numeric-extra').attr('checked',true); 
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
            $('#mult-field-0', wrapper).clone(true).appendTo(wrapper).attr('id','mult-field-'+numText).find('.text-label').val(label).focus();
            $('#mult-field-' + numText,wrapper).find('.text-label').attr('name','text-label-'+numText);
            $('#mult-field-' + numText,wrapper).show();
        }
        function removeText(){
            if (numText > 0){
               $('#mult-field-'+numText,'.text-table').remove(); 
            }
            --numText;
        }
        
        $('#numeric-num-text-items').on('input', function(e){
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

        $('#numeric-num-items').on('input',function(e){
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
        
        $('.gradeable-type-options').hide();
        
        if ($('input[name=gradeable-type]').is(':checked')){
            $('input[name=gradeable-type]').each(function(){
                if(!($(this).is(':checked'))){
                    $(this).attr("disabled",true);    
                }
            });
        }
        
        if($('#rotating-section').is(':checked')){
            $('#rotating-sections').show();
        }
        $('input:radio[name="section-type"]').change(
        function(){
            $('#rotating-sections').hide();
            if ($(this).is(':checked')){
                if($(this).val() == 'rotating-section'){ 
                    $('#rotating-sections').show();
                }
            }
        });
        
        
        if($('#radio-electronic-file').is(':checked')){ 
            $('input[name=instructions-url]').val('{$electronic_gradeable['eg_instructions_url']}');
            $('input[name=date_submit]').datetimepicker('setDate', (new Date("{$electronic_gradeable['eg_submission_open_date']}")));
            $('input[name=date_due]').datetimepicker('setDate', (new Date("{$electronic_gradeable['eg_submission_due_date']}")));
            $('input[name=subdirectory]').val('{$electronic_gradeable['eg_subdirectory']}');
            $('input[name=config-path]').val('{$electronic_gradeable['eg_config_path']}');
            $('input[name=eg_late_days]').val('{$electronic_gradeable['eg_late_days']}');
            $('input[name=point-precision]').val('{$electronic_gradeable['eg_precision']}');
            
            if($('#repository_radio').is(':checked')){
                $('#repository').show();
            }
            
            $('#electronic-file').show();
        }
        else if ($('#radio-checkpoints').is(':checked')){
            var components = {$old_components};
            //TODO fix the ta-message
            // remove the default checkpoint
            removeCheckpoint(); 
            $.each(components, function(i,elem){
                addCheckpoint(elem.gc_title,elem.gc_is_extra_credit);
            });
            $('#checkpoints').show();
        }
        else if ($('#radio-numeric').is(':checked')){ 
            var components = {$old_components};
            //TODO fix the ta-message
            $.each(components, function(i,elem){
                if(i < {$num_numeric}){
                    addNumeric(elem.gc_title,elem.gc_max_value,elem.gc_is_extra_credit);
                }
                else{
                    addText(elem.gc_title);
                }
            });
            $('#numeric-num-items').val({$num_numeric});
            $('#numeric-num-text-items').val({$num_text});
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

    $('#date_grade').datetimepicker('setDate', (new Date("{$g_grade_start_date}")));
    $('#date_released').datetimepicker('setDate', (new Date("{$g_grade_released_date}")));

    function toggleQuestion(question, role) {
        if(document.getElementById(role +"-" + question ).style.display == "block") {
            $("#" + role + "-" + question ).animate({marginBottom:"-80px"});
            setTimeout(function(){document.getElementById(role + "-"+ question ).style.display = "none";}, 175);
        }
        else {
            $("#" + role + "-" + question ).animate({marginBottom:"5px"});
            setTimeout(function(){document.getElementById(role+"-" + question ).style.display = "block";}, 175);
        }
        calculatePercentageTotal();
    }

    // Shows the radio inputs dynamically
    $('input:radio[name="gradeable-type"]').change(
    function(){
        $('.gradeable-type-options').hide();
        if ($(this).is(':checked')){ 
            if($(this).val() == 'Electronic File'){ 
                $('#electronic-file').show();
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
    $('input:radio[name="upload-type"]').change(
    function(){
        $('.upload-type').hide();
        if ($(this).is(':checked')){ 
            if($(this).val() == 'Upload File'){ 
                $('#upload-file').show();
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
                <textarea name="comment-'+newQ+'" rows="1" style="width: 800px; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-right: 1px;"></textarea> \
                <div class="btn btn-mini btn-default" onclick="toggleQuestion(' + newQ + ',\'individual\''+')" style="margin-top:-5px;">TA Note</div> \
                <div class="btn btn-mini btn-default" onclick="toggleQuestion(' + newQ + ',\'student\''+')" style="margin-top:-5px;">Student Note</div> \
                <textarea name="ta-'+newQ+'" id="individual-'+newQ+'" rows="1" placeholder=" Message to TA" style="width: 940px; padding: 0 0 0 10px; \
                          resize: none; margin-top: 5px; margin-bottom: 5px;"></textarea> \
                <!-- Some fields need to change here TODO --> \
                <textarea name="student-'+newQ+'" id="student-'+newQ+'" rows="1" placeholder=" Message to Student" style="width: 940px; padding: 0 0 0 10px; \
                          resize: none; margin-top: 5px; margin-bottom: 5px;"></textarea> \
            </td> \
            <td style="background-color:#EEE;">' + sBox + ' \
                <input onclick="calculatePercentageTotal();" name="ec-'+newQ+'" type="checkbox" /> \
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

    function selectBox(question){
        var retVal = '<select name="point-' + question + '" class="points" onchange="calculatePercentageTotal()">';
        for(var i = 0; i <= 100; i++) {
            retVal = retVal + '<option>' + (i * 0.5) + '</option>';
        }
        retVal = retVal + '</select>';
        return retVal;
    }

    function calculatePercentageTotal() {
        var total = 0;
        var ec = 0;
        $('select.points').each(function(){
            var elem = $(this).attr('name').replace('point','ec');
            if (!$('[name="'+elem+'"]').is(':checked') == true) {
                total += +($(this).val());
            }
            else {
                ec += +($(this).val());
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
        row.find('textarea[name=comment-' + oldNum + ']').attr('name', 'comment-' + newNum);
        row.find('div.btn').attr('onclick', 'toggleQuestion(' + newNum + ',"individual"' + ')');
        row.find('textarea[name=ta-' + oldNum + ']').attr('name', 'ta-' + newNum).attr('id', 'individual-' + newNum);
        row.find('select[name=point-' + oldNum + ']').attr('name', 'point-' + newNum);
        row.find('input[name=ec-' + oldNum + ']').attr('name', 'ec-' + newNum);
        row.find('a[id=delete-' + oldNum + ']').attr('id', 'delete-' + newNum).attr('onclick', 'deleteQuestion(' + newNum + ')');
        row.find('a[id=down-' + oldNum + ']').attr('id', 'down-' + newNum).attr('onclick', 'moveQuestionDown(' + newNum + ')');
        row.find('a[id=up-' + oldNum + ']').attr('id', 'up-' + newNum).attr('onclick', 'moveQuestionUp(' + newNum + ')');
    }

    function moveQuestionDown(question) {
        if (question <= 1) {
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
        if (question <=2) {
            return;
        }

        var currentRow = $('tr#row-' + question);
        var newRow = $('tr#row-' + (question-1));
        var child = 0;
        if (question == 2) {
            child = 1;
        }

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