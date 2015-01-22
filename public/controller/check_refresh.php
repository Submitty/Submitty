<?php
require_once("controller/data_functions.php");
require_once("controller/controller_functions.php");

$course=check_course();
$semester=check_semester();

if (isset($_POST["assignment_id"]) && isset($_POST["assignment_version"]) && isset($_POST["submitting_version"]) && isset($_POST["assignment_graded"]) && isset($_POST["submitting_graded"])) {
    $assignment_id = htmlspecialchars($_POST["assignment_id"]);
    $assignment_version = htmlspecialchars($_POST["assignment_version"]);
    $submitting_version = htmlspecialchars($_POST["submitting_version"]);
    $assignment_graded = htmlspecialchars($_POST["assignment_graded"]);
    $submitting_graded = htmlspecialchars($_POST["submitting_graded"]);
    if (!$assignment_graded) {
        $results = get_assignment_results($_SESSION["id"], $semester, $course, $assignment_id, $assignment_version);
        if ($results != NULL && $results != false) {
            echo "REFRESH_ME";
            exit();
        }
    }
    if (!$submitting_graded)
    {
        $results = get_assignment_results($_SESSION["id"], $semester, $course, $assignment_id, $submitting_version);
        if ($results != NULL && $results != false) {
            echo "REFRESH_ME";
            exit();
        }
    }
}
echo "false";
