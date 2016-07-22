<?php

// TODO update SQL queries to support new schema

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

$lab_base = array();
$lab_titles = array();
$db->query("SELECT lab_id AS id, lab_title, lab_number as number, lab_code AS code FROM labs ORDER BY lab_id",array());
foreach($db->rows() as $row) {
    $lab_base[$row['id']] = 0;
    $lab_titles[$row['id']] = $row['lab_title'];
}

$queries = array(
    "LAB"  => "SELECT l.lab_title, gl.lab_id as id,sum(case when grade_lab_value=2 then .5 else grade_lab_value end) AS score
    FROM grades_labs AS gl
    LEFT JOIN (select lab_id,lab_number,lab_title FROM labs) as l ON gl.lab_id=l.lab_id WHERE student_rcs=?
    GROUP BY gl.lab_id,l.lab_number,l.lab_title ORDER BY l.lab_number",

    "HW"   => "SELECT r.rubric_due_date, r.rubric_submission_id, r.rubric_name, g.grade_days_late, g.rubric_id as id,sum(gq.grade_question_score) as score
    FROM grades_questions AS gq, grades AS g LEFT JOIN (select rubric_id, rubric_submission_id, rubric_name, rubric_due_date FROM rubrics) as r ON g.rubric_id=r.rubric_id
    WHERE gq.grade_id=g.grade_id AND g.student_rcs=?
    GROUP BY g.rubric_id,r.rubric_submission_id,g.grade_days_late,rubric_due_date,rubric_name
    ORDER BY r.rubric_due_date",

    "TEST" => "SELECT t.test_type, t.test_number, t.test_id as id, gt.test_text,
    case when gt.value::numeric=0 or gt.value is null then 0
    else case when gt.value::numeric + t.test_curve > t.test_max_grade
            then t.test_max_grade
        else gt.value::numeric + t.test_curve end end as score
    FROM
        tests AS t LEFT JOIN (
        SELECT
            test_id,grade_test_value as value, grade_test_text as test_text
        FROM
            grades_tests
        WHERE
            student_rcs=?
        ) AS gt ON t.test_id=gt.test_id
    ORDER BY
        t.test_id",

    "OTHER" => "SELECT g.*, o.*
    FROM
        grades_others as g
        LEFT JOIN (SELECT * FROM other_grades) as o on o.oid = g.oid
    WHERE
        student_rcs=?"
);

// Query the database for all students registered in the class
$params = array();
$db->query("SELECT * FROM students ORDER BY student_rcs ASC", $params);

foreach($db->rows() as $student_record) {
    // Gather student info, set output filename, reset output
    $student_id = intval($student_record["student_id"]);
    $student_rcs = $student_record["student_rcs"];
    $student_first_name = $student_record["student_first_name"];
    $student_last_name = $student_record["student_last_name"];

    // create/reset student json
    $student_output_json = array();
    $student_output_json_name = $student_rcs . "_summary.json";	
	
    $student_section = intval($student_record['student_section_id']);

    $params = array($student_id);

	// CREATE HEADER FOR JSON
    $student_output_json["rcs_id"] = $student_rcs;
    $student_output_json["first_name"] = $student_first_name;
    $student_output_json["last_name"] = $student_last_name;
    $student_output_json["section"] = intval($student_section);

    // copy date above
    $student_output_json["last_update"] = date("l, F j, Y");
    // $student_output_json["last_update"] = date("Y m d");
	
    $params = array($student_rcs);
    $db->query($queries['LAB'], $params);

	//check if query was empty; if so, do not create Lab in json
	if (empty($db)) {
		$student_output_json['Lab'] = array();
	}

    $lab_grades = $lab_base;
    foreach($db->rows() as $row) {
        $lab_grades[$row['id']] = $row['score'];
    }
	
    foreach($lab_grades as $id => $score) {
		if ($score <= 0) {
			continue;
		}
	    // there is probbaly a better way...
        $labnum = $id;
	    if (substr($lab_titles[$id], 0,4) == "Lab ") {
            $labnum = (int)substr($lab_titles[$id], 4);
        }
        $labid = "lab" . sprintf("%02d", $labnum);
        // eventually, the instructor could/should(?) have control both of the lab id & the lab title
		// add Lab => {ladid => {name, score}} to student json
		$student_output_json["Lab"][$labid] = array('name' => $lab_titles[$id],'score' => floatval($score));
    }

    $exceptions = array();
    $db->query("SELECT * FROM late_day_exceptions WHERE ex_student_rcs=?", $params);
    foreach($db->rows() as $row) {
        $exceptions[$row['ex_rubric_id']] = $row['ex_late_days'];
    }

    $db->query($queries['HW'], $params);

	// check if query was empty; if so, do not create HW in json
	if (empty($db)) {
		$student_output_json['rubric'] = array();
	}
	
    foreach($db->rows() as $row) {
        if (!isset($row['score']) || $row['score'] <= 0) {
            // $row['score'] = -7000000;
			continue;
        }
		
		$late_days = $row['grade_days_late'];
        if (isset($exceptions[$row['id']])) {
            $late_days -= $exceptions[$row['id']];
        }
        $late_days = ($late_days < 0) ? 0 : $late_days;
        if ($late_days > 0) {
			// add rubric => {hw_id => {rubric_name, score, days_late}} to student json
			// if there are late days
            $student_output_json["rubric"][$row['rubric_submission_id']] = array('name' => $row['rubric_name'],'score' => floatval($row['score']),'days_late' => intval($late_days));
        } else {
			// add rubric => {hw_id => {rubric_name, score}} to student json otherwise
			$student_output_json["rubric"][$row['rubric_submission_id']] = array('name' => $row['rubric_name'],'score' => floatval($row['score']));
		}
    }

    $db->query($queries['TEST'], $params);

	// check if query was empty; if so, do not create test in json
	if (empty($db)) {
		$student_output_json['test'] = array();
	}
	
    foreach($db->rows() as $row) {
        if ($row['score'] <= 0) {
            continue;
        }

	    $testname = $row['test_type']." " . $row['test_number'];
		if (implode(" ", pgArrayToPhp($row['test_text'])) === '') {
			// add test => {test# => {testName, score}} to student json
			// if there is not a text field
			$student_output_json['test'][strtolower($row['test_type']) . $row['test_number']] = array('name' => $testname,'score' => floatval($row['score']));
		} else {
			// add test => {test# => {testName, score, testText}} to student json otherwise 
			$student_output_json['test'][strtolower($row['test_type']) . $row['test_number']] = array('name' => $testname,'score' => floatval($row['score']),'text' => implode(" ", pgArrayToPhp($row['test_text'])));
		}
    }

    $db->query($queries['OTHER'], $params);
	
	if (empty($db)) {
		$student_output_json['Other'] = array();
	}

    foreach($db->rows() as $row) {
        if ($row['grades_other_score'] <= 0 &&
            $row['grades_other_text'] === '') {
            continue;
        }
		
		if ($row['grades_other_text'] === '') {
			// add Other => {other_id => {name, score}} to student json
			// if there is not a text field
			$student_output_json['Other'][$row['other_id']] = array('name' => $row['other_name'],'score' => floatval($row['grades_other_score']));
		} else {
			// add Other => {other_id => {name, score, text}} to student json otherwise
			$student_output_json['Other'][$row['other_id']] = array('name' => $row['other_name'],'score' => floatval($row['grades_other_score']),'text' => $row['grades_other_text']);
		}
    }

    // ======================================================    
	
    // WRITE THE JSON FILE
    file_put_contents(implode("/", array(__SUBMISSION_SERVER__, "reports","all_grades", $student_output_json_name)), json_encode($student_output_json,JSON_PRETTY_PRINT));
    
    echo "grade summary json produced for " . $student_rcs . "<br>";
	
	//  break;
}

echo "Queries run: ".$db->totalQueries();

?>
