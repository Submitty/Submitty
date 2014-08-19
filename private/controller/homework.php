<?php
require_once("../private/controller/helper.php");
require_once("../private/model/homework_model_functions.php");

//Make model function calls for homework here

$error = "";
if (isset($_GET["error"])) {//Errors are pushed to the view
    $error_code = htmlspecialchars($_GET["error"]);
    if ($error_code == "upload_failed") {
        $error = "Upload failed";
    } else if ($error_code == "assignment_closed") {
        $error = "This assignment is closed";
    }
}

$status = "";
if (isset($_GET["status"])) {//Upload status is pushed to the view
    $status_code = htmlspecialchars($_GET["status"]);
    if ($status_code == "uploaded_no_error") {
        $status = "Upload Successful";
    }
}


$username = $_SESSION["id"];
$class_config = get_class_config($_SESSION["id"]);//Gets class.JSON data
if ($class_config == NULL) {
    ?><script>alert("Configuration for this class (class.JSON) is invalid.  Quitting");</script>
    <?php exit();
}
$most_recent_assignment_id = $class_config["default_assignment"];
$most_recent_assignment_version = most_recent_assignment_version($username, $most_recent_assignment_id);

$all_assignments = $class_config["assignments"];


if (isset($_GET["course"])) {
    $course = htmlspecialchars($_GET["course"]);
    if (!is_valid_course($course)) {
          $course = "CONTROLLER_HOMEWORK_NONE_A";
    } 
} else {
    $course = "CONTROLLER_HOMEWORK_NONE_B";
}


if (isset($_GET["assignment_id"])) {//Which homework or which lab the user wants to see
    $assignment_id = htmlspecialchars($_GET["assignment_id"]);
    if (!is_valid_assignment($class_config, $assignment_id)) {
        $assignment_id = $most_recent_assignment_id;
    }
    if (isset($_GET["assignment_version"])) {
        $assignment_version = htmlspecialchars($_GET["assignment_version"]);
    }
    if (!isset($assignment_version) || !is_valid_assignment_version($username, $assignment_id, $assignment_version)) {
        $assignment_version = most_recent_assignment_version($username, $assignment_id);
    }
} else {//Otherwise use the most recent assignment and version
    $assignment_id = $most_recent_assignment_id;
    $assignment_version = $most_recent_assignment_version;
}
$assignment_name = name_for_assignment_id($class_config, $assignment_id);

$highest_version = most_recent_assignment_version($username, $assignment_id);





//Function call to make sure assignment and homework_number and version number are all valid
//If not valid do last homework
//Function call to get data for assignment, homework_number and version number 


$TA_grade = TA_grade($username, $assignment_id);
//This is the summary for the entire homework
//Either fill in value as a string or fill in score as an int.
//Points_possible as an int is optional when score is used

// Grab the assignment and user information regarding test cases
$assignment_config = get_assignment_config($username, $assignment_id);//Gets data from assignment_config.json
$testcases_info = $assignment_config["testcases"];//These are the tests run on a homework (for grading etc.)
$version_results = get_assignment_results($username, $assignment_id, $assignment_version);//Gets user results data from submission.json for the specific version of the assignment
if ($version_results) { 
    $testcases_results = $version_results["testcases"];
} else {
    $testcases_results = array();
}

$max_submissions_for_assignment = $assignment_config["max_submissions"];

$points_received = 0;
$points_possible = 0;

$homework_tests = array();
$homework_summary = array();
for ($i = 0; $i < count($testcases_info); $i++) {
    for ($u = 0; $u < count($testcases_results); $u++){
        //Match the assignment results (user specific) with the configuration (class specific)
        if ($testcases_info[$i]["title"] == $testcases_results[$u]["test_name"]){
            //Data to display in summary table
            array_push($homework_summary, array(
                "title"=>$testcases_info[$i]["title"], 
                "score"=>$testcases_results[$u]["points_awarded"], 
                "points_possible"=>$testcases_info[$i]["points"]
            ));
            //Data to display in the detail view / Diff Viewer (bottom)
            array_push($homework_tests, array(
                "title"=>$testcases_info[$i]["title"],
                "is_hidden"=>$testcases_info[$i]["hidden"],
                "points_possible"=>$testcases_info[$i]["points"],
                "score"=>$testcases_results[$u]["points_awarded"],
                "message"=> isset($testcases_results[$u]["message"]) ? $testcases_results[$u]["message"] : "",
                "diff"=> isset($testcases_results[$u]["diff"]) ? get_testcase_diff($username, $assignment_id, $assignment_version,$testcases_results[$u]["diff"]) : ""
    //"diff"=> isset($testcases_results[$u]["diff"]) ? "a" : "b"
            ));
            break;
        }
    }
}

$submitting_version = get_user_submitting_version($_SESSION["id"], $assignment_id);//What version they are using as their final submission
$submitting_version_score = 0;
$submitting_version_score = get_awarded_points_visible($_SESSION["id"], $assignment_id, $submitting_version)." / ".$assignment_config["points_visible"];
;
$viewing_version_score = 0;
$viewing_version_score = get_awarded_points_visible($_SESSION["id"], $assignment_id, $assignment_version);


$submitted_files = get_submitted_files($_SESSION["id"], $assignment_id, $assignment_version);

$submitting_version_in_grading_queue = version_in_grading_queue($username, $assignment_id, $submitting_version);

$assignment_version_in_grading_queue = version_in_grading_queue($username, $assignment_id, $assignment_version);

render("homework", array(
    "course"=>$course,
    "assignment_id"=>$assignment_id,
    "assignment_name"=>$assignment_name,
    "all_assignments"=>$all_assignments,
    "points_possible"=>$points_possible,
    "points_visible"=>$assignment_config["points_visible"],
    "homework_summary"=>$homework_summary,
    
      // added for debugging
    "username"=>$username,
    "version_results"=>$version_results,
    "testcases_results"=>$testcases_results,
    "testcases_info"=>$testcases_info,

    "homework_tests"=>$homework_tests,
    "submitting_version"=>$submitting_version,
    "submitting_version_score"=>$submitting_version_score,
    "viewing_version_score"=>$viewing_version_score,
    "highest_version"=>$highest_version,
    "assignment_version"=>$assignment_version,
    "submitted_files"=>$submitted_files,
    "TA_grade"=>$TA_grade,
    "max_submissions"=>$max_submissions_for_assignment,
    "submitting_version_in_grading_queue"=>$submitting_version_in_grading_queue,
    "assignment_version_in_grading_queue"=>$assignment_version_in_grading_queue,
    "error"=>$error,
    "status"=>$status
    )
);
?>
