<?php
include "../../toolbox/functions.php";

$oid = intval($_GET['oid']);

$params = array($oid);
$db->query("SELECT * FROM other_grades WHERE oid=?", $params);
if (count($db->rows()) == 0) {
    echo "no other exists with oid '{$oid}'";
    die();
}
$other_details = $db->row();

$rcs = $_GET["rcs"];
$score = isset($_POST['score']) ? floatval($_POST["score"]) : 0;
$score = ($score < 0) ? 0 : $score;
$text = isset($_POST['text']) ? htmlentities($_POST['text']) : "";

$db->query("SELECT student_id FROM students WHERE student_rcs=?", array($rcs));
if (count($db->rows()) == 0) {
    echo "failure, student '{$rcs}' does not exist";
    die();
}

$params = array($oid, $rcs);
$db->query("SELECT * FROM grades_others WHERE oid=? AND student_rcs=?", $params);

if(count($db->rows()) == 1) {
    // UPDATE
    $temp = $db->row();
    $params = array($score, $text, \app\models\User::$user_id, $temp["grades_other_id"]);
    $db->query("UPDATE grades_others SET grades_other_score=?, grades_other_text=?, grades_other_user_id=? WHERE grades_other_id=?", $params);
}
else {
    // INSERT
    $params = array($oid, $rcs, $score, $text, \app\models\User::$user_id);
    $db->query("INSERT INTO grades_others (oid, student_rcs, grades_other_score, grades_other_text, grades_other_user_id) VALUES (?,?,?,?,?)", $params);
}

echo "updated";