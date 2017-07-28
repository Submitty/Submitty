<?php

use \lib\Database;

require "LateDaysCalculation.php";

class GradeSummary
{
    const summary_query = "SELECT * FROM (
        SELECT 
            g_syllabus_bucket, 
            g_title, 
            g_grade_released_date,
            g_gradeable_type, 
            g.g_id, 
            u.user_id,
            u.user_firstname,
            u.user_preferred_firstname,
            u.user_lastname, 
            u.registration_section,
            case when score is null then 0 else score end, 
            titles, 
            comments,
            scores,
            is_texts,
            gd_active_version
        FROM
            users AS u CROSS JOIN gradeable AS g 
            LEFT JOIN (
                SELECT 
                    g_id, 
                    gd_user_id, 
                    score, 
                    titles, 
                    comments,
                    scores,
                    is_texts,
                    gd_active_version
                FROM 
                    gradeable_data AS gd INNER JOIN(
                    SELECT 
                        gd_id, 
                        SUM(gcd_score) AS score, 
                        array_agg(gc_title ORDER BY gc_order ASC) AS titles, 
                        array_agg(gcd_component_comment ORDER BY gc_order ASC) AS comments,
                        array_agg(gcd_score ORDER BY gc_order ASC) AS scores,
                        array_agg(gc_is_text ORDER BY gc_order ASC) AS is_texts
                    FROM 
                        gradeable_component_data AS gcd INNER JOIN 
                            gradeable_component AS gc ON gcd.gc_id=gc.gc_id
                    GROUP BY gd_id
                ) AS gd_sum ON gd.gd_id=gd_sum.gd_id
            ) AS total ON total.g_id = g.g_id AND total.gd_user_id=u.user_id";

    const report_order_by = " ORDER BY u.user_id ASC, g_syllabus_bucket ASC, g_grade_released_date ASC) as result";

    private function autogradingTotalAwarded($g_id, $student_id, $active_version)
    {
        $total = 0;
        $results_file = __SUBMISSION_SERVER__ . "/results/" . $g_id . "/" . $student_id . "/" . $active_version . "/results.json";
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

    private function getSummaryDataAll(){
        global $db;
        $db->query(self::summary_query.self::report_order_by);
        return $db->rows();
    }

    private function getSummaryDataStudent($student_id){
        $where_clause = " WHERE user_id=?";
        $params = array($student_id);

        global $db;
        $db->query(self::report_query.$where_clause.self::report_order_by, $params);
        return $db->rows();
    }

    private function generateSummary($gradeable, $ldu, $student_output_json)
    {
        $student_id = $gradeable["user_id"];

        $this_g = array();

        // FIXME:  Should use value in the database, for electronic gradeables with TA grading
        //  ...  but that value is broken
        //  ...  also, that value does not exist for non ta graded electronic gradeables
        //  currently, a student can change the active version after the deadline and get full credit for a late submission
        //
        $active_version = getActiveVersionFromFile($gradeable['g_id'], $student_id);
        //$autograding_score = autogradingTotalAwarded($gradeable['g_id'], $student_id, $gradeable['gd_active_version']);
        $autograding_score = $this->autogradingTotalAwarded($gradeable['g_id'], $student_id, $active_version);

        $this_g["id"] = $gradeable['g_id'];
        $this_g["name"] =  $gradeable['g_title'];
        $this_g["grade_released_date"] = $gradeable['g_grade_released_date'];

        // TODO: DEPRECATE THIS FIELD
        $this_g["score"] = max(0,(floatval($gradeable['score'])+floatval($autograding_score)));

        // REPLACED BY:
        $this_g["original_score"] = max(0,(floatval($gradeable['score'])+floatval($autograding_score)));
        $this_g["actual_score"] = $this_g["original_score"];

        // adds late days for electronic gradeables
        if($gradeable['g_gradeable_type'] == 0){

            $this_g["status"] = "NO SUBMISSION";

            $late_days = $ldu ->get_gradeable($student_id, $gradeable['g_id']);

            if (array_key_exists('late_days_charged', $late_days)) {
                $this_g["status"] = $late_days['status'];
            }

            if (strpos($this_g["status"], 'Bad') !== false) {
                $this_g["actual_score"] = 0;
            }

            // TODO: DEPRECATE THIS FIELD
            $this_g["original_score"] = max(0,(floatval($gradeable['score'])+floatval($autograding_score)));

            if (array_key_exists('late_days_charged', $late_days) && $late_days['late_days_used'] > 0) {

                // TODO:  DEPRECATE THIS FIELD
                $this_g['days_late'] = $late_days['late_days_charged'];

                // REPLACED BY:
                $this_g['days_after_deadline'] = $late_days['late_days_used'];
                $this_g['extensions'] = $late_days['extensions'];
                $this_g['days_charged'] = $late_days['late_days_charged'];

            }
            else {
                $this_g['days_late'] = 0;
            }
        }

        // Add text for numeric/text gradeables and electronic gradeables
        if($gradeable['g_gradeable_type'] == 2 || $gradeable['g_gradeable_type'] == 0){
            $text_items = array();
            $titles = pgArrayToPhp($gradeable['titles']);
            $comments = pgArrayToPhp($gradeable['comments']);

            for($i=0; $i < count($comments); ++$i){
                if (trim($comments[$i]) !== ''){
                    array_push($text_items,array($titles[$i] => $comments[$i]));
                }
            }

            if(count($text_items) > 0){
                $this_g["text"] = $text_items;
            }
        }

        // Add problem scores for checkpoints and numeric/text gradeables
        if($gradeable['g_gradeable_type'] == 2 || $gradeable['g_gradeable_type'] == 1){
            $component_scores = array();
            $titles = pgArrayToPhp($gradeable['titles']);
            $problem_scores = pgArrayToPhp($gradeable['scores']);
            $comments = pgArrayToPhp($gradeable['comments']);
            $is_texts = pgArrayToPhp($gradeable['is_texts']);

            for($i=0; $i < count($problem_scores); ++$i){
                if (trim($comments[$i]) === '' && $is_texts[$i] === 'f'){
                    array_push($component_scores,array($titles[$i] => floatval($problem_scores[$i])));
                }
            }

            $this_g["component_scores"] = $component_scores;
        }

        array_push($student_output_json[ucwords($gradeable['g_syllabus_bucket'])], $this_g);

        return $student_output_json;
    }

    private function generateSummariesFromQueryResults($summaryData, $categories, $ldu){
        // create/reset student json
        $student_output_json = array();

        //Build the summaries
        foreach ($summaryData as $gradeable){
            $student_id = $gradeable['user_id'];
            if(!array_key_exists($student_id, $student_output_json)){
                $student_output_json[$student_id] = array();

                $default_allowed_late_days = __DEFAULT_TOTAL_LATE_DAYS__;

                // Gather student info, set output filename, reset output
                $student_id = $gradeable["user_id"];
                $student_legal_first_name = $gradeable["user_firstname"];
                $student_preferred_first_name = $gradeable["user_preferred_firstname"];
                $student_last_name = $gradeable["user_lastname"];

                $student_section = intval($gradeable['registration_section']);
                // CREATE HEADER FOR JSON
                $student_output_json[$student_id]["user_id"] = $student_id;
                $student_output_json[$student_id]["legal_first_name"] = $student_legal_first_name;
                $student_output_json[$student_id]["preferred_first_name"] = $student_preferred_first_name;
                $student_output_json[$student_id]["last_name"] = $student_last_name;
                $student_output_json[$student_id]["registration_section"] = intval($student_section);

                $student_output_json[$student_id]["default_allowed_late_days"] = $default_allowed_late_days;
                //$student_output_json["allowed_late_days"] = $late_days_allowed;

                $student_output_json[$student_id]["last_update"] = date("l, F j, Y");

                // ADD each bucket to the output
                foreach ($categories as $category) {
                    $student_output_json[$student_id][ucwords($category)] = array();
                }
            }

            $student = $student_output_json[$student_id];

            $student_output_json[$student_id] = $this->generateSummary($gradeable, $ldu, $student);
        }

        // WRITE THE JSON FILE
        foreach($student_output_json as $student)
        {
            $student_id = $student['user_id'];
            $student_output_json_name = $student_id . "_summary.json";

            file_put_contents(implode("/", array(__SUBMISSION_SERVER__, "reports","all_grades", $student_output_json_name)), json_encode($student,JSON_PRETTY_PRINT));

            echo "grade summary json produced for " . $student_id . "<br>";

        }
        global $db;
        echo "Queries run: " . $db->totalQueries();
    }

    private function getSyllabusBuckets()
    {
        global $db;
        $db->query("
            SELECT 
              g_syllabus_bucket
            FROM 
              gradeable
            GROUP BY 
              g_syllabus_bucket
            ORDER BY 
              g_syllabus_bucket ASC", array());
        $buckets = $db->rows();
        $categories = array();

        foreach ($buckets as $bucket) {
            array_push($categories, ucwords($bucket['g_syllabus_bucket']));
        }

        return $categories;
    }

    public function generateAllSummaries(){
        $summaryData = $this->getSummaryDataAll();
        $categories = $this->getSyllabusBuckets();
        $ldu = new LateDaysCalculation();

        $this->generateSummariesFromQueryResults($summaryData, $categories, $ldu);
    }

    public function generateAllSummariesForStudent($student_id){
        $summaryData = $this->getSummaryDataStudent($student_id);
        $categories = $this->getSyllabusBuckets();
        $ldu = new LateDaysCalculation();

        $this->generateSummariesFromQueryResults($summaryData, $categories, $ldu);
    }
}
