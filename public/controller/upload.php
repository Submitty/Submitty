<?php

require_once("controller/data_functions.php");

//Upload the stuff



if (isset($_GET["course"])) {
  $tmp = htmlspecialchars($_GET["course"]);
  if (!is_valid_course($tmp)) {
    $course = "UPLOAD_NONE_B".$tmp;
    // FIXME, displaymesssage does not exist
    header("Location: index.php?page=displaymessage&course=".$course);
   } else {
    $course = $tmp;
  }
 } else {
  // FIXME: need a better default
  $course = "default_course";
  // maybe should exit?
  //exit(1);
}


if (isset($_GET["semester"])) {
  $tmp = htmlspecialchars($_GET["semester"]);
  if (!is_valid_semester($tmp)) {
    //$semester = "UPLOAD_NONE_B".$tmp;
    // FIXME, displaymesssage does not exist
    header("Location: index.php?page=displaymessage&semester=".$semester);
  } else {
    $semester = $tmp;
  }
} else {
  // FIXME: need a better default
  $semester = "default_semester";
  // maybe should exit?
  //exit(1);
}


if (isset($_GET["assignment_id"])) {
  $assignment_id = htmlspecialchars($_GET["assignment_id"]);

  // FIXME: add a validity check for assignment id

} else {
  // FIXME: maybe do something better
  exit(1);
}


if (isset($_FILES["file"])) {

    $uploaded_file = $_FILES["file"];
    $result = upload_homework($_SESSION["id"], $semester, $course, $assignment_id,$uploaded_file);

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
} else {
    $_SESSION["status"] = "upload_failed";
}
//Go back to homework page
header("Location: index.php?page=displaymessage&semester=".$semester."&course=".$course."&assignment_id=".$assignment_id);

?>
