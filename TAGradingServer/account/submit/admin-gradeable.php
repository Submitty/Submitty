<?php

include "../../toolbox/functions.php";

check_administrator();

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf']) {
    die("invalid csrf token");
}

 $gradeableJSON = $_POST['gradeableJSON'];
 echo "JSON received $gradeableJSON";
 
 # FIX HARCODED PATH
 $fp = fopen('/var/local/hss/courses/f15/'.$_GET['course'].'/config/gradeable.json', 'w');
 if (!$fp){
    die('failed to open file');
 }
 
 #decode for pretty print
 fwrite($fp, json_encode(json_decode($gradeableJSON), JSON_PRETTY_PRINT));
 fclose($fp);
 
 echo "\nwrote to file gradeable.json";
 echo $_GET['course'];
 
 
#header('Location: '.__BASE_URL__.'/account/admin-rubrics.php?course='.$_GET['course']);

?>