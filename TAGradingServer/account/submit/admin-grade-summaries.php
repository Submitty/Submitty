<?php

require "../../toolbox/functions.php";

check_administrator();

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf']) {
    die("invalid csrf token");
}

if (!is_dir(implode("/",array(__SUBMISSION_SERVER__, "reports","all_grades")))) {
    mkdir(implode("/",array(__SUBMISSION_SERVER__, "reports","all_grades")));
}

/************************************/
/* Output Individual Student Files  */
/************************************/

$nl = "\n";

//calculate the maximum score for autograding
function autogradingTotalAwarded($g_id, $student_id, $active_version){
    $total = 0;
    $results_file = __SUBMISSION_SERVER__."/results/".$g_id."/".$student_id."/".$active_version."/results.json";
    if (file_exists($results_file)) {
        $results_file_contents = file_get_contents($results_file);
        $results = json_decode($results_file_contents, true);
	if (isset($results['testcases'])) {	       
            foreach($results['testcases'] as $testcase){
                $total += floatval($testcase['points_awarded']);
            }
        }
    }
    return $total;
}


// find the syllabus buckets
$db->query("SELECT DISTINCT g_syllabus_bucket FROM gradeable ORDER BY g_syllabus_bucket ASC", array());
$buckets = $db->rows();
$categories = array();

foreach ($buckets as $bucket){
    array_push($categories, ucwords($bucket['g_syllabus_bucket']));
}

$default_allowed_late_days = __DEFAULT_TOTAL_LATE_DAYS__;

$db->query("SELECT * FROM users ORDER BY user_id ASC", array());
//$db->query("SELECT * FROM users WHERE (user_group=4 AND registration_section IS NOT NULL) OR (manual_registration) ORDER BY user_id ASC", array());
foreach($db->rows() as $student_record) {
        
    // Gather student info, set output filename, reset output
    $student_id = $student_record["user_id"];
    $student_legal_first_name = $student_record["user_firstname"];
    $student_preferred_first_name = $student_record["user_preferred_firstname"];
    $student_last_name = $student_record["user_lastname"];

    // create/reset student json
    $student_output_json = array();
    $student_output_json_name = $student_id . "_summary.json";	
	
    $student_section = intval($student_record['registration_section']);

    $params = array($student_id);

	// CREATE HEADER FOR JSON
    $student_output_json["user_id"] = $student_id;
    $student_output_json["legal_first_name"] = $student_legal_first_name;
    $student_output_json["preferred_first_name"] = $student_preferred_first_name;
    $student_output_json["last_name"] = $student_last_name;
    $student_output_json["registration_section"] = intval($student_section);


    // adds late days for electronic gradeables
    $db->query("
        SELECT 
            allowed_late_days
        FROM 
            late_days 
        WHERE user_id=?
        ORDER BY since_timestamp DESC", array($student_id));
    $row = $db->row();
    
    //    $default_allowed_late_days = __DEFAULT_TOTAL_LATE_DAYS__;
    //if (count($row) > 0 &&
    //    isset($row['allowed_late_days']) &&
    //   $row['allowed_late_days'] > $late_days_allowed) {
    //  $late_days_allowed = $row['allowed_late_days'];
    //}
    //$late_days_allowed = isset($row['allowed_late_days']) ? $row['allowed_late_days'] : 0;


    $student_output_json["default_allowed_late_days"] = $default_allowed_late_days;
    //$student_output_json["allowed_late_days"] = $late_days_allowed;

    $student_output_json["last_update"] = date("l, F j, Y");
    
    // ADD each bucket to the output
    foreach($categories as $category){
        $student_output_json[$category] = array();
    }
    
    // SQL sorcery ༼╰( ͡° ͜ʖ ͡° )つ──☆*:・ﾟ
    $db->query("
    SELECT * FROM (
        SELECT 
            g_syllabus_bucket, 
            g_title, 
            g_gradeable_type, 
            g.g_id, 
            u.user_id, 
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
            ) AS total ON total.g_id = g.g_id AND total.gd_user_id=u.user_id
        ORDER BY g_syllabus_bucket ASC, g_grade_released_date ASC, u.user_id ASC
        ) AS user_grades
    WHERE user_id=?
        ",array($student_id));
	
    foreach($db->rows() as $gradeable){
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
        $this_g["name"] =  $gradeable['g_title'];
        $this_g["score"] = max(0,(floatval($gradeable['score'])+floatval($autograding_score)));

        // adds late days for electronic gradeables 
        if($gradeable['g_gradeable_type'] == 0){


// TOOK THIS CODE FROM admin-hw-report.php
            $db->query("
SELECT GREATEST(late_days_used - COALESCE(late_day_exceptions, 0), 0) as late_days_used
FROM late_days_used AS ldu
LEFT JOIN (
    SELECT late_day_exceptions, user_id, g_id
    FROM late_day_exceptions
) AS lde ON lde.user_id=ldu.user_id AND lde.g_id=ldu.g_id
WHERE ldu.g_id=? AND ldu.user_id=?", array($gradeable['g_id'],$student_id));
            $row = $db->row();
            if (isset($row['late_days_used']) && $row['late_days_used'] > 0) {
                $this_g['days_late'] = $row['late_days_used'];
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
    }
	
    // WRITE THE JSON FILE
    file_put_contents(implode("/", array(__SUBMISSION_SERVER__, "reports","all_grades", $student_output_json_name)), json_encode($student_output_json,JSON_PRETTY_PRINT));
    
    echo "grade summary json produced for " . $student_id . "<br>";
	
}

echo "Queries run: ".$db->totalQueries();

?>
