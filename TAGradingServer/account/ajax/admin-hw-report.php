<?php

include "../../toolbox/functions.php";

use lib\Database;

check_administrator();

$start = microtime_float();

// Make sure we actually have a created output directory
if (!is_dir(implode("/",array(__SUBMISSION_SERVER__,"reports")))) {
    mkdir(implode("/",array(__SUBMISSION_SERVER__,"reports")));
}

$send_email = (isset($_GET['email'])) ? intval($_GET['email']) == 1 : false;
$only_regrade = isset($_GET['regrade']) ? intval($_GET['regrade']) == 1 : false;
$all = isset($_GET['all']) ? intval($_GET['all']) == 1 : false;

/************************************/
/* Output Individual Student Files  */
/************************************/

$academic_integrity = array();
$academic_resolutions = array();

$db->query("SELECT * FROM grades_academic_integrity ORDER BY rubric_id",array());
foreach ($db->rows() as $row) {
    if (!isset($academic_integrity[$row['rubric_id']])) {
        $academic_integrity[$row['rubric_id']] = array();
        $academic_resolutions[$row['rubric_id']] = array();
    }
    array_push($academic_integrity[$row['rubric_id']],$row['student_rcs']);
    if ($row['penalty'] != null) {
        $academic_resolutions[$row['rubric_id']][$row['student_rcs']] = floatval($row['penalty']);
    }
}

/***********/
/* OUTPUT  */
/***********/

$nl = "\n";
$write_output = True;

$get_rubric_id = intval($_GET["hw"]);
Database::query("SELECT * FROM rubrics WHERE rubric_id=?", array($get_rubric_id));
$get_rubric = Database::row();
if (!isset($get_rubric['rubric_id'])) {
    echo "failed|Invalid ID";
    exit(1);
}

$rubrics = array();
$db->query("SELECT * FROM rubrics WHERE rubric_due_date<=? ORDER BY rubric_due_date ASC", array($get_rubric['rubric_due_date']));
foreach($db->rows() as $row) {
    array_push($rubrics, $row);
}

// Query the database for all students registered in the class
$params = array();
$db->query("
SELECT *
FROM students
ORDER BY student_rcs ASC", $params);
foreach($db->rows() as $student_record)
{
    $student_id = intval($student_record["student_id"]);
    $student_rcs = $student_record["student_rcs"];
    $student_last_name = $student_record["student_last_name"];
    $student_first_name = $student_record["student_first_name"];
    $student_output_filename = $student_rcs . ".txt";

    $student_section_id = $student_record["student_section_id"];

    if(intval($student_section_id) != 0) {
        foreach ($rubrics as $k => $rubric) {
            $params = array($student_rcs, $rubric['rubric_due_date']);
            $db->query("SELECT allowed_lates FROM late_days WHERE student_rcs=? AND since_timestamp <= ? ORDER BY since_timestamp DESC LIMIT 1", $params);
            $late_day = $db->row();
            $student_allowed_lates = isset($late_day['allowed_lates']) ? $late_day['allowed_lates'] : 0;
            $rubric_id = $rubric['rubric_id'];
            $rubric_sep = $rubric['rubric_parts_sep'];
            $rubric_total = 0;
            $regraded_only = !($rubric_id == $get_rubric_id);

            // Gather student info, set output filename, reset output
            $student_output_text_main = "";
            $student_output_academic = "";
            $student_output_text = array();
            $student_grade = array();
            $grade_comment = "";

            if(isset($academic_integrity[$rubric_id]) && in_array($student_rcs, $academic_integrity[$rubric_id])
                && !(isset($academic_resolutions[$rubric_id]) && array_key_exists($student_rcs, $academic_resolutions[$rubric_id]))) {

                $db->query("SELECT question_part_number FROM questions WHERE rubric_id=? GROUP BY question_part_number",array($rubric_id));
                for($i = 1; $i <= count($db->rows()); $i++) {
                    $student_output_text[$i] = "academic";
                }
                $student_output_text_main .= "[ YOUR HOMEWORK IS BEING FURTHER REVIEWED FOR EVIDENCE OF ACADEMIC INTEGRITY VIOLATION ]";
            }
            else {
                // Query database to gather overall late days statistics
                $late_days_used_overall = 0;
                $late_days_used_remaining = $student_allowed_lates;

                $params = array($student_rcs, $rubric['rubric_due_date']);
                $db->query("SELECT SUM(g.grade_days_late) as late
                            FROM grades AS g, rubrics AS r
                            WHERE g.student_rcs=? AND g.rubric_id=r.rubric_id AND r.rubric_due_date<=? AND g.grade_status=1", $params);
                $row = $db->row();


                $db->query("SELECT * FROM late_day_exceptions WHERE ex_student_rcs=? and ex_rubric_id=?", array($student_rcs, $rubric_id));
                $ex = $db->row();
                if (isset($ex['ex_late_days'])) {
                    $grade_days_late = $row['late'] - $ex['ex_late_days'];
                }
                else {
                    $grade_days_late = $row['late'];
                }
                $late_days_used_overall += $grade_days_late;
                $late_days_used_remaining -= $grade_days_late;

                // Query database to get grades (and regrades if old homeworks)
                $params = array($rubric_id, $student_rcs);
                if (($regraded_only == true || $only_regrade == true) && $all != true) {
                    $db->query("SELECT * FROM grades WHERE rubric_id=? AND student_rcs=? AND grade_is_regraded=1",$params);
                }
                else {
                    $db->query("SELECT * FROM grades WHERE rubric_id=? AND student_rcs=?", $params);
                }
                $grade_record = $db->row();
                // Check to see if student has been graded yet
                if(isset($grade_record["grade_id"])) {
                    $grade_id = intval($grade_record["grade_id"]);
                    $grade_user_id = intval($grade_record["grade_user_id"]);
                    $grade_comment = $grade_record["grade_comment"];
                    $grade_days_late = intval($grade_record["grade_days_late"]);

                    // We don't want to deduct late days for no submissions or for submissions that are so late that
                    // the student receives an automatic zero
                    if($grade_record['grade_status'] == 0) {
                        $grade_days_late = 0;
                    }

                    // Query database to gather TA info
                    $params = array($grade_user_id);
                    $db->query("SELECT * FROM users WHERE user_id=?", $params);
                    $user_record = $db->row();

                    $grade_user_first_name = $user_record["user_firstname"];
                    $grade_user_last_name = $user_record["user_lastname"];
                    $grade_user_email = $user_record["user_email"];

                    ////////////////////////////////////////////////////////////////////////////
                    //
                    // Most of the information you will need for the output is defined above
                    //
                    ////////////////////////////////////////////////////////////////////////////

                    // Generate output (period is string concatenation in PHP)
                    $student_output_text_main .= strtoupper($rubric['rubric_name']) . " GRADE" . $nl;
                    $student_output_text_main .= "----------------------------------------------------------------------" . $nl;


		    if ($grade_user_first_name == "Mentor" || $grade_user_first_name == "TA" || $grade_user_first_name == "") {
		    } else {
		      $student_output_text_main .= "Graded by: " . $grade_user_first_name . " " . $grade_user_last_name . " &lt;" . $grade_user_email . "&gt;" . $nl;
                    }
                    $student_output_text_main .= "Any regrade requests are due within 7 days of posting to: " . $grade_user_email . $nl;


                    $student_output_text_main .= "Late days used on this homework: " . $grade_days_late . $nl;
                    if ($student_allowed_lates > 0) {
                        $student_output_text_main .= "Late days used overall: " . $late_days_used_overall . $nl;
                        $student_output_text_main .= "Late days remaining: " . $late_days_used_remaining . $nl;
                    }
                    //$student_output_text_main .= $nl;
                    $student_output_text_main .= "----------------------------------------------------------------------" . $nl;

                    // Query database for specific questions from this rubric
                    $part_grade = array();
                    $question_part_number_last = -1;

                    $params = array($rubric_id);
                    $db->query("SELECT * FROM questions WHERE rubric_id=? ORDER BY question_part_number ASC, question_number ASC", $params);
                    foreach($db->rows() as $question_record)
                    {

                        // Gather question info
                        $question_id = intval($question_record["question_id"]);
                        $question_part_number = intval($question_record["question_part_number"]);
                        $question_message = $question_record["question_message"];
                        $question_grading_note = $question_record["question_grading_note"];
                        $question_total = floatval($question_record["question_total"]);
                        $question_default = $question_record["question_default"];
                        $question_extra_credit = intval($question_record["question_extra_credit"]) == 1;

                        // ensure we have indexes for this part
                        if (!isset($student_output_text[$question_part_number]) || !isset($student_grade[$question_part_number])) {
                            $student_output_text[$question_part_number] = "";
                            $student_grade[$question_part_number] = 0;
                            $part_grade[$question_part_number] = 0;
                        }

                        // Gather grade for student for this question
                        $params = array($grade_id, $question_id);
                        $db->query("SELECT * FROM grades_questions WHERE grade_id=? AND question_id=?", $params);
                        $grade_question_record = $db->row();

                        if(!isset($grade_question_record["grade_question_score"]))
                        {
                            $grade_question_score = "ERROR";
                            $grade_question_comment = "[ ERROR GRADE MISSING ]";

                            // Found error, subtract 1 million to ensure we catch the bad grade
                            $student_grade[$question_part_number] -= 1000000;
                        }
                        else
                        {
                            $grade_question_score = floatval($grade_question_record["grade_question_score"]);
                            //$grade_question_comment = $grade_question_record["grade_question_comment"];
                            $grade_question_comment = clean_string_javascript($grade_question_record["grade_question_comment"]);
                        }

                        // we only need to do that for homeworks without separate parts
                        if($question_part_number_last != $question_part_number && $rubric_sep != true)
                        {
                            //$student_output_text[$question_part_number] .= "PART " . $question_part_number . $nl;
                            //$student_output_text[$question_part_number] .= "------" . $nl;
                        }

                        // Generate output for each question
                        $student_output_text[$question_part_number] .= $question_message . " [ " . $grade_question_score . " / " . $question_total . " ]" . $nl;

                        // general rubric notes intended for TA & student
                        if ($question_grading_note != "") {
                            $student_output_text[$question_part_number] .= "Rubric: ". $question_grading_note . $nl;
                        }

                        if ($grade_question_comment != "") {
                            $student_output_text[$question_part_number] .= $nl."   TA NOTE: " . $grade_question_comment . $nl;
                        }
                        else if ($question_default != "" && isset($question_default) && $question_total == $grade_question_score) {
                            $student_output_text[$question_part_number] .= $nl."   TA NOTE: " . $question_default . $nl;
                        }

                        $student_output_text[$question_part_number] .= $nl;

                        // Keep track of students grade and rubric total
                        $student_grade[$question_part_number] += $grade_question_score;

                        $question_part_number_last = $question_part_number;
                    }

                    // TODO: Put together all outputs if not separate parts
                    foreach($student_output_text as $question_part_number => $v) {
                        // Generate output for overall comment from TA and overall grade

			            if ($question_part_number == 0) {
                        	$student_output_text[$question_part_number] .= "AUTO-GRADING TOTAL [ " . $student_grade[$question_part_number] . " / " . $part_grade[$question_part_number] . " ]";

                            // TODO: Replace this with using the stored value in the database for each grade
			 	            // get the active assignment to grade
			                $json_path = __SUBMISSION_SERVER__."/submissions/".$rubric['rubric_submission_id']."/".$student_rcs."/user_assignment_settings.json";

                            $json = file_get_contents($json_path);

	                        if ($json === false) {
                                $student_output_text[$question_part_number] .= $nl.$nl."NO GRADE FOUND (contact the instructor if you did submit this assignment)".$nl.$nl;
                            }
                            else {
   	                            $json = json_decode($json, true);
	                            $submission_number = intval($json['active_assignment']);
                                $submit_file = __SUBMISSION_SERVER__."/results/".$rubric['rubric_submission_id']."/".$student_rcs."/".$submission_number."/.submit.grade";

                                $gradefilecontents = file_get_contents($submit_file);
                                if ($gradefilecontents === false) {
                                    $student_output_text[$question_part_number] .= $nl.$nl."NO GRADE FOUND (contact the instructor if you did submit this assignment)".$nl.$nl;
                                }
                                else {
                                    $student_output_text[$question_part_number] .= $nl.$nl.$gradefilecontents.$nl;
                                }
                            }

                        }
                        else {
                            if ($rubric_sep) {
                                $student_output_text[$question_part_number] .= "PART " . $question_part_number . " GRADE [ " . $student_grade[$question_part_number] . " / " . $part_grade[$question_part_number] . " ]". $nl;
                            }
                            else {
                                $student_output_text[$question_part_number] .= "TA GRADING TOTAL [ " . $student_grade[$question_part_number] . " / " . $part_grade[$question_part_number] . " ]". $nl;
                            }
                        }

                        $student_output_text[$question_part_number] .= "----------------------------------------------------------------------" . $nl;
                    }

                    // If output is all one file, condense to one element array
                    if ($rubric_sep == false) {
                        $student_output_text = array(implode($nl, $student_output_text));
                    }
                }
            }

            if($write_output)
            {
                $student_final_grade = array_sum($student_grade);
                if(isset($academic_resolutions[$rubric_id]) && array_key_exists($student_rcs, $academic_resolutions[$rubric_id]))
                {
                    $student_resolution = $academic_resolutions[$rubric_id];
                    $student_penalty_grade = $student_final_grade * floatval($student_resolution[$student_rcs]);
                    $student_output_academic .= "[ YOUR HOMEWORK IS BEING PENALIZED DUE TO AN ACADEMIC INTEGRITY VIOLATION ]" . $nl;
                    $student_output_academic .= "[ PENALTY: -" . intval(100.0 - (100.0 * floatval($student_resolution[$student_rcs]))) . "% OFF OF THE ORIGINAL GRADE ]" . $nl;
                    $student_output_academic .= "PENALIZED " . strtoupper($rubric['rubric_name']) . " GRADE [ " . $student_penalty_grade . " / " . $rubric_total . " ]". $nl;
                    $student_output_academic .= "----------------------------------------------------------------------" . $nl;
                }

                $student_output_last = strtoupper($rubric['rubric_name']) . " GRADE [ " . $student_final_grade . " / " . $rubric_total . " ]" . $nl;
                $student_output_last .= $nl;
                $student_output_last .= "OVERALL NOTE FROM TA: " . ($grade_comment != "" ? $grade_comment . $nl : "No Note") . $nl;
                $student_output_last .= "----------------------------------------------------------------------" . $nl;

                $rubric_submission_parts = explode(",", $rubric['rubric_parts_submission_id']);
                foreach($student_output_text as $k => $output) {
                    if ($output == "") { continue; }

                    if($rubric_sep == true) {
                        $dir = implode("/",array(__SUBMISSION_SERVER__, "reports", $rubric['rubric_submission_id'].$rubric_submission_parts[$k-1]));
                    }
                    else {
                        $dir = implode("/", array(__SUBMISSION_SERVER__, "reports", $rubric['rubric_submission_id']));
                    }
                    if (!create_dir($dir)) {
                        print "failed to create directory {$dir}";
                        exit();
                    }
                    $save_filename = implode("/", array($dir, $student_output_filename));

                    $student_final_output = $student_output_text_main . $output . $student_output_last;
                    if ($output == "academic") {
                        $student_final_output .= $student_output_academic;
                    }
                    else {
                        if ($student_final_output == "") {
                            $student_final_output = "[ TA HAS NOT GRADED ASSIGNMENT, CHECK BACK LATER ]";
                        }

                        $student_final_output .= $student_output_academic;
                    }
                    //print $student_final_output." ".$save_filename."<br />";
                    if (file_put_contents($save_filename, $student_final_output) === false) {
                        print "failed to write {$save_filename}\n";
                    }
                }
            }
        }
    }
}

$db->query("UPDATE grades SET grade_is_regraded=0",array());

echo "updated";
