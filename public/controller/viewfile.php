<?php
require_once("controller/data_functions.php");
require_once("controller/controller_functions.php");

$username=get_username();
$course=check_course();
$semester=check_semester();
$class_config=check_class_config($semester,$course);
$dev_team=$class_config["dev_team"];
$assignment_id=check_assignment_id($class_config);
$assignment_version=get_assignment_version($username,$semester, $course, $assignment_id);
$file_name=check_file_name($semester, $course, $assignment_id, $assignment_version);

$username = $_SESSION["id"];

if (isset($class_config["download_files"])){
    $download_files = $class_config["download_files"];
}
else{
    $download_files = false;
}
if (isset($class_config["download_readme"])){
    $download_readme = $class_config["download_readme"];
}
else{
    $download_readme = false;
}

if ($download_files == true){
    if ($file_name == "all"){
        header("Content-Type: archive/zip");
        header('Content-Disposition: attachment; filename="assignment_' .  $assignment_id."_submission_". $assignment_version . ".zip".'"');
        $submitted_files = get_submitted_files($username, $semester, $course, $assignment_id, $assignment_version);
        $filespec = "";
        chdir( get_all_files($username, $semester, $course, $assignment_id, $assignment_version) );
        foreach($submitted_files as $file){
            $filespec .= " '".$file["name"]."' ";
        }
        $stream = popen( "/usr/bin/zip -q - ".$filespec, "r" );

        if( $stream )
        {
            fpassthru( $stream );
            fclose( $stream );
        }

    }
    else{
        header("Pragma: public");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Content-Type: application/octet-stream");
        header('Content-Disposition: inline; filename="' .  $file_name . '"');

        readfile(get_file($username, $semester, $course, $assignment_id, $assignment_version, $file_name));
    }
}

else if ($download_readme == true){
    if (strtolower($file_name) == "readme.txt"){
        header("Pragma: public");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Content-Type: text/plain");
        header('Content-Disposition: inline; filename="' .  $file_name . '"');

        readfile(get_file($username, $semester, $course, $assignment_id, $assignment_version, $file_name));
    }
    else{
        $_SESSION["status"] = "Invalid filename download";
        header("Location: index.php?page=displaymessage&semester=".$semester."&course=".$course."&assignment_id=".$assignment_id);
    }
}

else{
    $_SESSION["status"] = "Downloads disabled";
    header("Location: index.php?page=displaymessage&semester=".$semester."&course=".$course."&assignment_id=".$assignment_id);
}

// echo "<body><p>".get_file($username, $semester, $course, $assignment_id, $assignment_version, $tmp)."</p></body>";
//Go back to homework page
// header("Location: index.php?page=displaymessage&semester=".$semester."&course=".$course."&assignment_id=".$assignment_id);

?>
