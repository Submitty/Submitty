<?php

require "../../toolbox/functions.php";
require "../../models/GradeSummary.php";

check_administrator();

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf']) {
    die("invalid csrf token");
}

foreach ($db->rows() as $student_record) {

    foreach ($db->rows() as $gradeable) {
        $this_g = array();

        //
        // FIXME:  Should use value in the database, for electronic gradeables with TA grading
        //  ...  but that value is broken
        //  ...  also, that value does not exist for non ta graded electronic gradeables
        //  currently, a student can change the active version after the deadline and get full credit for a late submission
        //
        $active_version = getActiveVersionFromFile($gradeable['g_id'], $student_id);
        //$autograding_score = autogradingTotalAwarded($gradeable['g_id'], $student_id, $gradeable['gd_active_version']);
        $autograding_score = autogradingTotalAwarded($gradeable['g_id'], $student_id, $active_version);

        $this_g["id"] = $gradeable['g_id'];
        $this_g["name"] = $gradeable['g_title'];

        // TODO: DEPRECATE THIS FIELD
        $this_g["score"] = max(0, (floatval($gradeable['score']) + floatval($autograding_score)));

        // REPLACED BY:
        $this_g["original_score"] = max(0, (floatval($gradeable['score']) + floatval($autograding_score)));
        $this_g["actual_score"] = $this_g["original_score"];

        // adds late days for electronic gradeables
        if ($gradeable['g_gradeable_type'] == 0) {

            $this_g["status"] = "NO SUBMISSION";

            $late_days = $ldu->get_gradeable($student_id, $gradeable['g_id']);

            if (array_key_exists('late_days_charged', $late_days)) {
                $this_g["status"] = $late_days['status'];
            }

            if (strpos($this_g["status"], 'Bad') !== false) {
                $this_g["actual_score"] = 0;
            }

            // TODO: DEPRECATE THIS FIELD
            $this_g["original_score"] = max(0, (floatval($gradeable['score']) + floatval($autograding_score)));

            if (array_key_exists('late_days_charged', $late_days) && $late_days['late_days_used'] > 0) {

                // TODO:  DEPRECATE THIS FIELD
                $this_g['days_late'] = $late_days['late_days_charged'];

                // REPLACED BY:
                $this_g['days_after_deadline'] = $late_days['late_days_used'];
                $this_g['extensions'] = $late_days['extensions'];
                $this_g['days_charged'] = $late_days['late_days_charged'];

            } else {
                $this_g['days_late'] = 0;
            }
        }

        // Add text for numeric/text gradeables and electronic gradeables
        if ($gradeable['g_gradeable_type'] == 2 || $gradeable['g_gradeable_type'] == 0) {
            $text_items = array();
            $titles = pgArrayToPhp($gradeable['titles']);
            $comments = pgArrayToPhp($gradeable['comments']);

            for ($i = 0; $i < count($comments); ++$i) {
                if (trim($comments[$i]) !== '') {
                    array_push($text_items, array($titles[$i] => $comments[$i]));
                }
            }

            if (count($text_items) > 0) {
                $this_g["text"] = $text_items;
            }
        }

        // Add problem scores for checkpoints and numeric/text gradeables
        if ($gradeable['g_gradeable_type'] == 2 || $gradeable['g_gradeable_type'] == 1) {
            $component_scores = array();
            $titles = pgArrayToPhp($gradeable['titles']);
            $problem_scores = pgArrayToPhp($gradeable['scores']);
            $comments = pgArrayToPhp($gradeable['comments']);
            $is_texts = pgArrayToPhp($gradeable['is_texts']);

            for ($i = 0; $i < count($problem_scores); ++$i) {
                if (trim($comments[$i]) === '' && $is_texts[$i] === 'f') {
                    array_push($component_scores, array($titles[$i] => floatval($problem_scores[$i])));
                }
            }

            $this_g["component_scores"] = $component_scores;
        }


        array_push($student_output_json[ucwords($gradeable['g_syllabus_bucket'])], $this_g);
    }

    // WRITE THE JSON FILE
    file_put_contents(implode("/", array(__SUBMISSION_SERVER__, "reports", "all_grades", $student_output_json_name)), json_encode($student_output_json, JSON_PRETTY_PRINT));

    echo "grade summary json produced for " . $student_id . "<br>";

}

echo "Queries run: " . $db->totalQueries();

?>
