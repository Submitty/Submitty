<?php
use \lib\Database;
use \lib\Functions;

include "../header.php";

check_administrator();

if($user_is_administrator)
{
    $have_old = false;
    $has_grades = false;
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
    $old_questions = array();
    $old_components = array();

    if (isset($_GET['action']) && $_GET['action'] == 'edit') {
        $gradeable_id = $_GET['id'];
        Database::query("SELECT * FROM gradeable WHERE g_id=?",array($gradeable_id));
        if (count(Database::rows()) == 0) {
            die("No gradeable found");
        }
        $old_gradeable = Database::row();
        Database::query("SELECT * FROM gradeable_component WHERE g_id=? ORDER BY gc_order", array($gradeable_id));
        $old_components = json_encode(Database::rows());
        $have_old = true;
        
        //figure out if the gradeable has grades or not
        $db->query("SELECT COUNT(*) as cnt FROM gradeable AS g INNER JOIN gradeable_component AS gc ON g.g_id=gc.g_id 
                    INNER JOIN gradeable_component_data AS gcd ON gcd.gc_id=gc.gc_id WHERE g.g_id=?",array($gradeable_id));
       $has_grades = $db->row()['cnt'];
    }

    $useAutograder = (__USE_AUTOGRADER__) ? "true" : "false";
    $account_subpages_unlock = true;

    function selectBox($part, $question, $grade = 0) {
        $retVal = "<select name='point-{$part}-{$question}' class='points' onchange='calculatePercentageTotal();'>";
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
        $gradeable_parts_submission_id[1] = "_part1";
        $g_team_assignment = json_encode($old_gradeable['g_team_assignment']);
        $g_grade_by_registration = json_encode($old_gradeable['g_grade_by_registration']);
        $part_count = 1;
        $string = "Add";
        $action = strtolower($string);
    }
    else {
        $gradeableNumberQuery = 0;
        $gradeable_name = $old_gradeable['g_title'];
        $gradeable_submission_id = $old_gradeable['g_id'];
        $gradeable_parts_submission_id = array();
        $g_overall_ta_instructions = $old_gradeable['g_overall_ta_instructions'];
        $g_gradeable_type = $old_gradeable['g_gradeable_type'];
        $g_team_assignment = $old_gradeable['g_team_assignment'];
        $g_grade_by_registration = $old_gradeable['g_grade_by_registration'];
        $g_syllabus_bucket = $old_gradeable['g_syllabus_bucket'];
        $g_grade_start_date = $old_gradeable['g_grade_start_date'];
        $g_grade_released_date = $old_gradeable['g_grade_released_date'];
        $g_min_grading_group = $old_gradeable['g_min_grading_group'];
        $part_count = 0;
        $string = "Edit";
        $action = strtolower($string);
    }

    $have_old = json_encode($have_old);
    $rubric_sep_checked = ($old_rubric['rubric_parts_sep'] == 1) ? "checked" : "";

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
    <form class="form-signin" action="{$BASE_URL}/account/submit/admin-gradeable.php?action={$action}&id={$old_gradeable['g_id']}" method="post" enctype="multipart/form-data"> 
        <input type='hidden' name="part_count" value="{$part_count}" />
        <input type='hidden' name="csrf_token" value="{$_SESSION['csrf']}" />

        <div class="modal-header" style="overflow: auto;">
            <h3 id="myModalLabel" style="float: left;">{$string} Gradeable {$extra}</h3>
            <!-- <button class="btn import-json" type="button" style="float: right;">Import From JSON</button>-->
            <button class="btn btn-primary" type="submit" style="margin-right:10px; float: right;">{$string} Gradeable</button>
        </div>

        <div class="modal-body" style="/*padding-bottom:80px;*/ overflow:visible;">
            <!-- check to make sure this id is actually unique -->
            What is the unique id of this gradeable?: <input style='width: 200px' type='text' name='gradeable_id' value="{$gradeable_submission_id}" />
            <br />
            What is the title of this gradeable?: <input style='width: 227px' type='text' name='gradeable_title' value="{$gradeable_name}" />
            <br />
            What overall instructions should be provided to the TA?: 
            <br />
            <textarea rows="4" cols="200" name="ta_instructions" placeholder="(Optional)" style="width: 500px;">
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
            
            <!-- This should not be changed after grading has begun? -->
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
                How many late days may students use on this assignment? <input style="width: 50px" name="rubric_late_days" type="text" value="{$old_rubric['rubric_late_days']}"/>
                <br/>
                
                <fieldset>
                    <!-- Should probably use lables, but CSS is weird-->
                    <input type="radio" class="upload-file" name="upload-type" value="Upload File"> Upload File(s)
                    <input type="radio" class="upload-repo" name="upload-type" value="Repository"> Repository
                    
                    <div class="upload-type upload-file" id="upload-file">
                        <br />
                        How many total "drop zones" (directories or paths) for upload? <input style="width: 50px" name="num-drop-zones" type="text" value="1"/> 
                        <br />
                        Limit on total sum of size of files uploaded <input style="width: 50px" name="total-file-size" type="text" value="1 MB"/> (default is... ?) 
                        <br />
                    </div>
                    
                    <div class="upload-type upload-repo" id="repository">
                        <br />
                        Which subdirectory? <input style='width: 227px' type='text' name='subdirectory' value="" />
                        <br />
                    </div>
                    
                </fieldset>
                <!-- Path to .h config may or not be included -->
                
                Use autograding?
                
                <!-- The max and extra credit max will be read automatically
                     Would be nice to display this now
                     can read these from config.h, but that would be messy
                     can read from the .json file for this assingnment, only after compilation
                -->
                
                <!-- Again these should probably be labels, but CSS is annoying (>_<) -->
                <input type="radio" name="auto-grading" value="yes" /> Yes
                <input type="radio" name="auto-grading" value ="no" /> No
                <br /> <br />
                
                Use TA grading? 
                <input type="radio" name="ta-grading" value="yes" /> Yes
                <input type="radio" name="ta-grading" value="no" /> No
                <br /> <br />
                
                &emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;
                
                <table class="table table-bordered" id="rubricTable" style=" border: 1px solid #AAA;">
                    <thead style="background: #E1E1E1;">
                        <tr>
                            <!-- <th style="width:61px;">Part</th> -->
                            <th>Question</th>
                            <th style="width:100px;">Points</th>
                        </tr>
                    </thead>

                    <tbody style="background: #f9f9f9;">
HTML;

    if (count($old_questions) == 0) {
        if (__USE_AUTOGRADER__) {
            $old_questions[0][1] = array('question_message'      => "AUTO-GRADING",
                                         'question_grading_note' => "",
                                         'question_total'        => 0,
                                         'question_extra_credit' => 0);
            $old_questions[0][2] = array('question_message'      => "AUTO-GRADING EXTRA CREDIT",
                                         'question_grading_note' => "",
                                         'question_total'        => 0,
                                         'question_extra_credit' => 1);
        }
        $old_questions[1][1] = array('question_message'      => "",
                                     'question_grading_note' => "",
                                     'question_total'        => 0,
                                     'question_extra_credit' => 0);
    }

    foreach ($old_questions as $k => $v) {
        $count = count($old_questions[$k]) + (($k > 0) ? 1 : 0);

        $disabled = ($k == 0) ? "disabled" : "";
        $readonly = ($k == 0) ? "readonly" : "";

        $first = true;
        foreach ($v as $num => $question) {
            print <<<HTML
                <tr id="row-{$k}-{$num}">
HTML;
            if ($first) {
                print <<<HTML
                    <td rowspan="{$count}" id="part-{$k}" style="position:relative; display: none;">
                        <span id='spanPart{$k}'>{$k}</span><br />
HTML;
                if ($k > 0) {
                    print <<<HTML
                        <a id="delete-{$k}" class="question-icon" onclick="deletePart({$k});"><img class="question-icon-cross" src="../toolbox/include/bootstrap/img/glyphicons-halflings.png"></a>
                        <!--<a id="down-{$k}" class="question-icon" onclick="movePartDown({$k});"><img class="question-icon-down" src="../toolbox/include/bootstrap/img/glyphicons-halflings.png"></a>
                        <a id="up-{$k}" class="question-icon" onclick="movePartUp({$k});"><img class="question-icon-up" src="../toolbox/include/bootstrap/img/glyphicons-halflings.png"></a>-->
                        <br />
                        <div class='part_submission_id'>
                            <input style='width: 47px' type="text" name="rubric_part_{$k}_id" value="{$rubric_parts_submission_id[$k]}" />
                        </div>
HTML;
                }

                print <<<HTML
                    </td>
HTML;
                $first = false;
            }

            $display_ta = ($question['question_grading_note'] != "") ? 'block' : 'none';

            print <<<HTML
                    <td style="overflow: hidden;">
                        <textarea name="comment-{$k}-{$num}" rows="1" style="width: 885px; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-right: 1px;" {$readonly}>{$question['question_message']}</textarea>
                        <div class="btn btn-mini btn-default" onclick="toggleTA({$k}, {$num})" style="margin-top:-5px;">TA Note</div>
                        <textarea name="ta-{$k}-{$num}" id="individual-{$k}-{$num}" rows="1" placeholder=" Message to TA" style="width: 940px; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-bottom: 5px; display: {$display_ta};">{$question['question_grading_note']}</textarea>
                    </td>

                    <td style="background-color:#EEE;">
HTML;
            $old_grade = (isset($question['question_total'])) ? $question['question_total'] : 0;
            print selectBox($k, $num, $old_grade);
            $checked = ($question['question_extra_credit'] == 1) ? "checked" : "";
            print (($question['question_extra_credit'] == 1 && $disabled == "disabled") ? "<input type='hidden' name='ec-{$k}-{$num}' value='on' />" : "");
            print <<<HTML

                        <input onclick='calculatePercentageTotal();' name="ec-{$k}-{$num}" type="checkbox" {$checked} {$disabled} />
HTML;
            if ($k != 0) {
                print <<<HTML
                        <br />
                        <a id="delete-{$k}-{$num}" class="question-icon" onclick="deleteQuestion({$k}, {$num});"><img class="question-icon-cross" src="../toolbox/include/bootstrap/img/glyphicons-halflings.png"></a>
                        <a id="down-{$k}-{$num}" class="question-icon" onclick="moveQuestionDown({$k}, {$num});"><img class="question-icon-down" src="../toolbox/include/bootstrap/img/glyphicons-halflings.png"></a>
                        <a id="up-{$k}-{$num}" class="question-icon" onclick="moveQuestionUp({$k}, {$num});"><img class="question-icon-up" src="../toolbox/include/bootstrap/img/glyphicons-halflings.png"></a>
HTML;
            }
            print <<<HTML
                    </td>
                </tr>
HTML;
        }
        if ($k > 0) {
            print <<<HTML
                <tr id="add-{$k}">
                    <td style="overflow: hidden;">
                        <div class="btn btn-small btn-success"  onclick="addQuestion({$k})"><i class="icon-plus icon-white"></i> Question</div>
                    </td>

                    <td style="border-left: 1px solid #F9F9F9;"></td>
                </tr>
HTML;
        }
    }
    print <<<HTML
                <tr>
                    <td style="border-left: 1px solid #F9F9F9;"></td>
                    <td style="border-left: 1px solid #F9F9F9;"></td>
                </tr>
HTML;
    print <<<HTML
                    <tr>
                        <td style="background-color: #EEE; border-top: 2px solid #CCC;"></td>
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
                  <table class="multi-fields table table-bordered" style=" border: 1px solid #AAA; max-width:50% !important;">
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
                               <input style="width: 200px" name="numeric-label" type="text" class="numeric-label" value="0"/> 
                           </td>  
                            <td>     
                                <input style="width: 60px" type="text" name="max-score-1" class="max-score" value="" /> 
                           </td>                           
                           <td>     
                                <input type="checkbox" name="numeric-extra-1" class="numeric-extra" value="" />
                           </td> 
                        </tr>
                      
                       <tr class="multi-field" id="mult-field-1">
                           <td>
                               <input style="width: 200px" name="numeric-label" type="text" class="numeric-label" value="1"/> 
                           </td>  
                            <td>     
                                <input style="width: 60px" type="text" name="max-score-1" class="max-score" value="" /> 
                           </td>                           
                           <td>     
                                <input type="checkbox" name="numeric-extra-1" class="numeric-extra" value="" />
                           </td> 
                        </tr>
                  </table>
                  <button type="button" id="add-numeric-field">Add </button>  
                  <button type="button" id="remove-numeric-field" id="remove-numeric" style="visibilty:hidden;">Remove</button>   
                </div>  
                <br /> <br />
                Do you want a box for an (optional) message from the TA to the student?
                <input type="radio" name="opt-ta-messg" value="yes" /> Yes
                <input type="radio" name="opt-ta-messg" value="no" /> No
            </div>  
            </fieldset>
            <br/>

            <!-- Should not lock to a particular gradeable type -->
            Who is assigned to grade this item?:
            <br /> <br />
            <input type="radio" name="section-type" value="reg-section" 
            
HTML;
    echo ($g_grade_by_registration===true)?'checked':'';
    print <<<HTML
            /> Registration Section
            <input type="radio" name="section-type" value="grade-section" 
HTML;
    echo ($g_grade_by_registration===false)?'checked':'';
    print <<<HTML
            /> Grading Section
            <br /> <br />
            
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

                NOTE:  Ok to have multiple people assigned to same section
                NOTE:  Paranoid instructors can remove access to any and all teaching assistants / graders after the bulk of grading for this gradeable is complete to force regrade requests to go through the instructor.
                NOTE:  Paranoid instructors can remove access to any and all teaching assistants / graders after the bulk of grading for this gradeable is complete to force regrade requests to go through the instructor.
                NOTE:  Flag as error if some sections have no grader    
            -->
HTML;

    $db->query("SELECT s.user_id, u.user_rcs, u.user_email, s.rubric_id, s.grading_section_id
    FROM homework_grading_sections as s, users as u WHERE u.user_id = s.user_id
    ORDER BY rubric_id, grading_section_id", array());
    $sections = array();
    foreach ($db->rows() as $row) {
        if (!isset($sections[$row['rubric_id']][$row['user_rcs']])) {
            $sections[$row['rubric_id']][$row['user_rcs']] = array();
        }
        $sections[$row['rubric_id']][$row['user_rcs']][] = $row['grading_section_id'];
    }
    asort($sections);

    $db->query("SELECT student_grading_id, count(student_id) as cnt FROM students GROUP BY student_grading_id ORDER BY student_grading_id", array());
    $a = array();
    foreach ($db->rows() as $row) {
        $a[] = "{$row['student_grading_id']} ({$row['cnt']} students)";
    }
    $a = implode(", ", $a);

    print "Available Grading Sections: {$a}<br /><br />";

    $i = 0;
    $db->query("SELECT * FROM users ORDER BY user_rcs ASC", array());
    $users = $db->rows();
    foreach($users as $user) {
        $value =  isset($sections[$old_rubric['rubric_id']][$user['user_rcs']]) ? implode(",", $sections[$old_rubric['rubric_id']][$user['user_rcs']]) : -1;
        print <<<HTML
            <span style='display:inline-block; width:300px; padding-right: 5px'>{$user['user_lastname']},
                    {$user['user_firstname']}:</span>
            <input style='width: 30px; text-align: center' type='text' name='{$user['user_id']}-section'
                    value='{$value}' />
            <br />
HTML;
        $i++;
    }
    
    // TODO: Style this less dumb
    $margintop = ($i*-40) . "px";
    $marginright =  650-(count($rubrics)*25) . "px";
    print <<<HTML
            <div>

                <table border="1" style="float: right; margin-top:{$margintop}; margin-right: {$marginright}">
                    <tr>
                        <td>User</td>
HTML;
    foreach ($rubrics as $id => $number) {
        print <<<HTML
                        <td style="width: 20px; text-align: center">
                            {$number}
                        </td>
HTML;
    }

    print <<<HTML
                </tr>
HTML;

    foreach ($users as $user) {
        print <<<HTML
                    <tr>
                        <td>{$user['user_rcs']}</td>
HTML;

        foreach ($rubrics as $id => $rubric) {
            $number = (isset($sections[$id][$user['user_rcs']])) ? implode(",",$sections[$id][$user['user_rcs']]) : "";
            print <<<HTML
                        <td style="text-align: center">
                            {$number}
                        </td>
HTML;
        }
        print <<<HTML
                    </tr>
HTML;
    }
    print <<<HTML
                </table>
            </div>
            <br />
            <!-- TODO include this section from the Google Doc -->

            <!-- TODO default to the submission + late days for electronic -->
            What date can the TAs start grading this?: <input name="date_grade" id="date_grade" class="datepicker" type="text"
                style="cursor: auto; background-color: #FFF; width: 250px;">
            
            <br />
            
            <!-- TODO default to never -->    
            What date will the TA grades be released to the students? <input name="date_released" id="date_released" class="datepicker" type="text"
                style="cursor: auto; background-color: #FFF; width: 250px;">    
            
            <br />

            What syllabus/iris "bucket" does this item belong to?:
            
            <select name="gradeable-buckets" style="width: 170px;">
                <option value="homework"
                
HTML;
    echo ($g_syllabus_bucket === 'homework')?'selected':'';
    print <<<HTML
                >Homework</option>
                <option value="assignment"
HTML;
    echo ($g_syllabus_bucket === 'assignment')?'selected':'';
    print <<<HTML
                >Assignment</option>
                <option value="quiz"
HTML;
    echo ($g_syllabus_bucket === 'quiz')?'selected':'';
    print <<<HTML
                >Quiz</option>
                <option value="test"
HTML;
    echo ($g_syllabus_bucket === 'test')?'selected':'';
    print <<<HTML
                >Test</option>
                <option value="reading"
HTML;
    echo ($g_syllabus_bucket === 'reading')?'selected':'';
    print <<<HTML
                >Reading</option>
                <option value="participation"
HTML;
    echo ($g_syllabus_bucket === 'participation')?'selected':'';
    print <<<HTML
                >Participation</option>
                <option value="exam"
HTML;
    echo ($g_syllabus_bucket === 'exam')?'selected':'';
    print <<<HTML
                >Exam</option>
                <option value="lab"
HTML;
    echo ($g_syllabus_bucket === 'lab')?'selected':'';
    print <<<HTML
                >Lab</option>
                <option value="recitation"
HTML;
    echo ($g_syllabus_bucket === 'recitation')?'selected':'';
    print <<<HTML
                >Recitation</option>
                <option value="problem-set"
HTML;
    echo ($g_syllabus_bucket === 'problem-set')?'selected':'';
    print <<<HTML
                >ProblemSet</option>
                <option value="project"
HTML;
    echo ($g_syllabus_bucket === 'project')?'selected':'';
    print <<<HTML
                >Project</option>
            </select>

            <br />
            What is the lowest privileged user group that can grade this?
            
            <select name="minimum-grading-group" style="width:180px;">
                <option value='1'
HTML;
    echo ($g_min_grading_group === 1)?'selected':'';
    print <<<HTML
                >Instructor</option>
                <option value='2'
HTML;
    echo ($g_min_grading_group === 2)?'selected':''; 
    print <<<HTML
                >Full Access Grader</option>
                <option value='3'
HTML;
    echo ($g_min_grading_group === 3)?'selected':'';
    print <<<HTML
                >Limited Access Grader</option>
                <option value='4'
HTML;
    echo ($g_min_grading_group === 4)?'selected':'';
    print <<<HTML
                >Student</option>
            </select>
            
            <!-- When the form is completed and the "SAVE GRADEABLE" button is pushed
                
                If this is an electronic assignment:
                    Generate a new config/class.json
                    NOTE: similar to the current format with thsi new gradeable and all other electonic gradeables
                    
                    Writes the inner contents for BUILD_csciXXXX.sh script
                    
                    (probably can't do this due to security concerns) Run BUILD_csciXXXX.sh script
                    
                If this is an edit of an existing AND there are existing grades this gradeable
                regenerates the grade reports. And possibly re-runs the generate grade summaries?
            -->
        </div>
        <div class="modal-footer">
                <button class="btn btn-primary" type="submit" style="margin-top: 10px;">{$string} Gradeable</button>
                <!--<button class="btn import-json" type="button" style="margin-top: 10px;">Import From JSON</button>-->
        </div>
    </form>
</div>

<script type="text/javascript">
    $.fn.serializeObject = function(){
        var o = {};
        var a = this.serializeArray();
        var ignore = [];
        
        ignore.push('csrf_token'); // no need to save csrf to JSON :P
        
        $(':radio').each(function(){
           if(! $(this).is(':checked')){
               //alert($(this).attr('class'));
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

    $(document).ready(function() {
        $(".import-json").click(function(){
            $.post('{$BASE_URL}/account/submit/admin-gradeable.php?action={$action}&id={$old_rubric['rubric_id']}&course='
                +'{$_GET['course']}', 'gradeableJSON=' + JSON.stringify($('form').serializeObject())+'&csrf_token=' 
                + '{$_SESSION['csrf']}', function (response) {
              alert(response);
            });
        }); 

        var numCheckpoints=1;
        
        function addCheckpoint(label, extra_credit){
            var wrapper = $('.checkpoints-table');
            ++numCheckpoints;
            $('#mult-field-0', wrapper).clone(true).appendTo(wrapper).attr('id','mult-field-'+numCheckpoints).find('.checkpoint-label').val(label).focus();
            $('#mult-field-' + numCheckpoints).find('.checkpoint-label').attr('name','checkpoint-label-'+numCheckpoints);
            $('#mult-field-' + numCheckpoints).find('.checkpoint-extra').attr('name','checkpoint-extra-'+numCheckpoints);
            if(extra_credit){
                $('#mult-field-' + numCheckpoints).find('.checkpoint-extra').attr('checked',true); 
            }
            $('#remove-checkpoint-field').show();
            $('#mult-field-' + numCheckpoints).show();
        }
        
        function removeCheckpoint(){
            if (numCheckpoints > 0){
                $('#mult-field-'+numCheckpoints).remove();
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

        var numNumeric=1;
        $('.multi-field-wrapper-numeric').each(function() {
            var wrapper = $('.multi-fields', this);
            $("#add-numeric-field", $(this)).click(function(e) {
                numNumeric++;
                $('#mult-field-0', wrapper).clone(true).appendTo(wrapper).attr('id','mult-field-'+numNumeric).find('.numeric-label').val(numNumeric).focus();
                $('#mult-field-' + numNumeric).find('.numeric-extra').attr('name','numeric-extra-'+numNumeric);
                $('#mult-field-' + numNumeric).find('.max-score').attr('name','max-score'+numNumeric);
                $('#remove-numeric-field').show();
                $('#mult-field-' + numNumeric).show();
            });
            $('#remove-numeric-field').click(function() {
                if (numNumeric > 1){
                    $('#mult-field-'+numNumeric).remove();
                    if(--numNumeric === 1){
                        $('#remove-numeric-field').hide();
                    }
                }
            });
        });
        $('#remove-numeric-field').hide();
        $('.gradeable-type-options').hide();
        
        if ($('input[name=gradeable-type]').is(':checked')){
            $('input[name=gradeable-type]').each(function(){
                if(!($(this).is(':checked'))){
                    $(this).attr("disabled",true);    
                }
            });
        }
        
        if($('#radio-electronic-file').is(':checked')){ 
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

    $('#date_grade').datetimepicker('setDate', (new Date("{$g_grade_start_date}")));;
    $('#date_released').datetimepicker('setDate', (new Date("{$g_grade_released_date}")));;

    function toggleTA(part, question) {
        if(document.getElementById("individual-" + part + "-" + question ).style.display == "block") {
            $("#individual-" + part + "-" + question ).animate({marginBottom:"-80px"});
            setTimeout(function(){document.getElementById("individual-" + part + "-" + question ).style.display = "none";}, 175);

        }
        else {
            $("#individual-" + part + "-" + question ).animate({marginBottom:"5px"});
            setTimeout(function(){document.getElementById("individual-" + part + "-" + question ).style.display = "block";}, 175);
        }

        calculatePercentageTotal();
    }
HTML;

    $parts = "[";
    for($i = 0; $i <= max(array_keys($old_questions)); $i++) {
        $parts .= (isset($old_questions[$i]) ? (count($old_questions[$i]) + (($i > 0) ? 1 : 0)) : 0).",";
    }
    $parts = rtrim($parts, ",");
    $parts .= "]";

    print <<<JS
    
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

    var parts = {$parts};

    function addPart() {
        parts.push(2);
        var partName = parts.length - 1;
        var partNameString = "" + partName;
        var table = document.getElementById("rubricTable");
        var row = table.insertRow(table.rows.length - 2);
        row.id = 'row-' + partName + '-1';
        var cell1 = row.insertCell(0);
        cell1.rowSpan = "2";
        cell1.setAttribute("id", "part-"+partName);
        var cell2 = row.insertCell(1);
        cell2.style.overflow = "hidden";
        var cell3 = row.insertCell(2);
        cell3.style.backgroundColor = "#EEE";
        cell1.innerHTML = '<span id="spanPart' + partName + '">' + partNameString + "</span><br />" +
        '<a id="delete-' + partName + '" class="question-icon" onclick="deletePart(' + partName + ');"><img class="question-icon-cross" src="../toolbox/include/bootstrap/img/glyphicons-halflings.png"></a>' +
        '<br />' +
        "<div class='part_submission_id'><input style='width: 47px;' type='text' name='rubric_part_"+partNameString+"_id' value='_part"+partNameString+"' /></div>";
        cell2.innerHTML = '<textarea name="comment-' + partName + '-1" rows="1" style="width: 885px; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-right: 1px;"></textarea></span>'+
                          '<div class="btn btn-mini btn-default" onclick="toggleTA(' + partName + ',1)" style="margin-top:-5px;">TA Note</div>'+
                          '<textarea name="ta-' + partName + '-1" id="individual-' + partName + '-1" rows="1" placeholder=" Message to TA" style="width: 954px; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-bottom: -80px; display: none;"></textarea>';
        cell3.innerHTML = selectBox(partName, "1") + ' <input onclick="calculatePercentageTotal();" name="ec-' + partName + '-1" type="checkbox" />';

        cell3.innerHTML += "<br />" +
                        "<a id='delete-" + partName + "-1' class=\"question-icon\" onclick=\"deleteQuestion(" + partName + ", 1);\"><img class=\"question-icon-cross\" src=\"../toolbox/include/bootstrap/img/glyphicons-halflings.png\"></a>" +
                        "<a id='down-" + partName + "-1' class=\"question-icon\" onclick=\"moveQuestionDown(" + partName + ", 1);\"><img class=\"question-icon-down\" src=\"../toolbox/include/bootstrap/img/glyphicons-halflings.png\"></a>" +
                        "<a id='up-" + partName + "-1' class=\"question-icon\" onclick=\"moveQuestionUp(" + partName + ", 1);\"><img class=\"question-icon-up\" src=\"../toolbox/include/bootstrap/img/glyphicons-halflings.png\"></a>";
        row = table.insertRow(table.rows.length - 2);
        row.id = 'add-' + partName;
        cell1 = row.insertCell(0);
        cell2 = row.insertCell(1);
        cell2.style.borderLeft = '1px solid #F9F9F9';
        cell1.innerHTML='<div class="btn btn-small btn-success"  onclick="addQuestion('+partName+')"><i class="icon-plus icon-white"></i> Question</div>';
        var elem = $("input[name='part_count']");
        elem.val(parseInt(elem.val())+1);
        togglePartSubmissionId();
    }

    function addQuestion(partName)
    {
        var part = Number(partName);
        if (part <= 0) {
            return;
        }

        var number = 0;
        for (var i = 0; i < parts.length && i <= part; i++) {
            number += parts[i];
        }

        document.getElementById("part-"+partName).rowSpan = '' + (Number(document.getElementById("part-"+partName).rowSpan) + 1);
        var table = document.getElementById("rubricTable");
        var row = table.insertRow(number);
        row.id = 'row-' + partName + '-' + parts[part];
        var cell1 = row.insertCell(0);
        cell1.style.overflow = "hidden";
        var cell2 = row.insertCell(1);
        cell2.style.backgroundColor = "#EEE";
        cell1.innerHTML = '<textarea name="comment-' + partName + "-" + parts[part] + '" rows="1" style="width:885px; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-right: 1px;"></textarea></span>'+
                          '<div class="btn btn-mini btn-default" onclick="toggleTA(' + partName + "," + parts[part] + ')" style="margin-top:-5px;">TA Note</div>'+
                          '<textarea name="ta-' + partName + "-" + parts[part] + '" id="individual-' + partName + "-" + parts[part] + '" rows="1" placeholder=" Message to TA" style="width: 940px; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-bottom: -80px; display: none;"></textarea>';
        cell2.innerHTML = selectBox(partName, parts[part]) + ' <input onclick="calculatePercentageTotal();" name="ec-'+partName+'-'+parts[part]+'" type="checkbox" />';
        cell2.innerHTML += "<br />" +
                        "<a id='delete-" + partName + "-" + parts[part]+"' class=\"question-icon\" onclick=\"deleteQuestion(" + partName + ", " + parts[part] + ");\"><img class=\"question-icon-cross\" src=\"../toolbox/include/bootstrap/img/glyphicons-halflings.png\"></a>" +
                        "<a id='down-" + partName + "-" + parts[part]+"' class=\"question-icon\" onclick=\"moveQuestionDown(" + partName + ", " + parts[part] + ");\"><img class=\"question-icon-down\" src=\"../toolbox/include/bootstrap/img/glyphicons-halflings.png\"></a>" +
                        "<a id='up-" + partName + "-" + parts[part]+"' class=\"question-icon\" onclick=\"moveQuestionUp(" + partName + ", " + parts[part] + ");\"><img class=\"question-icon-up\" src=\"../toolbox/include/bootstrap/img/glyphicons-halflings.png\"></a>";
        parts[Number(partName)] += 1;

    }

    function selectBox(part, question)
    {
        var retVal = '<select name="point-' + part + "-" + question + '" class="points" onchange="calculatePercentageTotal()">';
        for(var i = 0; i <= 100; i++) {
            retVal = retVal + '<option>' + (i * 0.5) + '</option>';
        }
        retVal = retVal + '</select>';

        return retVal;
    }

    function togglePartSubmissionId() {
        if ($("input[name='rubric_parts_sep']").prop('checked') == true) {
            $('.part_submission_id').show();
        }
        else {
            $('.part_submission_id').hide();
        }
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

    function deleteQuestion(part, question) {
        if (part <= 0 || question <= 0) {
            return;
        }

        var row = $('tr#row-' + part + '-' + question);

        var part_id = "";
        if (question == 1) {
            part_id = row.children()[0].children[2].children[0].value;
        }
        row.remove();
        for(var i = question+1; i < parts[part]; i++) {
            updateRow(part, part, i, i-1);
        }
        parts[part] -= 1;

        if (parts[part] == 1) {
            $('tr#add-' + part).remove();
            for (var ii = part + 1; ii < parts.length; ii++) {
                for (var jj = 1; jj < parts[ii]; jj++) {
                    updateRow(ii, ii-1, jj, jj);
                }
                $('span#spanPart' + ii).text(ii-1).attr('id', 'spanPart' + (ii-1));
                $('input[name=rubric_part_' + ii + '_id').attr('name', 'rubric_part_' + (ii-1) + '_id');
                $('tr#add-' + ii).attr('id', 'add-' + (ii-1)).find('div.btn').attr('onclick', 'addQuestion(' + (ii-1) + ')');
                $('td#part-' + ii).attr('id', 'part-' + (ii-1));
                $('a#delete-' + ii).attr('id', 'delete-' + (ii-1)).attr('onclick', 'deletePart(' + (ii-1) + ');');
            }
            parts.splice(part, 1);
        }
        else {
            if (question == 1) {
                $('tr#row-' + part + '-1').prepend('' +
                '<td rowspan="' + parts[part] + '" id="part-' + part + '"><span id="spanPart' + part + '">' + part + '</span><br />' +
                '<a id="delete-' + part + '" class="question-icon" onclick="deletePart(' + part + ');"><img class="question-icon-cross" src="../toolbox/include/bootstrap/img/glyphicons-halflings.png"></a>' +
                '<br />' +
                '<div class="part_submission_id" style="display: none;"><input style="width: 47px;" type="text" name="rubric_part_' + part + '_id" value="' + part_id + '"></div></td>');
            }
            else {
                $('tr#row-' + part + '-1').children('#part-' + part).attr('rowspan', parts[part]);
            }
        }
        calculatePercentageTotal();
    }

    function updateRow(oldPart, newPart, oldNum, newNum) {
        var row = $('tr#row-' + oldPart + '-' + oldNum);
        row.attr('id', 'row-' + newPart + '-' + newNum);
        row.find('textarea[name=comment-' + oldPart + '-' + oldNum + ']').attr('name', 'comment-' + newPart + '-' + newNum);
        row.find('div.btn').attr('onclick', 'toggleTA(' + newPart + ',' + newNum + ')');
        row.find('textarea[name=ta-' + oldPart + '-' + oldNum + ']').attr('name', 'ta-' + newPart + '-' + newNum).attr('id', 'individual-' + newPart + '-' + newNum);
        row.find('select[name=point-' + oldPart + '-' + oldNum + ']').attr('name', 'point-' + newPart + '-' + newNum);
        row.find('input[name=ec-' + oldPart + '-' + oldNum + ']').attr('name', 'ec-' + newPart + '-' + newNum);
        row.find('a[id=delete-' + oldPart + '-' + oldNum + ']').attr('id', 'delete-' + newPart + '-' + newNum).attr('onclick', 'deleteQuestion(' + newPart + ', '+ newNum + ')');
        row.find('a[id=down-' + oldPart + '-' + oldNum + ']').attr('id', 'down-' + newPart + '-' + newNum).attr('onclick', 'moveQuestionDown(' + newPart + ', '+ newNum + ')');
        row.find('a[id=up-' + oldPart + '-' + oldNum + ']').attr('id', 'up-' + newPart + '-' + newNum).attr('onclick', 'moveQuestionUp(' + newPart + ', '+ newNum + ')');
    }

    function moveQuestionDown(part, question) {
        if ((parts[part] - 1) == question || question <= 0 || part <= 0) {
            return;
        }

        var currentRow = $('tr#row-' + part + '-' + question);
        var newRow = $('tr#row-' + part + '-' + (question+1));
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

    function moveQuestionUp(part, question) {
        if (question == 1 || question <= 0 || part <= 0) {
            return;
        }

        var currentRow = $('tr#row-' + part + '-' + question);
        var newRow = $('tr#row-' + part + '-' + (question-1));
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

    function deletePart(part) {
        if (part < 1) {
            return;
        }

        for (var i = 1; i < parts[part]; i++) {
            $('tr#row-' + part + '-' + i).remove();
        }
        $('tr#add-' + part).remove();


        for (var ii = part + 1; ii < parts.length; ii++) {
            for (var jj = 1; jj < parts[ii]; jj++) {
                updateRow(ii, ii-1, jj, jj);
            }
            $('span#spanPart' + ii).text(ii-1).attr('id', 'spanPart' + (ii-1));
            $('input[name=rubric_part_' + ii + '_id').attr('name', 'rubric_part_' + (ii-1) + '_id');
            $('tr#add-' + ii).attr('id', 'add-' + (ii-1)).find('div.btn').attr('onclick', 'addQuestion(' + (ii-1) + ')');;
            $('td#part-' + ii).attr('id', 'part-' + (ii-1));
            $('a#delete-' + ii).attr('id', 'delete-' + (ii-1)).attr('onclick', 'deletePart(' + (ii-1) + ');');
            //$('a#down-' + ii).attr('id', 'down-' + (ii-1)).attr('onclick', 'movePartDown(' + (ii-1) + ');');
            //$('a#up-' + ii).attr('id', 'up-' + (ii-1)).attr('onclick', 'movePartUp(' + (ii-1) + ');');
        }
        parts.splice(part, 1);

        calculatePercentageTotal();
    }
    
    function movePartDown(part) {
        if (parts.length - 1 <= part || part <= 0) {
            return;
        }

        for (var i = 1; i < parts[part]; i++) {
            updateRow(part, -1, i, i);
        }

        for (var j = 1; j < parts[part+1]; j++) {
            updateRow(part+1, part, j, j);
        }

        for (var k = 1; k < parts[part]; k++) {
            updateRow(-1, part+1, k, k);
        }
    }

    function movePartUp(part) {
        if (part <= 1 || parts.length - 1 < part) {
            return;
        }
    }

    togglePartSubmissionId();
    calculatePercentageTotal();
JS;
    print <<<HTML
</script>
HTML;
}

include "../footer.php";