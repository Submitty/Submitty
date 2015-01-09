<?php
require_once("controller/data_functions.php");


$course="UPDATE_NONE_A";
$semester="UPDATE_NONE_A";

if (isset($_GET["semester"])) {
   $semester = htmlspecialchars($_GET["semester"]);
}

if (isset($_GET["course"])) {
   $course = htmlspecialchars($_GET["course"]);

   if (isset($_GET["assignment_id"]) && isset($_GET["assignment_version"])) {
      
       $assignment_id = htmlspecialchars($_GET["assignment_id"]);
       $assignment_version = htmlspecialchars($_GET["assignment_version"]);
       $assignment_config = get_assignment_config($_SESSION["id"], $semester, $course, $assignment_id);
       if (!can_edit_assignment($_SESSION["id"], $semester, $course, $assignment_id, $assignment_config)) {
           $_SESSION["status"] = "assignment_closed";
           header ("Location: index.php?semester=".$semester."&course=".$course."&assignment_id=".$assignment_id."&assignment_version=".$assignment_version);
           exit();
       }
       change_assignment_version($_SESSION["id"], $semester, $course, $assignment_id, $assignment_version, $assignment_config);
       header ("Location: index.php?semester=".$semester."&course=".$course."&assignment_id=".$assignment_id."&assignment_version=".$assignment_version);
       exit();
    } else {
    header ("Location: index.php?semester=".$semester."&course=".$course);
    }
} else {
    header ("Location: index.php?semester=UPDATE_NO_SEMESTER&course=UPDATE_NONE_B");
    exit();
}
