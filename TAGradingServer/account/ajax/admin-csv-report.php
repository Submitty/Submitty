<?php

include  "../../toolbox/functions.php";

check_administrator();

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf']) {
    die("invalid csrf token");
}

$csv_output = "";
$nl = "\n";

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

// Create column headers and the IDs/Codes of all assignments
$header = array();

$header[] = "Username";
$queries = array(
    "LAB"  => "SELECT 'LAB' as key, lab_id AS id, lab_number as number, ('Lab ' || lab_number) as name FROM labs ORDER BY lab_number",
    "HW"   => "SELECT 'HW' as key, rubric_id AS id, rubric_name as name FROM rubrics ORDER BY rubric_due_date ASC",
    "TEST" => "SELECT upper(test_type) as key, test_id AS id, test_number as number, (test_type || ' ' || test_number) as name FROM tests ORDER BY test_type, test_number"
);

$totals = array("LAB"=>array(),"HW"=>array(),"TEST"=>array());

foreach ($queries as $key => $query) {
    $db->query($query, array());
    foreach ($db->rows() as $row) {
        $header[] = $row['name'];
        $totals[$key][] = $row['id'];
    }
}

$csv_output .= implode(",", $header) . $nl;

// Loop through every student, getting their sums for assignments to add to the CSV
$db->query("SELECT * FROM students ORDER BY student_rcs",array());
$students = $db->rows();
foreach($students as $student) {
    $student_row = array();
    $student_row[] = $student['student_rcs'];
    $params = array($student['student_rcs']);

    // generate the reports for the assignments for the student
    $queries = array(
        "LAB"  => "SELECT gl.lab_id as id,sum(case when grade_lab_value=2 then .5 else grade_lab_value end) AS score
        FROM grades_labs AS gl
        LEFT JOIN (select lab_id,lab_number FROM labs) as l ON gl.lab_id=l.lab_id WHERE student_rcs=?
        GROUP BY gl.lab_id,l.lab_number ORDER BY l.lab_number",
        "HW"   => "SELECT g.rubric_id as id,sum(gq.grade_question_score) as score
        FROM grades_questions AS gq, grades AS g LEFT JOIN (select rubric_id, rubric_due_date FROM rubrics) as r ON g.rubric_id=r.rubric_id
        WHERE gq.grade_id=g.grade_id AND g.student_rcs=?
        GROUP BY g.rubric_id, r.rubric_due_date
        ORDER BY r.rubric_due_date",
        "TEST" => "SELECT t.test_id as id,
        case when gt.value::numeric=0 or gt.value is null then 0
        else case when gt.value::numeric+t.test_curve > 100 then 100
            else gt.value::numeric+t.test_curve end end as score
        FROM
	        tests AS t LEFT JOIN (
	        SELECT
		        test_id,grade_test_value as value
	        FROM
		        grades_tests
	        WHERE
		        student_rcs=?
	        ) AS gt ON t.test_id=gt.test_id
        ORDER BY
        	t.test_id");
    foreach($queries as $key => $query) {
        $db->query($query,$params);
        $results = array();
        // For labs, a score might not exist for a student for a lab so have to check ids
        // against the ones we know exist (so give a score for existing and zero for missing)
        foreach ($db->rows() as $row) {
            $results[$row['id']] = $row['score'];
        }

        foreach($totals[$key] as $id) {
            if ($key == "HW") {
                if (isset($academic_integrity[$id]) && in_array($student['student_rcs'], $academic_integrity[$id]) && !(isset($academic_resolutions[$id]) && array_key_exists($student['student_rcs'], $academic_resolutions[$id]))) {
                    $student_row[] = "";
                    continue;
                }
            }
            if (array_key_exists($id,$results)) {

                if($key == "HW" && isset($academic_resolutions[$id]) && array_key_exists($student['student_rcs'], $academic_resolutions[$id])) {
                    $student_row[] = $results[$id]*$academic_resolutions[$id][$student['student_rcs']];
                }
                else {
                    $student_row[] = $results[$id];
                }
            }
            else if ($key != "HW") {
                // don't upload grades for ungraded homeworks (all other types are fine for it)
                $student_row[] = "0";
            }
            else if ($key == "HW") {
                $student_row[] = "";
            }
        }
    }
    $csv_output .= implode(",", $student_row).$nl;
}

header("Content-Type: text/plain");
header('Content-Disposition: attachment; filename=hwserver-report.csv');
header("Content-Length: " . strlen($csv_output));

echo $csv_output;