<?php
require_once("../private/controller/helper.php");
require_once("../private/model/homework_model_functions.php");
//Make model function calls for homework here

$most_recent_assignment = most_recent_assignment($_SESSION["id"]);
$most_recent_assignment_number = most_recent_assignment_number($_SESSION["id"]);
$most_recent_assignment_version = most_recent_assignment_version($_SESSION["id"]);

$all_assignments = get_assignments($_SESSION["id"]);


if (isset($_GET["number"]) && isset($_GET["assignment"])) {//Which homework or which lab the user wants to see
    $assignment_number = htmlspecialchars($_GET["number"]);
    $assignment = htmlspecialchars($_GET["assignment"]);
    if (!is_valid_assignment_number($_SESSION["id"], $assignment, $assignment_number)) {
        $assignment = $most_recent_assignment;
        $assignment_number = $most_recent_assignment_number;
    }
    if (isset($_GET["version"])) {
        $assignment_version = htmlspecialchars($_GET["version"]);
    }
    if (!isset($assignment_version) || !is_valid_assignment_version($_SESSION["id"], $assignment, $assignment_number, $assignment_version)) {
        $assignment_version = last_assignment_version($_SESSION["id"], $assignment, $assignment_number);
    }
} else if (isset($_GET["arraynumber"])) {
    $i = htmlspecialchars($_GET["arraynumber"]);
    if ($i >= 0 && $i < count($all_assignments)) {
        $assignment = $all_assignments[$i]["assignment"];
        $assignment_number = $all_assignments[$i]["number"];
        $assignment_version = last_assignment_version($_SESSION["id"], $assignment, $assignment_number);
    } else {
        $assignment = $most_recent_assignment;
        $assignment_number = $most_recent_assignment_number;
        $assignment_version = $most_recent_assignment_version;
    }
} else {
    $assignment = $most_recent_assignment;
    $assignment_number = $most_recent_assignment_number;
    $assignment_version = $most_recent_assignment_version;
}

$max_version_number = max_assignment_version($_SESSION["id"], $assignment, $assignment_number);
$max_submissions_for_assignment = max_submissions_for_assignment($_SESSION["id"], $assignment, $assignment_number);





//Function call to make sure assignment and homework_number and version number are all valid
//If not valid do last homework
//Function call to get data for assignment, homework_number and version number 

$points_received = 15;//Points_received for entire homework as an int
$points_possible = 20;//Points_possible for entire homework as an int

$TA_grade = TA_grade($_SESSION["id"], $assignment, $assignment_number);
//This is the summary for the entire homework
//Either fill in value as a string or fill in score as an int.
//Points_possible as an int is optional when score is used



$homework_summary = array(//Demo data
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
    "assignment"=>$assignment,
    "homework_number"=>$assignment_number,
    "all_assignments"=>$all_assignments,
    "points_possible"=>$points_possible,
    "points_received"=>$points_received,
    "homework_summary"=>$homework_summary,
    "homework_tests"=>$homework_tests,
    "max_version_number"=>$max_version_number,
    "version_number"=>$assignment_version,
    "TA_grade"=>$TA_grade,
    "max_submissions"=>$max_submissions_for_assignment,
    )
);
?>
