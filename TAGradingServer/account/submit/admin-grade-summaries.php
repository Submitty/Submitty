<?php

require "../../toolbox/functions.php";

check_administrator();

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
    else case when gt.value::numeric+t.test_curve > (t.test_max_grade + t.test_curve)
            then t.test_max_grade + t.test_curve
        else gt.value::numeric+t.test_curve end end as score
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
        student_rcs='bergle'"
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
    $student_output_filename = $student_rcs . "_summary.txt";
    $student_output_text = "";

    $student_section = intval($student_record['student_section_id']);

    $params = array($student_id);


    // WRITE STUDENTS NAME AT TOP OF REPORT FILE
    $student_output_text .= "rcs_id " . $student_rcs . $nl;
    $student_output_text .= "first_name " . $student_first_name . $nl;
    $student_output_text .= "last_name " . $student_last_name . $nl;
    $student_output_text .= "section " . $student_section . $nl;

    // late update date (choice of format)
    //                                       Sunday, October 4, 2015
    $student_output_text .= "last_update " . date("l, F j, Y") .$nl;
    //                                          2015 09 29
    // $student_output_text .= "last_update " . date("Y m d") . $nl;


    $params = array($student_rcs);
    $db->query($queries['LAB'], $params);

    $lab_grades = $lab_base;
    foreach($db->rows() as $row) {
        $lab_grades[$row['id']] = $row['score'];
    }

    foreach($lab_grades as $id => $score) {
	    // there is probbaly a better way...
        $labnum = $id;
	    if (substr($lab_titles[$id], 0,4) == "Lab ") {
            $labnum = (int)substr($lab_titles[$id], 4);
        }
        $labid = "lab" . sprintf("%02d", $labnum);
        // eventually, the instructor could/should(?) have control both of the lab id & the lab title
        $student_output_text .= 'lab ' . $labid . ' "' . $lab_titles[$id] . '" ' . floatval($score) . $nl;
    }

    $exceptions = array();
    $db->query("SELECT * FROM late_day_exceptions WHERE ex_student_rcs=?", $params);
    foreach($db->rows() as $row) {
        $exceptions[$row['ex_rubric_id']] = $row['ex_late_days'];
    }

    $db->query($queries['HW'], $params);
    foreach($db->rows() as $row) {
        if (!isset($row['score'])) {
            $row['score'] = -7000000;
        }
        $student_output_text .= "hw " . $row['rubric_submission_id'] . " \"" . $row['rubric_name'] . "\" " . $row['score'] . $nl;
        $late_days = $row['grade_days_late'];
        if (isset($exceptions[$row['id']])) {
            $late_days -= $exceptions[$row['id']];
        }
        $late_days = ($late_days < 0) ? 0 : $late_days;
        if ($late_days > 0) {
            $student_output_text .= "days_late " . $row['rubric_submission_id'] . " " . $late_days . $nl;
        }
    }

    $db->query($queries['TEST'], $params);
    foreach($db->rows() as $row) {
        if ($row['score'] <= 0) {
            continue;
        }

	    $testname = $row['test_type']." " . $row['test_number'];
        $student_output_text .= strtolower($row['test_type']) . ' ' .$row['test_number'] . ' "' . $testname . '" ' . $row['score'] . " " . implode(" ", pgArrayToPhp($row['test_text'])) . $nl;
    }

    $db->query($queries['OTHER'], $params);
    foreach($db->rows() as $row) {
        if ($row['grades_other_score'] <= 0) {
            continue;
        }
        $student_output_text .= $row['other_id'].' '.$row['grades_other_score'].' "'.$row['other_name'].'" '.$row['grades_other_text'];
    }
    
    // ======================================================

    // WRITE REPORT FILE
    file_put_contents(implode("/", array(__SUBMISSION_SERVER__, "reports","all_grades", $student_output_filename)), $student_output_text);

    echo "grade summary report produced for " . $student_rcs . "<br>";
    //  break;
}

echo "Queries run: ".$db->totalQueries();

?>
