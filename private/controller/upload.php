<?php

require_once("../private/model/homework_model_functions.php");


//Upload the stuff

$course = "NONE";

if (isset($_FILES["file"])) {

    if (!isset($_GET["course"])) {
        echo "No course id";
        exit();
    }
    $course = htmlspecialchars($_GET["course"]);
    if (!strpos($course,"csci1200") &&
       !strpos($course,"csci1100") &&
       !strpos($course,"csci1200test") &&
       !strpos($course,"csci1100test")) {
        echo "BAD COURSE ".$course;
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
            header("Location: index.php?course=".$course."&assignment_id=".$assignment_id."&error=assignment_closed");
            exit();
        }

        header("Location: index.php?course=".$course."&assignment_id=".$assignment_id."&error=upload_failed");
        exit();
    }
}


$assignment_version = 1;//htmlspecialchars($_GET["assignment_version"]);
$assignment_config = get_assignment_config($_SESSION["id"], $assignment_id);


// automatically set new upload as active version   
change_assignment_version($_SESSION["id"], $assignment_id, $assignment_version, $assignment_config);



//Go back to homework page
header("Location: index.php?course=".$course."&assignment_id=".$assignment_id);

?>
