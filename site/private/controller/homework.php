<?php
require_once("../private/controller/helper.php");
require_once("../private/model/homework_model_functions.php");

//Make model function calls for homework here

$username = $_SESSION["id"];
$most_recent_assignment_id = most_recent_assignment_id($username);
$most_recent_assignment_version = most_recent_assignment_version($username, $most_recent_assignment_id);

$all_assignments = get_assignments($username);


if (isset($_GET["assignment_id"])) {//Which homework or which lab the user wants to see
    $assignment_id = htmlspecialchars($_GET["assignment_id"]);
    if (!is_valid_assignment($username, $assignment_id)) {
        $assignment_id = $most_recent_assignment_id;
    }
    if (isset($_GET["assignment_version"])) {
        $assignment_version = htmlspecialchars($_GET["assignment_version"]);
    }
    if (!isset($assignment_version) || !is_valid_assignment_version($username, $assignment_id, $assignment_version)) {
        $assignment_version = most_recent_assignment_version($username, $assignment_id);
    }
} else {
    $assignment_id = $most_recent_assignment_id;
    $assignment_version = $most_recent_assignment_version;
}
$assignment_name = name_for_assignment_id($username, $assignment_id);

$highest_version = most_recent_assignment_version($username, $assignment_id);
$max_submissions_for_assignment = max_submissions_for_assignment($username, $assignment_id);





//Function call to make sure assignment and homework_number and version number are all valid
//If not valid do last homework
//Function call to get data for assignment, homework_number and version number 

$points_received = 15;//Points_received for entire homework as an int
$points_possible = 20;//Points_possible for entire homework as an int

$TA_grade = TA_grade($username, $assignment_id);
//This is the summary for the entire homework
//Either fill in value as a string or fill in score as an int.
//Points_possible as an int is optional when score is used

// Grab the assignment and user information regarding test cases
$assignment_config = get_assignment_config($username, $assignment_id);
$testcases_info = $assignment_config["testcases"];
$version_results = get_assignment_results($username, $assignment_id, $assignment_version);
if ($version_results) { 
    $testcases_results = $version_results["testcases"];
} else {
    $testcases_results = array();
}
$homework_tests = array();

if (count($testcases_results) != count($testcases_info)) {
    $homework_summary = array();
    for ($i = 0; $i < count($testcases_info); $i++) {
        for ($u = 0; $u < count($testcases_results); $u++){
            if ($testcases_info[$i]["title"] == $testcases_results[$u]["test_name"]){
                array_push($homework_summary, array("title"=>$testcases_info[$i]["title"], "score"=>$testcases_results[$u]["points_awarded"], "points_possible"=>$testcases_info[$i]["points"]));
                array_push($homework_tests, array(
                    "title"=>$testcases_info[$i]["title"],
                    "points_possible"=>$testcases_info[$i]["points"],
                    "points"=>$testcases_results[$u]["points_awarded"],
                    "message"=> isset($testcases_results[$u]["message"]) ? $testcases_results[$u]["message"] : "",
                    "diff"=> isset($testcases_results[$u]["diff"]) ? get_testcase_diff($username, $assignment_id, $assignment_version,$testcases_results[$u]["diff"]) : ""
                ));
                break;
            }
        }

    }
} else {
    $homework_summary = array();
    for ($i = 0; $i < count($testcases_info); $i++) {
         array_push($homework_summary, array("title"=>$testcases_info[$i]["title"], "score"=>$testcases_results[$i]["points_awarded"], "points_possible"=>$testcases_info[$i]["points"]));//THIS NEEDS TO CHANGE TO SEARCH FOR THE CORRECT RESULT BY TITLE
         array_push($homework_tests, array(
            "title"=>$testcases_info[$i]["title"],
            "points_possible"=>$testcases_info[$i]["points"],
            "score"=>$testcases_results[$i]["points_awarded"],
            "message"=> isset($testcases_results[$i]["message"]) ? $testcases_results[$i]["message"] : "",
            "diff"=> isset($testcases_results[$i]["diff"]) ? get_testcase_diff($username, $assignment_id, $assignment_version,$testcases_results[$i]["diff"]) : ""
        ));
    }

}

// //This is the data with the diff comparisons
// $homework_tests = array(
//     array("title"=>"Test 1", "score"=>4, "points_possible"=>4),
//     array("title"=>"Test 2", "score"=>0, "points_possible"=>4)
// );
$submitting_version = get_user_submitting_version($_SESSION["id"], $assignment_id);
$submitting_results = get_assignment_results($_SESSION["id"], $assignment_id, $submitting_version);
if ($submitting_results) {
    $submitting_version_score = $submitting_results["points_awarded"]." / ".$assignment_config["points_visible"];
} else {
    $submitting_version_score = "0 / ".$assignment_config["points_visible"];
}


$submitting_version_in_grading_queue = version_in_grading_queue($username, $assignment_id, $submitting_version);

$assignment_version_in_grading_queue = version_in_grading_queue($username, $assignment_id, $assignment_version);
render("homework", array(
    "assignment_id"=>$assignment_id,
    "assignment_name"=>$assignment_name,
    "all_assignments"=>$all_assignments,
    "points_possible"=>$points_possible,
    "points_received"=>$points_received,
    "homework_summary"=>$homework_summary,
    "homework_tests"=>$homework_tests,
    "submitting_version"=>$submitting_version,
    "submitting_version_score"=>$submitting_version_score,
    "highest_version"=>$highest_version,
    "assignment_version"=>$assignment_version,
    "TA_grade"=>$TA_grade,
    "max_submissions"=>$max_submissions_for_assignment,
    "submitting_version_in_grading_queue"=>$submitting_version_in_grading_queue,
    "assignment_version_in_grading_queue"=>$assignment_version_in_grading_queue
    )
);
?>
