<?php

use \lib\Database;
require "LateDaysCalculation.php";

class HWReport
{
    const report_query = "SELECT * FROM (
        SELECT
            g_syllabus_bucket,
            g_title,
            g_gradeable_type,
            g.g_id,
            u.user_id,
            u.user_firstname,
            u.user_lastname,
            u.user_email,
            u.registration_section,
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
            max_scores,
            grader_id,
            grader_firstname,
            grader_lastname,
            grader_preferred,
            grader_group,
            grader_email
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
                    max_scores,
                    grader.user_id as grader_id,
                    grader.user_firstname as grader_firstname,
                    grader.user_lastname as grader_lastname,
                    grader.user_preferred_firstname as grader_preferred,
                    grader.user_group as grader_group,
                    grader.user_email as grader_email
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
                    LEFT JOIN (
                      SELECT user_id, user_firstname, user_lastname, user_preferred_firstname, user_group, user_email
                      FROM users
                    ) AS grader ON grader.user_id = gd.gd_grader_id
            ) AS total ON total.g_id = g.g_id AND total.gd_user_id=u.user_id
        ORDER BY g_syllabus_bucket ASC, g_grade_released_date ASC, u.user_id ASC
        ) AS user_grades

    WHERE g_gradeable_type='0'";

    private function autogradingTotalAwarded($g_id, $student_id, $active_version){
        $total = 0;
        $results_file = __SUBMISSION_SERVER__."/results/".$g_id."/".$student_id."/".$active_version."/results.json";
        if (file_exists($results_file)) {
            $results_file_contents = file_get_contents($results_file);
            $results = json_decode($results_file_contents, true);
            if (isset($results['testcases'])) {
                foreach ($results['testcases'] as $testcase) {
                    $total += floatval($testcase['points_awarded']);
                }
            }
        }
        return $total;
    }

    private function getAutogradingMaxScore($g_id){
        $total = 0;
        $build_file = __SUBMISSION_SERVER__."/config/build/build_".$g_id.".json";
        if (file_exists($build_file)) {
            $build_file_contents = file_get_contents($build_file);
            $results = json_decode($build_file_contents, true);
            if (isset($results['testcases'])) {
                foreach ($results['testcases'] as $testcase) {
                    $testcase_value = floatval($testcase['points']);
                    if ($testcase_value > 0 && !$testcase['extra_credit']) {
                        $total += $testcase_value;
                    }
                }
            }
        }
        return $total;
    }

    private function getReportDataAll(){
        global $db;
        $db->query(self::report_query);
        return $db->rows();
    }

    private function getReportDataSingle($student_id, $gradeable_id){
        $where_clause = " AND user_id=? AND g_id=?";
        $params = array($student_id, $gradeable_id);

        global $db;
        $db->query(self::report_query.$where_clause, $params);
        return $db->rows();
    }

    private function getReportDataStudentAll($student_id){
        $where_clause = " AND user_id=?";
        $params = array($student_id);

        global $db;
        $db->query(self::report_query.$where_clause, $params);
        return $db->rows();
    }

    private function getReportDataGradeableAll($gradeable_id){
        $where_clause = " AND g_id=?";
        $params = array($gradeable_id);

        global $db;
        $db->query(self::report_query.$where_clause, $params);
        return $db->rows();
    }

    private function generateReport($gradeable, $ldu, $graders){
        $start = microtime_float();
        // Make sure we actually have a created output directory
        if (!is_dir(implode("/",array(__SUBMISSION_SERVER__,"reports")))) {
            mkdir(implode("/",array(__SUBMISSION_SERVER__,"reports")));
        }
        $nl = "\n";
        $write_output = True;
        $g_id = $gradeable['g_id'];
        $rubric_total = 0;
        $ta_max_score = 0;
        // Gather student info, set output filename, reset output
        $student_output_text_main = "";
        $student_output_text = "";
        $student_grade = 0;
        $grade_comment = "";

        $student_id = $gradeable["user_id"];
        $student_first_name = $gradeable["user_firstname"];
        $student_last_name = $gradeable["user_lastname"];
        $student_output_filename = $student_id . ".txt";
        $student_section = intval($gradeable['registration_section']);
        $late_days_used_overall = 0;

        // Check to see if student has been graded yet
        $graded=true;
        if($gradeable["score"] != -100 && isset($gradeable["gd_grader_id"])) {
            $grade_user_id = $gradeable["gd_grader_id"];
            $grade_comment = htmlspecialchars($gradeable["gd_overall_comment"]);
            // Query database to gather TA info

            // Generate output
            $student_output_text_main .= strtoupper($gradeable['g_title']) . " GRADE" . $nl;
            $student_output_text_main .= "----------------------------------------------------------------------" . $nl;
            if (isset($gradeable['grader_id']) && $gradeable['grader_group'] < 3) {
                $firstname = $gradeable['grader_firstname'];
                if (isset($gradeable['grader_preferred']) && $gradeable['grader_preferred'] !== "") {
                    $firstname = $gradeable['grader_preferred'];
                }
                $student_output_text_main .= "Graded by: {$firstname} {$gradeable['grader_lastname']} <{$gradeable['grader_email']}>" . $nl;
            }

            $late_days = $ldu->get_gradeable($student_id, $g_id);

            if (isset($gradeable['grader_id'])) {
                $student_output_text_main .= "Any regrade requests are due within 7 days of posting to: " . $gradeable['grader_email'] . $nl;
            }
            else {
                $student_output_text_main .= "Any regrade requests are due within 7 days of posting";
            }


            if ($late_days['late_days_used'] > 0) {
                $student_output_text_main .= "This submission was " . $late_days['late_days_used'] . " day(s) after the due date." . $nl;
            }
            if ($late_days['extensions'] > 0) {
                $student_output_text_main .= "You have a " . $late_days['extensions'] . " day extension on this assignment." . $nl;
            }
            $student_output_text_main .= "Homework status: " . $late_days['status'] . $nl;
            if (strpos($late_days['status'], 'Bad') !== false) {
                $student_output_text_main .= "NOTE:  HOMEWORK GRADE WILL BE RECORDED AS ZERO.";
                $student_output_text_main .= "  Contact your TA or instructor if you believe this is an error." . $nl;
            }
            if ($late_days['late_days_charged'] > 0) {
                $student_output_text_main .= "Number of late days used for this homework: " . $late_days['late_days_charged'] . $nl;
            }
            $student_output_text_main .= "Total late days used this semester: " . $late_days['total_late_used'] . " (up to and including this assignment)" . $nl;
            $student_output_text_main .= "Late days remaining for the semester: " . $late_days['remaining_days'] . " (as of the due date of this homework)" . $nl;


            $student_output_text_main .= "----------------------------------------------------------------------" . $nl;
            $grading_notes = pgArrayToPhp($gradeable['comments']);
            $question_totals = pgArrayToPhp($gradeable['scores']);
            $question_messages = pgArrayToPhp($gradeable['titles']);
            $question_extra_credits = pgArrayToPhp($gradeable['is_extra_credits'], true);
            $question_grading_notes = pgArrayToPhp($gradeable['grading_notes']);
            $question_max_scores = pgArrayToPhp($gradeable['max_scores']);
            $question_total = 0;

            $active_version = $gradeable['gd_active_version'];
            $submit_file = __SUBMISSION_SERVER__."/results/".$gradeable['g_id']."/".$student_id."/".$active_version."/results_grade.txt";
            $auto_grading_max_score = 0;
            $auto_grading_awarded = 0;
            if (!file_exists($submit_file)) {
                $student_output_text .= $nl.$nl."NO AUTO-GRADE RECORD FOUND (contact the instructor if you did submit this assignment)".$nl.$nl;
            }
            else {
                $auto_grading_awarded = $this->autogradingTotalAwarded($gradeable['g_id'], $student_id, $active_version);
                $auto_grading_max_score = $this->getAutogradingMaxScore($gradeable['g_id']);
                $student_output_text .= "AUTO-GRADING TOTAL [ " . $auto_grading_awarded . " / " . $auto_grading_max_score . " ]" . $nl;
                $gradefilecontents = file_get_contents($submit_file);
                $student_output_text .= "submission version #" . $active_version .$nl;
                $student_output_text .= $nl.$gradefilecontents.$nl;
            }
            for ($i = 0; $i < count($grading_notes); $i++){
                $grading_note = $grading_notes[$i];
                $question_total = floatval($question_totals[$i]);
                $question_max_score = floatval($question_max_scores[$i]);
                $question_message = $question_messages[$i];
                $question_extra_credit = boolval($question_extra_credits[$i]);
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
                $student_output_text .= $nl;
                // Keep track of students grade and rubric total
                $student_grade += $grade_question_score;
                if (!$question_extra_credit && $question_max_score > 0){
                    $rubric_total += $question_max_score;
                    $ta_max_score += $question_max_score;
                }
            }

            $student_output_text .= "TA GRADING TOTAL [ " . $student_grade . " / " . $ta_max_score . " ]". $nl;
            $student_output_text .= "----------------------------------------------------------------------" . $nl;
            $rubric_total += $auto_grading_max_score;
            $student_grade += $auto_grading_awarded;
        }

        if($write_output){
            $student_final_grade = max(0,$student_grade);
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

    private function getGraders(){
        global $db;
        $db->query("
        SELECT 
            gd_grader_id
            , u.user_firstname
            , u.user_lastname
            , u.user_email
        FROM 
            gradeable_data gd
            , users u
        WHERE
            gd.gd_grader_id = u.user_id
        GROUP BY 
            gd_grader_id
            , u.user_firstname
            , u.user_lastname
            , u.user_email
    ;");
        return $db->rows();
    }

    public function generateAllReports(){
        $reportsData = $this->getReportDataAll();
        $graders = $this->getGraders();
        $ldu = new LateDaysCalculation();

        foreach ($reportsData as $report) {
            $this->generateReport($report, $ldu, $graders);
        }
    }

    public function generateSingleReport($student_id, $gradeable_id){
        $reportsData = $this->getReportDataSingle($student_id, $gradeable_id);
        $graders = $this->getGraders();
        $ldu = new LateDaysCalculation();

        foreach ($reportsData as $report) {
            $this->generateReport($report, $ldu, $graders);
        }
    }

    public function generateAllReportsForStudent($student_id){
        $reportsData = $this->getReportDataStudentAll($student_id);
        $graders = $this->getGraders();
        $ldu = new LateDaysCalculation();

        foreach ($reportsData as $report) {
            $this->generateReport($report, $ldu, $graders);
        }
    }

    public function generateAllReportsForGradeable($gradeable_id){
        $reportsData = $this->getReportDataGradeableAll($gradeable_id);
        $graders = $this->getGraders();
        $ldu = new LateDaysCalculation();

        foreach ($reportsData as $report) {
            $this->generateReport($report, $ldu, $graders);
        }
    }
}