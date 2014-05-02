<?php

//Make model function calls for grades here
$section = 1;
$username = "sengs";
$last = "Seng";
$first = "Samuel";
$overall = 40.0;
$lab = 10.0;
$homework = 20.0;
$tests = 10.0;
$final = "";
//User overall data
$user_data = array("section"=>$section, "username"=>$username, "last"=>$last, "first"=>$first, "overall"=>$overall, "lab"=>$lab, "homework"=>$homework, "tests"=>$tests, "final"=>$final);
//Overall scales
$perfect = array("section"=>"", "username"=>"Perfect", "last"=>"", "first"=>"", "overall"=>70.9, "lab"=>20, "homework"=>20, "tests"=>20, "final"=>"");
$a = array("section"=>"", "username"=>"Lowest A-", "last"=>"approximate", "first"=>"", "overall"=>70.9, "lab"=>20, "homework"=>20, "tests"=>20, "final"=>"");
$b = array("section"=>"", "username"=>"Lowest B-", "last"=>"approximate", "first"=>"", "overall"=>70.9, "lab"=>20, "homework"=>20, "tests"=>20, "final"=>"");
$c = array("section"=>"", "username"=>"Lowest C-", "last"=>"approximate", "first"=>"", "overall"=>70.9, "lab"=>20, "homework"=>20, "tests"=>20, "final"=>"");
$d = array("section"=>"", "username"=>"Lowest D-", "last"=>"approximate", "first"=>"", "overall"=>70.9, "lab"=>20, "homework"=>20, "tests"=>20, "final"=>"");
$scale = array($perfect, $a, $b, $c, $d);
//User Lab data
$num_labs = 12;
$labs = array(3.0,3.0,3.0,3.0,3.0);
//Lab scales
$l1 = array(3.0,3.0,3.0,3.0,3.0);
$l2 = array(2.5,2.5,2.5,2.5,2.5);
$l3 = array(2.0,2.0,2.0,2.0,2.0);
$l4 = array(1.5,1.5,1.5,1.5,1.5);
$lab_scales = array($l1,$l2,$l3,$l4);
//User homework data
$num_homeworks = 10;
$homeworks = array(40,40,40,40,40);
//Homework scales
$l1 = array(50,50,50,50,50);
$l2 = array(45,45,45,45,45);
$l3 = array(40,40,40,40,40);
$l4 = array(35,35,35,35,35);
$homework_scales = array($l1,$l2,$l3,$l4);
//User Test data
$num_tests = 3;
$tests = array(90,90,90);
//Test scales
$t1 = array(100,100,100);
$t2 = array(80,80,80);
$t3 = array(60,60,60);
$t4 = array(40,40,40);
$test_scales = array($t1,$t2,$t3,$t4);


render("grades", array(
    "user_data"=>$user_data,
    "scale"=>$scale,
    "num_labs"=>$num_labs,
    "labs"=>$labs,
    "lab_scales"=>$lab_scales,
    "num_homeworks"=>$num_homeworks,
    "homeworks"=>$homeworks,
    "homework_scales"=>$homework_scales,
    "num_tests"=>$num_tests,
    "tests"=>$tests,
    "test_scales"=>$test_scales
    )    
);
?>
