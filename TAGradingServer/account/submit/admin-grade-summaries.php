<?php

require "../../toolbox/functions.php";

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
    
    "HW"   => "SELECT r.rubric_number, g.grade_days_late, g.rubric_id as id,sum(gq.grade_question_score) as score
    FROM grades_questions AS gq, grades AS g LEFT JOIN (select rubric_number,rubric_id FROM rubrics) as r ON g.rubric_id=r.rubric_id
    WHERE gq.grade_id=g.grade_id AND g.student_rcs=?
    GROUP BY g.rubric_id,r.rubric_number,g.grade_days_late
    ORDER BY r.rubric_number",
    
    "TEST" => "SELECT t.test_number, t.test_id as id, gt.test_text,
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
        t.test_id");

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

    
    $params = array($student_rcs);
    $db->query($queries['LAB'], $params);
    
    $lab_grades = $lab_base;
    foreach($db->rows() as $row) {
        $lab_grades[$row['id']] = $row['score'];
        //$student_output_text .= $row['lab_title'] . " " . $row['score'] . $nl;
    }
    
    foreach($lab_grades as $id => $score) {
        $student_output_text .= $lab_titles[$id] . " " . floatval($score) . $nl;
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
        $student_output_text .= "hw " . $row['rubric_number'] . " " . $row['score'] . $nl;
        $late_days = $row['grade_days_late'];
        if (isset($exceptions[$row['id']])) {
            $late_days -= $exceptions[$row['id']];
        }
        $late_days = ($late_days < 0) ? 0 : $late_days;
        if ($late_days > 0) {
            $student_output_text .= "days_late hw_" . $row['rubric_number'] . " " . $late_days . $nl;
        }
    }
    
    $db->query($queries['TEST'], $params);
    foreach($db->rows() as $row) {
        if ($row['score'] <= 0) {
            continue;
        }
        $student_output_text .= "test " . $row['test_number'] . " " . $row['score'] . " " . implode(" ", pgArrayToPhp($row['test_text'])) . $nl;
    }

    // ======================================================

    // WRITE REPORT FILE
    file_put_contents(implode("/", array(__SUBMISSION_SERVER__, "reports","all_grades", $student_output_filename)), $student_output_text);

    echo "grade summary report produced for " . $student_rcs . "<br>";
    //  break;
}

echo "Queries run: ".$db->totalQueries();

?>
