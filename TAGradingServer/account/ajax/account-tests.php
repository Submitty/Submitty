<?php
include "../../toolbox/functions.php";

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] != $_SESSION['csrf']) {
    die("invalid csrf token");
}

$test = intval($_GET["test"]);

$params = array($test);
$db->query("SELECT * FROM tests WHERE test_id=?", $params);
$test_details = $db->row();

$rcs = $_GET["rcs"];
$grade = floatval($_GET["grade"]);

$questions = array();
for ($i = 0; $i < $test_details['test_questions']; $i++) {
    $questions[] = floatval($_GET['q'.$i]);
}

$text = array();
for ($i = 0; $i < $test_details['test_text_fields']; $i++) {
    $text[] = htmlentities($_GET['t'.$i]);
}

$question_grades = phpToPgArray($questions);
$text_fields = phpToPgArray($text);

$params = array($test, $rcs);
$db->query("SELECT * FROM grades_tests WHERE test_id=? AND student_rcs=?", $params);
$temp = $db->row();

$old_grade = (isset($temp["grade_test_value"]) ? $temp["grade_test_value"] : "");

$db->query("SELECT student_id FROM students WHERE student_rcs=?",array($rcs));
$row = $db->row();
$id = $row['student_id'];

/* TODO: Fix this comparison to actually do something? */
if(((string) $grade) != ((string) $old_grade) || 1 == 1) {
    if(isset($temp["grade_test_value"])) {
        // UPDATE
        $params = array($user_id, $grade, $question_grades, $text_fields, $temp["grade_test_id"]);
        $db->query("UPDATE grades_tests SET grade_test_user_id=?, grade_test_value=?, grade_test_questions=?, grade_test_text=? WHERE grade_test_id=?", $params);
    }
    else {
        // INSERT
        $params = array($test, $id, $grade, $question_grades, $text_fields, $user_id, $rcs);
        $db->query("INSERT INTO grades_tests (test_id, student_id, grade_test_value, grade_test_questions, grade_test_text, grade_test_user_id, student_rcs) VALUES (?,?,?,?,?,?,?)", $params);
    }
}
else {
    echo $grade;
    echo " ";
    echo $old_grade;
    echo " ";
}

echo "updated";