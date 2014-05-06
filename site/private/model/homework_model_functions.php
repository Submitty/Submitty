<?php
$path_front = "upload_testing";
function upload_homework($username, $homework_number, $homework_file) {
    //VALIDATE HOMEWORK CAN BE UPLOADED HERE
    //ex: homework number, due date, late days
    $path_front = "upload_testing";

    $max_size = 50000;//CHANGE THIS TO GET VALUE FROM APPROPRIATE FILE
    $allowed = array("zip");
    $filename = explode(".", $homework_file["name"]);
    $extension = end($filename);

    $upload_path = $path_front."/HW".$homework_number."/".$username;
    
    if (!($homework_file["type"] === "application/zip")) {//Make sure the file is a zip file
        echo "Incorrect file upload type.  Not a zip, got ".htmlspecialchars($homework_file["type"]);
        return array("error"=>"Incorrect file upload type.  Not a zip, got ".htmlspecialchars($homework_file["type"]));
    }

    if (!file_exists($path_front."/HW".$homework_number)) {
        echo "Error, HW".$homework_number." does not exist in file structure";
        return array("error"=>"HW".$homework_number." does not exist in file structure");
    }
    if (!file_exists($upload_path)) {//Make sure the user has a file already
        echo "Error, Person does not exist in file structure";
        return array("error"=>"Person does not exist in file structure (Could not find them in the homework ".$homework_number." folder)");
    }
    
    $i = 0;//We're computer scientists, we start from 0
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

function change_version_number() {
}

function get_homework() {
}
