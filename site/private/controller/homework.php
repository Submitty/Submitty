<?php
require_once("../private/controller/helper.php");
require_once("../private/model/homework_model_functions.php");

//Make model function calls for homework here

$last_homework = last_homework_number();
if (!isset($homework_number)) {
    $homework_number = $last_homework;
}
$max_version_number = max_version_number($_SESSION["id"], $homework_number);
if (!isset($version_number)) {
    $version_number = $max_version_number;
}
if (!($version_number >= 0 && $version_number <= $max_version_number)) {
    $version_number = $max_version_number;
}

$max_submissions = max_submissions();

if (isset($_GET["number"])) {
    $homework_number = htmlspecialchars($_GET["number"]);
}
if (isset($_GET["assignment"])) {
    $assignment = htmlspecialchars($_GET["assignment"]);
}
if (!($assignment) || strpos($assignment, " ")) {
    $assignment = "Homework";
}
if (!($homework_number > 0 && $homework_number <= $last_homework)) {
    $homework_number = $last_homework;
}
if (isset($_GET["version"])) {
    $version_number = htmlspecialchars($_GET["version"]);
}
if (!($version_number > 0 && $version_number <= $max_version_number)) {
    $version_number = $max_version_number;
}

//Function call to make sure assignment and homework_number and version number are all valid
//If not valid do last homework
//Function call to get data for assignment, homework_number and version number 

$points_received = 15;//Points_received for entire homework as an int
$points_possible = 20;//Points_possible for entire homework as an int

$TA_grade = false;
//This is the summary for the entire homework
//Either fill in value as a string or fill in score as an int.
//Points_possible as an int is optional when score is used



$homework_summary = array(
    array(
        "title"=>"Points for README.txt",
        "score"=>3,
        "points_possible"=>"3"),
    array(
        "title"=>"Points for compilation",
        "score"=>4,
        "points_possible"=>4),
    array(
        "title"=>"Test 1",
        "score"=>4,
        "points_possible"=>4),
    array(
        "title"=>"Test 2",
        "score"=>0,
        "points_possible"=>4),
    array(
        "title"=>"Automatic extra credit(w/o hidden)",
        "value"=>"+0 points"),
    array(
        "title"=>"Automatic grading total",
        "score"=>11,
        "points_possible"=>15)
);

//This is the data with the diff comparisons
$homework_tests = array(
    array("title"=>"Test 1", "score"=>4, "points_possible"=>4),
    array("title"=>"Test 2", "score"=>0, "points_possible"=>4)
);



render("homework", array(
    "homework_number"=>$homework_number,
    "last_homework"=>$last_homework,
    "points_possible"=>$points_possible,
    "points_received"=>$points_received,
    "homework_summary"=>$homework_summary,
    "homework_tests"=>$homework_tests,
    "max_version_number"=>$max_version_number,
    "version_number"=>$version_number,
    "TA_grade"=>$TA_grade,
    "max_submissions"=>$max_submissions,
    "assignment"=>$assignment
    )
);
?>
