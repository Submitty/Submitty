<?php

require_once("controller/data_functions.php");
$username = $_SESSION["id"];

//Upload the stuff

if (isset($_GET["course"])) {
    $tmp = htmlspecialchars($_GET["course"]);
    if (!is_valid_course($tmp)) {
        $course = "UPLOAD_NONE_B".$tmp;
        // FIXME, displaymesssage does not exist
        header("Location: index.php?page=displaymessage&course=".$course);
    }
    else {
        $course = $tmp;
    }
}
else {
    // FIXME: need a better default
    $course = "default_course";
    // maybe should exit?
    //exit(1);
}

if (isset($_GET["semester"])) {
    $tmp = htmlspecialchars($_GET["semester"]);
    if (!is_valid_semester($tmp)) {
        //$semester = "UPLOAD_NONE_B".$tmp;
        // FIXME, displaymesssage does not exist
        header("Location: index.php?page=displaymessage&semester=".$semester);
    } else {
        $semester = $tmp;
    }
}
else {
    // FIXME: need a better default
    $semester = "default_semester";
    // maybe should exit?
    //exit(1);
}

if (isset($_GET["assignment_id"])) {
    $assignment_id = htmlspecialchars($_GET["assignment_id"]);

    // FIXME: add a validity check for assignment id

}
else {
    // FIXME: maybe do something better
    $_SESSION["status"] = "Invalid assignment id";
    header("Location: index.php?page=displaymessage&semester=".$semester."&course=".$course."&assignment_id=".$assignment_id);
}

if (isset($_GET["assignment_version"])) {
    $tmp = htmlspecialchars($_GET["assignment_version"]);
    if (!is_valid_assignment_version($username, $semester, $course, $assignment_id, $tmp)) {
        $_SESSION["status"] = "Invalid assignment version";
        header("Location: index.php?page=displaymessage&semester=".$semester."&course=".$course."&assignment_id=".$assignment_id);
    } else {
        $assignment_version = $tmp;
    }
}
else {
    // FIXME: maybe do something better
    $_SESSION["status"] = "Invalid assignment version";
    header("Location: index.php?page=displaymessage&semester=".$semester."&course=".$course."&assignment_id=".$assignment_id);
}

if (isset($_GET["file_name"])) {
    $tmp = htmlspecialchars($_GET["file_name"]);
    if ($tmp != "all" && !is_valid_file_name($username, $semester, $course, $assignment_id, $assignment_version, $tmp)) {
        $_SESSION["status"] = "Invalid file name";
        header("Location: index.php?page=displaymessage&semester=".$semester."&course=".$course."&assignment_id=".$assignment_id);
    } else {
        $file_name = $tmp;
    }
}
else {
    // FIXME: maybe do something better
    $_SESSION["status"] = "Invalid file name";
    header("Location: index.php?page=displaymessage&semester=".$semester."&course=".$course."&assignment_id=".$assignment_id);
}


if ($file_name == "all"){
    header("Content-Type: archive/zip");

    header('Content-Disposition: attachment; filename="assignment_' .  $assignment_id."_submission_". $assignment_version . ".zip".'"');
    $submitted_files = get_submitted_files($username, $semester, $course, $assignment_id, $assignment_version);

    // $tmp_zip = tempnam ("tmp", "tempname") . ".zip";
    // $zip = new ZipArchive;
    // $zip->open($tmp_zip, ZipArchive::CREATE);

    $filespec = "";
    chdir( get_all_files($username, $semester, $course, $assignment_id, $assignment_version) );
    foreach($submitted_files as $file){
        $filespec .= " '".$file["name"]."' ";
        //     $zip->addFile($file["name"]);

    }
    // $zip->close();

    // echo "<body><p>".$filespec."</p></body>";
    $stream = popen( "/usr/bin/zip -q - ".$filespec, "r" );

    if( $stream )
    {
        fpassthru( $stream );
        fclose( $stream );
    }

    // $filesize = filesize($tmp_zip);
    // header('Content-Length: ' . filesize($zipfilename));
    // readfile($tmp_zip);

}
else{
    header("Pragma: public");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Content-Type: application/octet-stream");
    header('Content-Disposition: attachment; filename="' .  $file_name . '"');

    readfile(get_file($username, $semester, $course, $assignment_id, $assignment_version, $file_name));
}
// echo "<body><p>".get_file($username, $semester, $course, $assignment_id, $assignment_version, $tmp)."</p></body>";
//Go back to homework page
// header("Location: index.php?page=displaymessage&semester=".$semester."&course=".$course."&assignment_id=".$assignment_id);

?>
