<?php
require_once("../private/controller/helper.php");
require_once("../private/model/homework_model_functions.php");

//Make model function calls for homework here

$status = "";
if (isset($_SESSION["status"])) {
    $status_code = htmlspecialchars($_SESSION["status"]);
    if ($status_code == "uploaded_no_error") {
        $status = "Upload Successful!";
    } else if ($status_code == "upload_failed") {
        $status = "Unknown error.  Upload failed.";
    } else if ($status_code == "assignment_closed") {
        $status = "Unable to upload, this assignment is closed";
    } else if ($status_code != "") {
        $status = $status_code;
    }
    $_SESSION["status"] = "";
}
if (isset($_GET["course"])) {
    $course = htmlspecialchars($_GET["course"]);
    if (!is_valid_course($course)) {
          $course = "CONTROLLER_HOMEWORK_NONE_A";
    } 
} else {
    $course = "CONTROLLER_HOMEWORK_NONE_B";
}


$username = $_SESSION["id"];
$class_config = get_class_config($course);//Gets class.JSON data
if ($class_config == NULL) {
    ?><script>alert("Configuration for this class (class.JSON) is invalid.  Quitting");</script>
    <?php exit();
}



$most_recent_assignment_id = $class_config["default_assignment"];
$most_recent_assignment_version = most_recent_assignment_version($username, $course, $most_recent_assignment_id);

$all_assignments = $class_config["assignments"];



if (isset($_GET["assignment_id"])) {//Which homework or which lab the user wants to see
    $assignment_id = htmlspecialchars($_GET["assignment_id"]);
    if (!is_valid_assignment($class_config, $assignment_id)) {
        $assignment_id = $most_recent_assignment_id;
    }
    if (isset($_GET["assignment_version"])) {
        $assignment_version = htmlspecialchars($_GET["assignment_version"]);
    }
    if (!isset($assignment_version) || !is_valid_assignment_version($username, $course, $assignment_id, $assignment_version) || $assignment_version == "") {
        $assignment_version = most_recent_assignment_version($username, $course, $assignment_id);
    }
} else {//Otherwise use the most recent assignment and version
    $assignment_id = $most_recent_assignment_id;
    $assignment_version = $most_recent_assignment_version;
}
$assignment_name = name_for_assignment_id($class_config, $assignment_id);

$highest_version = most_recent_assignment_version($username, $course, $assignment_id);





//Function call to make sure assignment and homework_number and version number are all valid
//If not valid do last homework
//Function call to get data for assignment, homework_number and version number 


$TA_grade = TA_grade($username, $assignment_id);
//This is the summary for the entire homework
//Either fill in value as a string or fill in score as an int.
//Points_possible as an int is optional when score is used

// Grab the assignment and user information regarding test cases
$assignment_config = get_assignment_config($username, $course, $assignment_id);//Gets data from assignment_config.json
$testcases_info = $assignment_config["testcases"];//These are the tests run on a homework (for grading etc.)
$version_results = get_assignment_results($username, $course, $assignment_id, $assignment_version);//Gets user results data from submission.json for the specific version of the assignment
if ($version_results) { 
    $testcases_results = $version_results["testcases"];
} else {
    $testcases_results = array();
}

$max_submissions_for_assignment = $assignment_config["max_submissions"];

$points_received = 0;
$points_possible = 0;

$homework_tests = get_homework_tests($username, $course, $assignment_id, $assignment_version, $assignment_config);




$submitting_version = get_user_submitting_version($username, $course, $assignment_id);//What version they are using as their final submission
$submitting_homework_tests = get_homework_tests($username, $course, $assignment_id, $submitting_version, $assignment_config);
$submitting_version_score = 0;
$submitting_version_score = get_awarded_points_visible($submitting_homework_tests)." / ".$assignment_config["points_visible"];
$viewing_version_score = get_awarded_points_visible($homework_tests);
$submitted_files = get_submitted_files($username, $course, $assignment_id, $assignment_version);

$submitting_version_in_grading_queue = version_in_grading_queue($username, $course, $assignment_id, $submitting_version);

$assignment_version_in_grading_queue = version_in_grading_queue($username, $course, $assignment_id, $assignment_version);
$points_visible = get_points_visible($homework_tests);
$select_submission_data = get_select_submission_data($username, $course, $assignment_id, $highest_version);
render("homework", array(
    "course"=>$course,
    "assignment_id"=>$assignment_id,
    "assignment_name"=>$assignment_name,
    "all_assignments"=>$all_assignments,
    "points_visible"=>$points_visible,
    
      // added for debugging
    "username"=>$username,
    "version_results"=>$version_results,
    "testcases_results"=>$testcases_results,
    "testcases_info"=>$testcases_info,

    "homework_tests"=>$homework_tests,
    "select_submission_data"=>$select_submission_data,
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
    "status"=>$status
    )
);
?>
