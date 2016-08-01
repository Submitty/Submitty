<?php

include "../../toolbox/functions.php";

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf']) {
    die("invalid csrf token");
}

$g_id = $_GET['g_id'];

$db->query("SELECT * FROM gradeable_data AS gd INNER JOIN gradeable AS g ON gd.g_id=g.g_id 
            INNER JOIN electronic_gradeable AS eg ON g.g_id=eg.g_id WHERE g.g_id = ?", array($g_id));
$gradeable = $db->row();
if (!isset($gradeable['g_id'])){
    die("Invalid gradeable specified");
}

$now = new DateTime('now');
$homeworkDate = new DateTime($gradeable['eg_submission_due_date']);

if ($gradeable['eg_late_days'] > 0) {
    $homeworkDate->add(new DateInterval("PT{$gradeable['eg_late_days']}H"));
}

if ($now < $homeworkDate) {
    die("Homework is not open for grading yet.");
}

$student = $_GET['student'];

// get the gradeable data from the student
$params = array($g_id);
$db->query("SELECT * FROM gradeable AS g INNER JOIN gradeable_component AS gc ON g.g_id=gc.g_id 
            WHERE g.g_id=? ORDER BY gc_order ASC", $params);
$rows = $db->rows();

$params = array($g_id, $student);
$db->query("SELECT gd_id FROM gradeable_data AS gd INNER JOIN gradeable AS g ON gd.g_id=g.g_id 
            INNER JOIN users AS u ON u.user_id=gd.gd_user_id WHERE g.g_id=? AND u.user_id=?",$params);
$gd_id = $db->row()['gd_id'];

//update each gradeable component data
foreach($rows AS $row){
    $grade = floatval($_POST["grade-" . $row["gc_order"]]);
    $comment = isset($_POST["comment-" . $row["gc_order"]]) ? $_POST["comment-" . $row["gc_order"]] : '';
    $gc_id = $row['gc_id'];
    
    $params = array($gd_id, $gc_id);
    $db->query("DELETE FROM gradeable_component_data WHERE gd_id=? AND gc_id=?", $params);
    
    $params = array($gc_id, $gd_id, $grade, $comment);
    $db->query("INSERT INTO gradeable_component_data (gc_id, gd_id, gcd_score, gcd_component_comment) VALUES(?,?,?,?)",$params);
}

//update the gradeable data
$overall_comment = $_POST['comment-general'];
$params = array($overall_comment, $gd_id);
$db->query("UPDATE gradeable_data SET gd_overall_comment=? WHERE gd_id=?",$params);


$status = intval($_POST['status']);
$submitted = intval($_POST['submitted']);
$is_graded = boolval($_POST['is_graded']);
$_POST["late"] = intval($_POST['late']);

//update the number of late days for the student the first time grades are submitted
if ($status == 1 && !$is_graded){
    $db->query("SELECT COALESCE(allowed_late_days,0) AS last_lates FROM late_days WHERE user_id=? ORDER BY since_timestamp DESC LIMIT 1", array($student));
    $new_lates = $db->row()['last_lates'] - intval($_POST['late']);
    $db->query("INSERT INTO late_days (user_id, since_timestamp, allowed_late_days) VALUES (?,?,?)", array($student, 'now', $new_lates));
    $db->query("INSERT INTO late_days_used (user_id, g_id, late_days_used) VALUES (?,?,?)", array($student, $g_id, intval($_POST['late'])));
}

if($_GET["individual"] == "1") {
    header('Location: '.$BASE_URL.'/account/account-summary.php?course='.$_GET['course'].'&g_id=' . $_GET["g_id"]);
}
else {
    header('Location: '.$BASE_URL.'/account/index.php?course='.$_GET['course'].'&g_id=' . $_GET["g_id"]);
}