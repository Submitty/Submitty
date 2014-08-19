<?php

require_once("../private/model/homework_model_functions.php");


//Upload the stuff

$course = "UPLOAD_NONE_A";

   if (isset($_GET["course"])) {
   $tmp = htmlspecialchars($_GET["course"]);
   if (!is_valid_course($tmp)) {
       $course = "UPLOAD_NONE_B".$tmp;
   } else {
   $course = $tmp;
   }
   }

if (isset($_FILES["file"])) {

    if (!isset($_GET["course"])) {
        echo "No course id";
        exit();
    }
    $course = htmlspecialchars($_GET["course"]);
    if ($course != "csci1100" &&
        $course != "csci1200" &&
        $course != "csci1200test" &&
        $course != "csci1100test") {
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
    $result = upload_homework($_SESSION["id"],$assignment_id,$uploaded_file);
    if (isset($result["error"])) {
        //Go to error page?
        if ($result["error"] == "assignment_closed") {
            $_SESSION["status"] = "assignment_closed";
            header("Location: index.php?page=displaymessage&course=".$course."&assignment_id=".$assignment_id);
            exit();
        }
        $_SESSION["status"] = "upload_failed";
        header("Location: index.php?page=displaymessage&course=".$course."&assignment_id=".$assignment_id);
        exit();
    }
}
//Go back to homework page
$_SESSION["status"] = "uploaded_no_error";
header("Location: index.php?page=displaymessage&course=".$course."&assignment_id=".$assignment_id);

?>
