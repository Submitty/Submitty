<?php
require_once("../private/controller/helper.php");
require_once("../private/model/homework_model_functions.php");

//Make model function calls for homework here

$most_recent_assignment_id = most_recent_assignment_id($_SESSION["id"]);
$most_recent_assignment_version = most_recent_assignment_version($_SESSION["id"], $most_recent_assignment_id);

$all_assignments = get_assignments($_SESSION["id"]);


if (isset($_GET["assignment_id"])) {//Which homework or which lab the user wants to see
    $assignment_id = htmlspecialchars($_GET["assignment_id"]);
    if (!is_valid_assignment($_SESSION["id"], $assignment_id)) {
        $assignment_id = $most_recent_assignment_id;
    }
    if (isset($_GET["version"])) {
        $assignment_version = htmlspecialchars($_GET["version"]);
    }
    if (!isset($assignment_version) || !is_valid_assignment_version($_SESSION["id"], $assignment_id, $assignment_version)) {
        $assignment_version = most_recent_assignment_version($_SESSION["id"], $assignment_id);
    }
} else {
    $assignment_id = $most_recent_assignment_id;
    $assignment_version = $most_recent_assignment_version;
}
$assignment_name = name_for_assignment_id($_SESSION["id"], $assignment_id);

$highest_version = most_recent_assignment_version($_SESSION["id"], $assignment_id);
$max_submissions_for_assignment = max_submissions_for_assignment($_SESSION["id"], $assignment_id);





//Function call to make sure assignment and homework_number and version number are all valid
//If not valid do last homework
//Function call to get data for assignment, homework_number and version number 

$points_received = 15;//Points_received for entire homework as an int
$points_possible = 20;//Points_possible for entire homework as an int

$TA_grade = TA_grade($_SESSION["id"], $assignment_id);
//This is the summary for the entire homework
//Either fill in value as a string or fill in score as an int.
//Points_possible as an int is optional when score is used

// Grab the assignment and user information regarding test cases
$testcases_info = get_testcase_config($_SESSION["id"], $assignment_id);
$testcases_results = get_testcase_results($_SESSION["id"], $assignment_id, $assignment_version);
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
                    "points"=>$testcases_results[$u]["points_awarded"]
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
            "score"=>$testcases_results[$i]["points_awarded"]
        ));
    }

}

// //This is the data with the diff comparisons
// $homework_tests = array(
//     array("title"=>"Test 1", "score"=>4, "points_possible"=>4),
//     array("title"=>"Test 2", "score"=>0, "points_possible"=>4)
// );

$submitting_version = 1;
$submitting_version_score = "11/15";

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
    )
);
?>
