<?php

include  "../../toolbox/functions.php";

check_administrator();

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf']) {
    die("invalid csrf token");
}

$csv_output = "";
$nl = "\n";

// Create column headers and the IDs/Codes of all assignments
$header = array();

$header[] = "Username";
$header[] = "First Name";
$header[] = "Last Name";
$header[] = "Registration Section";

$totals = array();

// find the syllabus buckets
$db->query("SELECT DISTINCT g_syllabus_bucket FROM gradeable ORDER BY g_syllabus_bucket ASC", array());
$buckets = $db->rows();
// populate the header
foreach ($buckets as $bucket){
    $db->query("SELECT g_title, g_id, g_syllabus_bucket FROM gradeable WHERE g_syllabus_bucket=? ORDER BY g_grade_released_date ASC", array($bucket['g_syllabus_bucket']));
    foreach ($db->rows() as $row) {
        $header[] = $row['g_title'];
        $totals[$row['g_syllabus_bucket']][] = $row['g_id'];
    }
}

$csv_output .= implode(",", $header) . $nl;

// GET ALL student users
$db->query("SELECT * FROM users WHERE (user_group=4 AND registration_section IS NOT NULL) OR (manual_registration) ORDER BY user_id ASC", array());
$users = $db->rows();

foreach ($users as $user){
    // returns all syllabus buckets with gradeable id, student_ids, and total scores on assignments
    $db->query("
    SELECT * FROM (
        SELECT g.g_id, u.user_id, case when score is null then 0 else score end
        FROM
            users AS u CROSS JOIN gradeable AS g 
            LEFT JOIN (
                SELECT g_id, gd_user_id, score 
                FROM 
                    gradeable_data AS gd INNER JOIN(
                    SELECT 
                        gd_id, SUM(gcd_score) AS score
                    FROM 
                        gradeable_component_data
                    GROUP BY gd_id
                ) AS gd_sum ON gd.gd_id=gd_sum.gd_id
            ) AS total ON total.g_id = g.g_id AND total.gd_user_id=u.user_id
        ORDER BY g_syllabus_bucket ASC, g_grade_released_date ASC, u.user_id ASC
        ) AS user_grades
    WHERE user_id=?
        ",array($user['user_id']));

    $student_grades = $db->rows();
    $student_row = array();
    $student_row[] = $user['user_id'];
    $student_row[] = (isset($user['user_preferred_firstname']) && $user['user_preferred_firstname'] != "") ? $user['user_preferred_firstname'] : $user['user_firstname'];
    $student_row[] = $user['user_lastname'];
    $student_row[] = $user['registration_section'];
    
    foreach($student_grades as $student_grade) {
        $student_row[] = max(0,floatval($student_grade['score']));
    }
    $csv_output .= implode(",", $student_row).$nl;
}

header("Content-Type: text/plain");
header('Content-Disposition: attachment; filename=hwserver-report.csv');
header("Content-Length: " . strlen($csv_output));

echo $csv_output;