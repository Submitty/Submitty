<?php

require_once("./private/controller/fileio.php");

echo "Server Operation Testing<br/>";
echo "<form action='' method='post' enctype='multipart/form-data'> <label for='file'>Filename:</label> <input type='file' name='file' id='file'><br> <input type='submit' name='submit' value='Submit'> </form>";

if (isset($_GET["do"])){
	$do = $_GET["do"];
	echo "Attempting to do $do <br/>";
}else{
	$do = "nothing";
	echo "Server does not know what to do <br/>";
}

// CREATE TREE FOR ALL ASSIGNMENTS
function create_root(){
	global $path_root;

	copyDir("./base_CSCI1200","$path_root");
}

// RESET TREE FOR ALL ASSIGNMENTS
function reset_root(){
	global $path_root;
	try{
		deleteDir($path_root);
	}catch(Exception $exception){

	}

	create_root();
}

// CREATE ASSIGNMENT DIRECTORY (INSTRUCTOR)
function create_assignment($assignment_name){
	global $path_templates;
	global $path_assignments;

	copyDir("$path_templates/assignment_config", "$path_assignments/$assignment_name");
}

// CREATING STUDENT ASSIGNMENT DIRECTORY
function create_student_directory($assignment_name, $student_name){
	global $path_assignments;
	global $path_templates;

	$student_dir = "$path_assignments/$assignment_name/$student_name";
	copyDir("$path_templates/student_submissions", "$student_dir");
}


// FOR HOMEWORK SUBMISSION
function submit($assignment_name, $student_name){
	global $path_assignments;

	$student_dir = "$path_assignments/$assignment_name/$student_name";

	if (!isset($_FILES["file"])){
		exit;
	}

	if (!file_exists($student_dir)){
		create_student_directory($assignment_name, $student_name);
	}

	$tmp = explode(".", $_FILES["file"]["name"]);
	$file_extension = end($tmp);

	if ($file_extension != "zip"){
		echo "Incorrect File Type: $file_extension <br/>";
		exit;
	}

	// 100 kb file cap
	if ($_FILES["file"]["size"] > 100000){
		echo "File too large <br/>";
		exit;
	}

	if ($_FILES["file"]["error"] > 0) {
		echo "Error: " . $_FILES["file"]["error"] . "<br/>";
	}

	echo "FILE STATISTICS :<br/>";
	echo "Upload: " . $_FILES["file"]["name"] . "<br/>";
	echo "Type: " . $_FILES["file"]["type"] . "<br/>";
	echo "Size: " . ($_FILES["file"]["size"] / 1024) . " kB<br/>";
	echo "Stored in: " . $_FILES["file"]["tmp_name"] . "<br/><br/>";

	$zip = new ZipArchive;
	$res = $zip->open($_FILES["file"]["tmp_name"]);
	if ($res === TRUE) {
	  $zip->extractTo("$student_dir/FILES");
	  $zip->close();
	} else {
	  echo 'Error Reading Zip <br/>';
	}



}

if ($do == "resetroot"){
	reset_root();
}else if ($do == "createroot"){
	create_root();
}else if ($do == "createassignment"){
	create_assignment($_GET["assignmentname"]);
}else if ($do == "createstudentdirectory"){
	create_student_directory($_GET["assignmentname"], $_GET["student"]);
}else if ($do == "submit"){
	submit($_GET["assignmentname"], $_GET["student"]);
}
?>