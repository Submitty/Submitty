<?php

include "../../toolbox/functions.php";

use lib\Database;
use app\models;

check_administrator();

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf']) {
    die("invalid csrf token");
}

$start = microtime_float();

// Make sure we actually have a created output directory
if (!is_dir(implode("/",array(__SUBMISSION_SERVER__,"reports")))) {
    mkdir(implode("/",array(__SUBMISSION_SERVER__,"reports")));
}

$nl = "\n";
$write_output = True;

// Query the database for all students registered in the class
$params = array();

function autogradingTotalAwarded($g_id, $student_id, $active_version){
    $total = 0;
    $results_file = __SUBMISSION_SERVER__."/results/".$g_id."/".$student_id."/".$active_version."/submission.json";
    if (file_exists($results_file)) {
        $results_file_contents = file_get_contents($results_file);
        $results = json_decode($results_file_contents, true);
        foreach($results['testcases'] as $testcase){
            $total += floatval($testcase['points_awarded']);
        }
    }
    return $total;
}

function getAutogradingMaxScore($g_id){
    $total = 0;
    $build_file = __SUBMISSION_SERVER__."/config/build/build_".$g_id.".json";
     if (file_exists($build_file)) {
        $build_file_contents = file_get_contents($build_file);
        $results = json_decode($build_file_contents, true);
        foreach($results['testcases'] as $testcase){
            $testcase_value = floatval($testcase['points']);
            if ($testcase_value > 0 && !$testcase['extra_credit']){
                $total += $testcase_value;
            }
        }
    }
    return $total;
}


$db->query("SELECT * FROM users WHERE (user_group=4 AND registration_section IS NOT NULL) OR (manual_registration) ORDER BY user_id ASC", array());
foreach($db->rows() as $student_record) {
    // Gather student info, set output filename, reset output
    $student_id = $student_record["user_id"];
    $student_first_name = $student_record["user_firstname"];
    $student_last_name = $student_record["user_lastname"];
    $student_output_filename = $student_id . ".txt";	
    $student_section = intval($student_record['registration_section']);
    $late_days_used_overall = 0;
    $params = array($student_id);
    
    // SQL sorcery ༼╰( ͡° ͜ʖ ͡° )つ──☆*:・ﾟ
    $db->query("
    SELECT * FROM (
        SELECT 
            g_syllabus_bucket, 
            g_title, 
            g_gradeable_type, 
            g.g_id, 
            u.user_id, 
            case when score is null then -100 else score end, 
            titles, 
            comments,
            scores,
            eg_submission_due_date,
            gd_status,
            gd_grader_id,
            gd_overall_comment,
            is_extra_credits,
            gd_active_version,
            grading_notes,
            max_scores
        FROM
            users AS u CROSS JOIN gradeable AS g 
            LEFT JOIN (
                SELECT 
                    gd.g_id, 
                    gd_user_id, 
                    score, 
                    titles, 
                    comments,
                    scores,
                    eg_submission_due_date,
                    gd_status,
                    gd_grader_id,
                    gd_overall_comment,
                    is_extra_credits,
                    gd_active_version,
                    grading_notes,
                    max_scores
                FROM 
                    gradeable_data AS gd INNER JOIN(
                    SELECT 
                        gd_id, 
                        SUM(gcd_score) AS score, 
                        array_agg(gc_title ORDER BY gc_order ASC) AS titles, 
                        array_agg(gcd_component_comment ORDER BY gc_order ASC) AS comments,
                        array_agg(gcd_score ORDER BY gc_order ASC) AS scores,
                        array_agg(gc_is_extra_credit ORDER BY gc_order ASC) AS is_extra_credits,
                        array_agg(gc_student_comment ORDER BY gc_order ASC) AS grading_notes,
                        array_agg(gc_max_value ORDER BY gc_order ASC) AS max_scores
                    FROM 
                        gradeable_component_data AS gcd INNER JOIN 
                            gradeable_component AS gc ON gcd.gc_id=gc.gc_id
                    GROUP BY gd_id
                ) AS gd_sum ON gd.gd_id=gd_sum.gd_id
                INNER JOIN electronic_gradeable AS eg ON gd.g_id=eg.g_id
            ) AS total ON total.g_id = g.g_id AND total.gd_user_id=u.user_id
        WHERE 
            g_grade_released_date < now()
        ORDER BY g_syllabus_bucket ASC, g_grade_released_date ASC, u.user_id ASC
        ) AS user_grades
    WHERE user_id=?
    AND g_gradeable_type='0'
        ",array($student_id));
	
    foreach($db->rows() as $gradeable){
        $params = array($student_id, $gradeable['eg_submission_due_date']);
        $db->query("SELECT allowed_late_days FROM late_days WHERE user_id=? AND since_timestamp <= ? ORDER BY since_timestamp DESC LIMIT 1", $params);
        $late_day = $db->row();
        $student_allowed_lates = isset($late_day['allowed_late_days']) ? $late_day['allowed_late_days'] : 0;
        $g_id = $gradeable['g_id'];
        $rubric_total = 0;
        $ta_max_score = 0;
        // Gather student info, set output filename, reset output
        $student_output_text_main = "";
        $student_output_text = "";
        $student_grade = 0;
        $grade_comment = "";
        
        if ($gradeable['gd_status'] == 1) {
            $db->query("SELECT late_days_used FROM late_days_used WHERE g_id=? AND user_id=?", array($g_id, $student_id));
            $late_days_used = $db->row()['late_days_used'];
            $grade_days_late = $late_days_used;
        }
        else {
            $grade_days_late = 0;
        }
    
        $late_days_used_overall += $grade_days_late;
        
        // Check to see if student has been graded yet
        $graded=true;
        if($gradeable["score"] != -100 && isset($gradeable["gd_grader_id"])) {
            $grade_user_id = $gradeable["gd_grader_id"];
            $grade_comment = htmlspecialchars($gradeable["gd_overall_comment"]);
            // Query database to gather TA info
            $params = array($grade_user_id);
            $db->query("SELECT * FROM users WHERE user_id=?", $params);
            $user_record = $db->row();
            $grade_user_first_name = $user_record["user_firstname"];
            $grade_user_last_name = $user_record["user_lastname"];
            $grade_user_email = $user_record["user_email"];
            
            // Generate output 
            $student_output_text_main .= strtoupper($gradeable['g_title']) . " GRADE" . $nl;
            $student_output_text_main .= "----------------------------------------------------------------------" . $nl;
            if (!($grade_user_first_name == "Mentor" || $grade_user_first_name == "TA" || $grade_user_first_name == "")) {
                $student_output_text_main .= "Graded by: " . $grade_user_first_name . " " . $grade_user_last_name . " <" . $grade_user_email . ">" . $nl;
            }
            $student_output_text_main .= "Any regrade requests are due within 7 days of posting to: " . $grade_user_email . $nl;
            $student_output_text_main .= "Late days used on this homework: " . $grade_days_late . $nl;
            if ($student_allowed_lates > 0) {
                $student_output_text_main .= "Late days used overall: " . $late_days_used_overall . $nl;
                $student_output_text_main .= "Late days remaining: " . max(0, $student_allowed_lates - $late_days_used_overall) . $nl;
            }
            $student_output_text_main .= "----------------------------------------------------------------------" . $nl;

            // Query database for specific questions from this rubric
            $grading_notes = pgArrayToPhp($gradeable['comments']);
            $question_totals = pgArrayToPhp($gradeable['scores']);
            $question_messages = pgArrayToPhp($gradeable['titles']);
            $question_extra_credits = pgArrayToPhp($gradeable['is_extra_credits']);
            $question_grading_notes = pgArrayToPhp($gradeable['grading_notes']);
            $question_max_scores = pgArrayToPhp($gradeable['max_scores']);
            $question_total = 0; 

            $submit_file = __SUBMISSION_SERVER__."/results/".$gradeable['g_id']."/".$student_id."/".$gradeable['gd_active_version']."/.submit.grade";
            if (!file_exists($submit_file)) {
                $student_output_text .= $nl.$nl."NO AUTO-GRADE RECORD FOUND (contact the instructor if you did submit this assignment)".$nl.$nl;
            }
            else {
                $auto_grading_awarded = autogradingTotalAwarded($gradeable['g_id'], $student_id, $gradeable['gd_active_version']);        
                $auto_grading_max_score = getAutogradingMaxScore($gradeable['g_id']);                                                                                
                $student_output_text .= "AUTO-GRADING TOTAL [ " . $auto_grading_awarded . " / " . $auto_grading_max_score . " ]";
                $gradefilecontents = file_get_contents($submit_file);
                $student_output_text .= $nl.$nl.$gradefilecontents.$nl;
            } 

            for ($i=0; $i < count($grading_notes); ++$i){
                $grading_note = $grading_notes[$i];
                $question_total = floatval($question_totals[$i]);
                $question_max_score = floatval($question_max_scores[$i]);
                $question_message = $question_messages[$i];
                $question_extra_credit = intval($question_extra_credits[$i]) == 1;
                $question_grading_note = $question_grading_notes[$i];
                // ensure we have indexes for this part
                if($question_total == -100){
                    $grade_question_score = "ERROR";
                    $grade_question_comment = "[ ERROR GRADE MISSING ]";
                    // Found error, subtract 1 million to ensure we catch the bad grade
                    $student_grade -= 1000000;
                }
               else{
                    $grade_question_score = floatval($question_total);
                    $grade_question_comment = htmlspecialchars($grading_note);
                }
                // Generate output for each question
                $student_output_text .= $question_message . " [ " . $grade_question_score . " / " . $question_max_score . " ]" . $nl;
                // general rubric notes intended for TA & student
                if ($question_grading_note != "") {
                    $student_output_text .= "Rubric: ". $question_grading_note . $nl;
                }
                if ($grade_question_comment != "") {
                    $student_output_text .= $nl."   TA NOTE: " . $grade_question_comment . $nl;
                }
                else if ($question_default != "" && isset($question_default) && $question_total == $grade_question_score) {
                    $student_output_text .= $nl."   TA NOTE: " . $question_default . $nl;
                }
                $student_output_text .= $nl;
                // Keep track of students grade and rubric total
                $student_grade += $grade_question_score;
                $rubric_total += (($question_extra_credit && $question_max_score > 0) ? 0 : $question_max_score);
                $ta_max_score += (($question_extra_credit && $question_max_score > 0) ? 0 : $question_max_score);
            }
                                                            //TODO replace with total overall score
            $student_output_text .= "TA GRADING TOTAL [ " . $student_grade . " / " . $ta_max_score . " ]". $nl;
            $student_output_text .= "----------------------------------------------------------------------" . $nl;
            $rubric_total += $auto_grading_max_score;
            $student_grade += $auto_grading_awarded;
        } 
    
        if($write_output){
            $student_final_grade = $student_grade;
            $student_output_last = strtoupper($gradeable['g_title']) . " GRADE [ " . $student_final_grade . " / " . $rubric_total . " ]" . $nl;
            $student_output_last .= $nl;
            $student_output_last .= "OVERALL NOTE FROM TA: " . ($grade_comment != "" ? $grade_comment . $nl : "No Note") . $nl;
            $student_output_last .= "----------------------------------------------------------------------" . $nl;

            $dir = implode("/", array(__SUBMISSION_SERVER__, "reports", $gradeable['g_id']));
            
            if (!create_dir($dir)) {
                print "failed to create directory {$dir}";
                exit();
            }
            $save_filename = implode("/", array($dir, $student_output_filename));

            $student_final_output = $student_output_text_main . $student_output_text. $student_output_last;

            if ($student_final_output == "") {
                $student_final_output = "[ TA HAS NOT GRADED ASSIGNMENT, CHECK BACK LATER ]";
            }

            if (file_put_contents($save_filename, $student_final_output) === false) {
                print "failed to write {$save_filename}\n";
            }
        }
    }
}

echo "updated";