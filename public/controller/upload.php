<?php
require_once("controller/data_functions.php");
require_once("controller/controller_functions.php");

$course=check_course();
$semester=check_semester();
$class_config=check_class_config($semester,$course);
$dev_team=$class_config["dev_team"];
$assignment_id=check_assignment_id($class_config);

if (isset($_POST["svn_checkout"])) {

    $uploaded_file = "";
    $result = upload_homework($_SESSION["id"], $semester, $course, $assignment_id,$uploaded_file, true);

    $_SESSION["status"] = "uploaded_no_error";
//    $_SESSION["status"] = "upload_failed";



}  else {

//Upload the files
if (isset($_FILES["file"])) {

    $uploaded_file = $_FILES["file"];
    $result = upload_homework($_SESSION["id"], $semester, $course, $assignment_id,$uploaded_file, false);

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
header("Location: index.php?page=displaymessage&semester=".$semester."&course=".$course."&assignment_id=".$assignment_id);

?>
