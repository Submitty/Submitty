<?php

include "../../toolbox/functions.php";
require "../../models/HWReport.php";

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
$homeworkDate = new DateTime($gradeable['g_grade_start_date']);
$status = intval($_POST['status']);
$submitted = intval($_POST['submitted']);
$is_graded = boolval($_POST['is_graded']);
$_POST["late"] = intval($_POST['late']);

if ($now < $homeworkDate) {
    die("Gradeable is not open for grading yet.");
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
    
    $params = array($gc_id, $gd_id, $grade, $comment, \models\User::$user_id, date('Y-m-d H:i:s'));
    $db->query("INSERT INTO gradeable_component_data (gc_id, gd_id, gcd_score, gcd_component_comment, gcd_grader_id, gcd_grade_time) VALUES(?,?,?,?,?,?)",$params);
}

//update the gradeable data
$overall_comment = $_POST['comment-general'];
$assignment_settings = __SUBMISSION_SERVER__."/submissions/".$gradeable['g_id']."/".$student."/user_assignment_settings.json";
if (!file_exists($assignment_settings)) {
    $active_version = -1;
}
else{
    $assignment_settings_contents = file_get_contents($assignment_settings);
    $results = json_decode($assignment_settings_contents, true);
    $active_version = $results['active_version'];
}

// Only changes the grader id if the overwrite grader box was checked
if (isset($_POST['overwrite'])) {
	$params = array(\models\User::$user_id, $active_version, $overall_comment, $status, intval($_POST['late']), $gd_id);
	$db->query("UPDATE gradeable_data SET gd_active_version=?, gd_overall_comment=?, gd_status=?, gd_late_days_used=?, gd_user_viewed_date=NULL WHERE gd_id=?",$params);
}
else {
	$params = array($active_version, $overall_comment, $status, intval($_POST['late']), $gd_id);
	$db->query("UPDATE gradeable_data SET gd_active_version=?, gd_overall_comment=?, gd_status=?, gd_late_days_used=?, gd_user_viewed_date=NULL WHERE gd_id=?",$params);
}


//update the number of late days for the student the first time grades are submitted
if ($status == 1 && !$is_graded){
    $db->query("INSERT INTO late_days_used (user_id, g_id, late_days_used) VALUES (?,?,?)", array($student, $g_id, intval($_POST['late'])));
}

$hwReport = new HWReport();
$hwReport->generateSingleReport($student, $g_id);

if($_GET["individual"] == "1") {
    header('Location: '.$SUBMISSION_URL."/index.php?semester={$_GET['semester']}&course={$_GET['course']}&component=grading&page=electronic&action=summary&gradeable_id={$_GET['g_id']}#user-row-".$student);
}
else {
    header('Location: '.$BASE_URL.'/account/index.php?course='.$_GET['course'].'&semester='.$_GET['semester'].'&g_id=' . $_GET["g_id"]);
}
