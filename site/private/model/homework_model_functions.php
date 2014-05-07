<?php
function upload_homework($username, $assignment_id, $homework_file) {
    if ($username !== $_SESSION["id"]) {//Validate the id
        echo "Something really got screwed up with usernames and session ids"; 
        return array("error"=>"Something really got screwed up with usernames and session ids");
    }
    if (!can_edit_homework($username, $assignment_id)) {//Made sure the user can upload to this homework
        return array("error"=>"This homework cannot be changed");
    }
    //VALIDATE HOMEWORK CAN BE UPLOADED HERE
    //ex: homework number, due date, late days
    $path_front = "../../CSCI1200";//This is for Prof Cutler to edit

    $max_size = 50000;//CHANGE THIS TO GET VALUE FROM APPROPRIATE FILE
    $allowed = array("zip");
    $filename = explode(".", $homework_file["name"]);
    $extension = end($filename);

    $upload_path = $path_front."/submissions/".$assignment_id."/".$username;//Upload path
    
    if (!($homework_file["type"] === "application/zip")) {//Make sure the file is a zip file
        echo "Incorrect file upload type.  Not a zip, got ".htmlspecialchars($homework_file["type"]);
        return array("error"=>"Incorrect file upload type.  Not a zip, got ".htmlspecialchars($homework_file["type"]));
    }

    if (!file_exists($path_front."/".$assignment_id)) {//Made sure the assignment exists as a folder
        echo "Error, ".$assignment_id." does not exist in file structure";
        return array("error"=>$assignment_id." does not exist in file structure");
    }
    if (!file_exists($upload_path)) {//Make sure the user has a file already
        echo "Error, Person does not exist in file structure";
        return array("error"=>"Person does not exist in file structure (Could not find them in the homework ".$homework_number." folder)");
    }
    
    $i = 1;
    while (file_exists($upload_path."/".$i)) {//Find the next homework version number
        //Replace with symlink?
        $i++;
    }

    if (!mkdir($upload_path."/".$i, 0777, false)) {//Create a new directory corresponding to a new version number
        //chmod 0777, recursive false
        echo "Error, failed to make folder ".$upload_path."/".$i;
        return array("error"=>"failed to make folder ".$upload_path."/".$i);
    }

    if (!move_uploaded_file($homework_file["tmp_name"], $upload_path."/".$i."/".$homework_file["name"])) {//Move the zip file to the correct directory
        echo "Error failed to move uploaded file from ".$homework_file["tmp_name"]." to ". $upload_path."/".$i."/".$homework_file["name"];
        return array ("error"=>"failed to move uploaded file from ".$homework_file["tmp_name"]." to ". $upload_path."/".$i."/".$homework_file["name"]);
    }
    if (!file_exists($upload_path."/".$i."/".$homework_file["name"])) {//Check to make sure the file got placed correctly
        echo "Hmm, ".$homework_file["tmp_name"]." didn't move to ".$upload_path."/".$i."/".$homework_file["name"];
        return array("error"=>"Hmm, ".$homework_file["tmp_name"]." didn't move to ".$upload_path."/".$i."/".$homework_file["name"]);
    }
    return array("success"=>"File uploaded successfully");
}

function can_edit_homework($username, $assignment_id) {
    return true;
}
function most_recent_assignment_id($username) {
    return "Homework1";
}

function most_recent_assignment_version($username, $assignment_id) {
    $path_front = "../../CSCI1200";
    $path = $path_front."/submissions/".$assignment_id."/".$username;
    $i = 1;
    if (!(file_exists($upload_path."/0"))) {
        return -1;
    }
    while (file_exists($upload_path."/".$i)) {
        $i++;
    }
    return $i - 1;

}

function name_for_assignment_id($username, $assignment_id) {
    $path_front = "../../CSCI1200";
    $file = $path_front."/results/class.json";
    //Get json and parse for assignment_name
    $json = json_decode(file_get_contents($file),true);
    $assignments = $json["assignments"];
    foreach ($assignments as $one) {
        if ($one["assignment_id"] == $assignment_id) {
            return $one["assignment_name"];
        }
    }
    return "";//FIX THIS
}

function is_valid_assignment($username, $assignment_id) {
    $path_front = "upload_testing";
    $upload_path = $path_front."/".$assignment_id."/".$username;//Upload path

    return file_exists($upload_path);
}

function is_valid_assignment_version($username, $assignment_id, $assignment_version) {
    $path_front = "upload_testing";
    $upload_path = $path_front."/".$assignment_id."/".$username."/".$assignment_version;
    return file_exists($upload_path);
}


function get_assignments($username) {
    return array(
        array("assignment_id"=>"Homework1", "assignment_name"=>"Homework 1"),
        array("assignment_id"=>"Lab1", "assignment_name"=>"Lab 1"),
        array("assignment_id"=>"Homework2", "assignment_name"=>"Homework 2")
    );
}

function TA_grade($username, $assignment_id) {
    return false;
}

function max_assignment_version($username, $assignment_id) {
    $path_front = "upload_testing";
    $upload_path = $path_front."/".$assignment_id."/".$username;
    $i = 1;
    if (!(file_exists($upload_path."/0"))) {
        return -1;
    }
    while (file_exists($upload_path."/".$i)) {
        $i++;
    }
    return $i - 1;
}

function max_submissions_for_assignment($username, $assignment_id) {
    return 20;
}


