<?php
require_once("controller/data_functions.php");
require_once("controller/controller_functions.php");

$course=check_course();
$semester=check_semester();
$class_config=check_class_config($semester,$course);
$dev_team=$class_config["dev_team"];
$assignment_id=check_assignment_id($class_config);
$assignment_config = get_assignment_config($semester,$course, $assignment_id);
$num_parts=get_num_parts($assignment_config);

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf']) {
  $_SESSION['status'] = 'invalid_token';
  header("Location: index.php?page=displaymessage&semester=".$semester."&course=".$course."&assignment_id=".$assignment_id);
}

if (isset($_POST["svn_checkout"]) && $_POST["svn_checkout"] == "true") {

  $uploaded_files = "";
  $result = upload_homework($_SESSION["id"], $semester, $course, $assignment_id, $num_parts, $uploaded_files, $previous_files, true);

  $_SESSION["status"] = "uploaded_no_error";

}  else {

  for($i=0; $i<$num_parts; $i++){
    if(isset($_FILES["files".($i+1)])){
      $uploaded_files[$i+1] = $_FILES["files".($i+1)];
    }
  }

  $tmp = json_decode($_POST['previous_files']);
  for($i=0; $i<$num_parts; $i++){
    if(count($tmp[$i]) > 0){
      $previous_files[$i+1] = $tmp[$i];
    }
  }

  //Upload the files
  if (isset($uploaded_files) || isset($previous_files)) {
  // if (isset($_FILES["files"])) { // if (isset($_FILES["file"])) {
    $result = upload_homework($_SESSION["id"], $semester, $course, $assignment_id, $num_parts, $uploaded_files, $previous_files, false);

    if (isset($result["error"])) {
      //Go to error page?
      if ($result["error"] == "assignment_closed") {
        $_SESSION["status"] = "assignment_closed";
        header("Location: index.php?page=displaymessage&semester=".$semester."&course=".$course."&assignment_id=".$assignment_id);
	    exit();
      }
      $_SESSION["status"] = isset($result["message"]) ? $result["message"] : "upload_failed";
      header("Location: index.php?page=displaymessage&semester=".$semester."&course=".$course."&assignment_id=".$assignment_id);
      exit();
    }
    $_SESSION["status"] = "uploaded_no_error";
  }
  else {
    $_SESSION["status"] = "upload_failed";
  }

}


//Go back to homework page
header("Location: index.php?page=displaymessage&semester=".$semester."&course=".$course."&assignment_id=".$assignment_id);header("Location: index.php?page=displaymessage&semester=".$semester."&course=".$course."&assignment_id=".$assignment_id);