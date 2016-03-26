<?php

include "../../toolbox/functions.php";

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf']) {
    die("invalid csrf token");
}

$rubric_id = intval($_GET['hw']);
$db->query("SELECT * FROM rubrics WHERE rubric_id=?", array($rubric_id));
$rubric = $db->row();
if (!isset($rubric['rubric_id'])) {
    die("Invalid rubric specified.");
}
$now = new DateTime('now');
$homeworkDate = new DateTime($rubric['rubric_due_date']);
if ($rubric['rubric_late_days'] > 0) {
    $homeworkDate->add(new DateInterval("PT{$rubric['rubric_late_days']}H"));
}
if ($now < $homeworkDate) {
    die("Homework is not open for grading yet.");
}
$student_rcs = $_GET["student"];
$db->query("SELECT student_id FROM students WHERE student_rcs=?", array($student_rcs));
$row = $db->row();
$student_id = $row['student_id'];

$params = array($rubric_id, $student_rcs);
$db->query("SELECT grade_id FROM grades WHERE rubric_id=? AND student_rcs=?", $params);
$row = $db->row();

$status = intval($_POST['status']);
$submitted = intval($_POST['submitted']);
$_POST["late"] = intval($_POST['late']);

if(isset($row["grade_id"])) {
    $grade_id = intval($row["grade_id"]);
    if (isset($_POST['overwrite']) && intval($_POST['overwrite']) == 1) {
        $params = array(clean_string($_POST["comment-general"]), \app\models\User::$user_id, $_POST["late"], $submitted, $status, $_POST['active_assignment'], $_POST['grade_parts_days_late'], $_POST['grade_parts_submitted'], $_POST['grade_parts_status'], $grade_id);
        $db->query("UPDATE grades SET grade_comment=?, grade_finish_timestamp=NOW(), grade_user_id=?, grade_days_late=?, grade_is_regraded=1, grade_submitted=?, grade_status=?, grade_active_assignment=?, grade_parts_days_late=?, grade_parts_submitted=?, grade_parts_status=? WHERE grade_id=?", $params);
    }
    else {
        $params = array(clean_string($_POST["comment-general"]), $_POST["late"], $submitted, $status, $_POST['active_assignment'], $_POST['grade_parts_days_late'], $_POST['grade_parts_submitted'], $_POST['grade_parts_status'], $grade_id);
        $db->query("UPDATE grades SET grade_comment=?, grade_finish_timestamp=NOW(), grade_days_late=?, grade_is_regraded=1, grade_submitted=?, grade_status=?, grade_active_assignment=?, grade_parts_days_late=?, grade_parts_submitted=?, grade_parts_status=? WHERE grade_id=?", $params);
    }
}
else {
    $params = array($rubric_id, $student_id, clean_string($_POST["comment-general"]), \app\models\User::$user_id, $_POST["late"], $student_rcs, $submitted, $status, $_POST['active_assignment'], $_POST['grade_parts_days_late'], $_POST['grade_parts_submitted'], $_POST['grade_parts_status']);
    $db->query("INSERT INTO grades (rubric_id, student_id, grade_comment, grade_finish_timestamp, grade_user_id, grade_days_late, student_rcs, grade_submitted, grade_status, grade_active_assignment, grade_parts_days_late, grade_parts_submitted, grade_parts_status) VALUES (?,?,?,NOW(),?,?,?,?,?,?,?,?,?)", $params);

    $params = array($rubric_id, $student_rcs);
    $db->query("SELECT grade_id FROM grades WHERE rubric_id=? AND student_rcs=?", $params);
    $row = $db->row();
    $grade_id = intval($row["grade_id"]);
}

$params = array($rubric_id);
$db->query("SELECT * FROM questions WHERE rubric_id=? ORDER BY question_part_number, question_number", $params);
foreach($db->rows() as $row) {
    $params = array($grade_id, $row["question_id"]);
    $db->query("DELETE FROM grades_questions WHERE grade_id=? AND question_id=?", $params);

    $params = array($grade_id, $row["question_id"], $_POST["grade-" . $row["question_part_number"] . "-" . $row["question_number"]],  clean_string($_POST["comment-" . $row["question_part_number"] . "-" . $row["question_number"]]));
    $db->query("INSERT INTO grades_questions (grade_id, question_id, grade_question_score, grade_question_comment) VALUES (?,?,?,?)", $params);
}

if($_GET["individual"] == "1") {
    header('Location: '.$BASE_URL.'/account/account-summary.php?course='.$_GET['course'].'&hw=' . $_GET["hw"]);
}
else {
    header('Location: '.$BASE_URL.'/account/index.php?course='.$_GET['course'].'&hw=' . $_GET["hw"]);
}