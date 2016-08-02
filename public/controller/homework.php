<?php
require_once("controller/controller_functions.php");
require_once("controller/data_functions.php");

//Make model function calls for homework here
//URL PARSING
$semester = check_semester();
$course = check_course();
$username = $_SESSION["id"];
$class_config = get_class_config($semester,$course);//Gets class.JSON data
if ($class_config == NULL) {
    ?><script>alert("Configuration for this class (class.JSON) is invalid.  Quitting");</script>
    <?php exit();
}
$most_recent_assignment_id =        most_recent_released_assignment_id($class_config);
$all_assignments =                  $class_config["assignments"];
$dev_team =                         $class_config["dev_team"];
//Get and validate assignment_id and assignment_version
//If not valid do last homework, last version
$assignment_id =                    parse_assignment_id_with_recent($class_config, $most_recent_assignment_id);
//$assignment_version =               parse_assignment_version_with_recent($username, $semester,$course, $assignment_id);
$assignment_version =               get_assignment_version($username, $semester,$course, $assignment_id);
$assignment_name =                  name_for_assignment_id($class_config, $assignment_id);
$assignment_link =                  link_for_assignment_id($class_config, $assignment_id);
$assignment_description =            description_for_assignment_id($class_config, $assignment_id);
$ta_grade_released =                is_ta_grade_released($class_config, $assignment_id);
$upload_message =                   get_upload_message($class_config);
$svn_checkout =                     is_svn_checkout($class_config, $assignment_id);
$view_points =                      is_points_visible($class_config, $assignment_id);
$view_hidden_points =               is_hidden_points_visible($class_config, $assignment_id);
$highest_version =                  get_highest_assignment_version($username, $semester,$course, $assignment_id);

//Assignment configuration data from config/build/build_XXX.json
$assignment_config =                get_assignment_config($semester,$course, $assignment_id);
$num_parts =                        get_num_parts($assignment_config);
$part_names =                       get_part_names($assignment_config);
$max_submissions_for_assignment =   $assignment_config["max_submissions"];
$assignment_message = "";
if (isset($assignment_config["assignment_message"])) {
  $assignment_message = $assignment_config["assignment_message"];
}

$points_received = 0;
$points_possible = 0;

//Gets testcase configuration data from config/build/build_XXX.json and matches it with results data for each testcase
$homework_tests =            get_homework_tests($username, $semester,$course, $assignment_id, $assignment_version, $assignment_config);

//Active version / version to submit
$active_version =        get_active_version($username, $semester,$course, $assignment_id);

$submitting_homework_tests = get_homework_tests($username, $semester,$course, $assignment_id, $active_version, $assignment_config);
$active_version_score =  0;
$active_version_score =  get_awarded_points_visible($submitting_homework_tests)." / ".get_points_visible($submitting_homework_tests);

$viewing_version_score =     get_awarded_points_visible($homework_tests);

//List of file names submitted for viewing version
$submitted_files =           get_submitted_files($username, $semester, $course, $assignment_id, $assignment_version);

//Is the submitting version being graded
$active_version_in_grading_queue = version_in_grading_queue($username, $semester,$course, $assignment_id, $active_version);
$active_version_in_grading_queue2 = version_in_grading_queue2($username, $semester,$course, $assignment_id, $active_version);

//Is the viewing version being graded
$assignment_version_in_grading_queue = version_in_grading_queue($username, $semester,$course, $assignment_id, $assignment_version);
$assignment_version_in_grading_queue2 = version_in_grading_queue2($username, $semester,$course, $assignment_id, $assignment_version);


// FIXME:  This incorrectly includes extra credit
//$points_visible =            $assignment_config["points_visible"];
// FIXME:  This looks correct.  Why are they different?  Why are there two?
$points_visible =            get_points_visible($submitting_homework_tests);

//List of submitted files that server is allowed to display
$files_to_view =            get_files_to_view($class_config,$semester,$course,$assignment_id, $username,$assignment_version);


if (isset($class_config["download_files"])){
    $download_files = $class_config["download_files"];
}
else{
    $download_files = false;
}
if (isset($class_config["grade_summary"])){
    $grade_summary = $class_config["grade_summary"];
}
else{
    $grade_summary = true;
}
if (isset($class_config["ta_grades"])){
    $ta_grades = $class_config["ta_grades"];
}
else{
    $ta_grades = true;
}

$status = parse_status();

//Data for assignment verion quick select dropdown
$select_submission_data = get_select_submission_data($username, $semester,$course, $assignment_id, $assignment_config, $highest_version);

render("homework", array(
    "semester"=>                $semester,
    "course"=>                  $course,
    "assignment_id"=>           $assignment_id,
    "assignment_name"=>         $assignment_name,
    "assignment_link"=>         $assignment_link,
    "assignment_description"=>  $assignment_description,
    "ta_grade_released"=>       $ta_grade_released,
    "upload_message"=>          $upload_message,
    "num_parts"=>               $num_parts,
    "part_names"=>              $part_names,
    "svn_checkout"=>            $svn_checkout,
    "all_assignments"=>         $all_assignments,
    "dev_team"=>                $dev_team,
    "points_visible"=>          $points_visible,
    "view_points"=>             $view_points,
    "view_hidden_points"=>      $view_hidden_points,
    "download_files"=>          $download_files,
    "grade_summary"=>           $grade_summary,
    "ta_grades"=>               $ta_grades,
      // added for debugging
    "username"=>                $username,

    "homework_tests"=>          $homework_tests,
    "select_submission_data"=>  $select_submission_data,
    "active_version"=>      $active_version,
    "active_version_score"=>$active_version_score,
    "viewing_version_score"=>   $viewing_version_score,
    "highest_version"=>         $highest_version,
    "assignment_version"=>      $assignment_version,
    "submitted_files"=>         $submitted_files,
    "max_submissions"=>         $max_submissions_for_assignment,
    "assignment_message"=>      $assignment_message,
    "active_version_in_grading_queue"=>$active_version_in_grading_queue,
    "active_version_in_grading_queue2"=>$active_version_in_grading_queue2,
    "assignment_version_in_grading_queue"=>$assignment_version_in_grading_queue,
    "assignment_version_in_grading_queue2"=>$assignment_version_in_grading_queue2,
    "status"=>                  $status,

    "files_to_view"=>           $files_to_view
    )
);
?>
