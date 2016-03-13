<?php
require_once("controller/data_functions.php");
require_once("controller/controller_functions.php");

$username=get_username();
$course=check_course();
$semester=check_semester();
$class_config=check_class_config($semester,$course);
$dev_team=$class_config["dev_team"];
$assignment_id=check_assignment_id($class_config);
$assignment_version=get_assignment_version($username, $semester, $course, $assignment_id);
$assignment_config = get_assignment_config($semester, $course, $assignment_id);


if (!can_edit_assignment($_SESSION["id"], $semester, $course, $assignment_id, $assignment_config)) {
   $_SESSION["status"] = "assignment_closed";
   header ("Location: index.php?semester=".$semester."&course=".$course."&assignment_id=".$assignment_id."&assignment_version=".$assignment_version);
   exit();
}
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf']) {
   $_SESSION['status'] = "invalid_token";
   header ("Location: index.php?semester=".$semester."&course=".$course."&assignment_id=".$assignment_id."&assignment_version=".$assignment_version);
   exit();
}
change_assignment_version($_SESSION["id"], $semester, $course, $assignment_id, $assignment_version, $assignment_config);
header ("Location: index.php?semester=".$semester."&course=".$course."&assignment_id=".$assignment_id."&assignment_version=".$assignment_version);
exit();
