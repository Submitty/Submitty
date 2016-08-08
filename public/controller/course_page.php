<?php
require_once("controller/controller_functions.php");
require_once("controller/data_functions.php");

$semester = check_semester();
$course = check_course();
$username = $_SESSION["id"];

$gradeable_addresses =				get_gradeable_addresses($semester, $course);

render("course_page", array(
    "semester"=>                $semester,
    "course"=>                  $course,
    "username"=>                $username,
    "gradeable_addresses"=>		$gradeable_addresses,

    )
);
?>