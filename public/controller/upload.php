<?php

require_once("controller/data_functions.php");


//Upload the stuff

$course = "UPLOAD_NONE_A";
$semester = "f14";

   if (isset($_GET["course"])) {
   $tmp = htmlspecialchars($_GET["course"]);
   if (!is_valid_course($tmp)) {
       $course = "UPLOAD_NONE_B".$tmp;
   } else {
   $course = $tmp;
   }
   }


if (isset($_GET["semester"])) {
   $tmp = htmlspecialchars($_GET["semester"]);
   if (!is_valid_semester($tmp)) {
       //$semester = "UPLOAD_NONE_B".$tmp;
   } else {
       $semester = $tmp;
   }
}



if (isset($_FILES["file"])) {

    if (!isset($_GET["course"])) {
        echo "No course id";
        exit();
    }

    if (!isset($_GET["semester"])) {
        echo "No semester id";
        exit();
    }

    $semester = htmlspecialchars($_GET["semester"]);
    if ($semester != "f14" &&
        $semester != "s15") {
        echo "BAD SEMESTER '".$semester."'";
        exit();
    }


    $course = htmlspecialchars($_GET["course"]);
    if ($course != "csci1100" &&
        $course != "csci1200" &&
        $course != "csci1200test" &&
        $course != "csci1100test" &&
        $course != "csci4960") {
        echo "BAD COURSE '".$course."'";
        exit();
    }

    if (!isset($_GET["assignment_id"])) {
        echo "No assignment id";
        exit();
    }
    $assignment_id = htmlspecialchars($_GET["assignment_id"]);
    if (strpos($assignment_id," ")) {
        //Go to error page?
        echo "Invalid assignment id";
        exit();
    }
    $uploaded_file = $_FILES["file"];//THIS NEEDS TO BE MADE HACKER PROOF
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
