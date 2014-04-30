<?php
require_once("private/controller/helper.php");

//Make model function calls for homework here

$points_received = 11;
$points_possible = 15;

$homework_summary = array("Points for README.txt"=>"3/3", "Points for compilation"=>"4/4", "Test 1"=>"4/4", "Test 2"=>"0/4", "Automatic extra credit(w/o hidden)"=>"+0 points", "Automatic grading total"=>"11/15");


render("homework", array("homework_number"=>$homework_number, "last_homework"=>$last_homework, "points_possible"=>$points_possible, "points_received"=>$points_received, "homework_summary"=>$homework_summary));
?>
