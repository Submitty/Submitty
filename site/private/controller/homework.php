<?php
require_once("private/controller/helper.php");

//Make model function calls for homework here

$points_received = 11;
$points_possible = 15;

$homework_summary = array(
    array("title"=>"Points for README.txt", "score"=>3, "points_possible"=>"3"),
    array("title"=>"Points for compilation", "score"=>4, "points_possible"=>4),
    array("title"=>"Test 1", "score"=>4, "points_possible"=>4),
    array("title"=>"Test 2", "score"=>0, "points_possible"=>4),
    array("title"=>"Automatic extra credit(w/o hidden)", "value"=>"+0 points"),
    array("title"=>"Automatic grading total", "score"=>11, "points_possible"=>15)
);

$homework_tests = array(
    array("title"=>"Test 1", "score"=>4, "points_possible"=>4),
    array("title"=>"Test 2", "score"=>0, "points_possible"=>4)
);


render("homework", array("homework_number"=>$homework_number, "last_homework"=>$last_homework, "points_possible"=>$points_possible, "points_received"=>$points_received, "homework_summary"=>$homework_summary, "homework_tests"=>$homework_tests));
?>
