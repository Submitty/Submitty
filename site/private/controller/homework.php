<?php
require_once("private/controller/helper.php");

//Make model function calls for homework here

$points_received = 30;
$points_possible = 30;

render("homework", array("homework_number"=>$homework_number, "last_homework"=>$last_homework, "points_possible"=>$points_possible, "points_received"=>$points_received));
?>
