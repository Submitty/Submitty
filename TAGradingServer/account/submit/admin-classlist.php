<?php
require "../../toolbox/functions.php";


$fileType = pathinfo($_FILES["classlist"]["name"], PATHINFO_EXTENSION);
if ($fileType != 'csv') {
    die("Only csv files are allowed!");
}

$row = 1;

// TODO: Fix this to not use fopen (#62)
if (($handle = fopen($_FILES['classlist']['tmp_name'], "r")) !== FALSE) {
    $contents = explode("\r",fread($handle,filesize($_FILES['classlist']['tmp_name'])));

    unset($contents[0]);
    foreach ($contents as $content) {
        $details = explode(",", $content);
        //echo $details[11].", ".$details[12]." (".$details[14]." - ".intval($details[6]).")<br />";
        $rcs = explode("@", $details[14]);
        $rcs = $rcs[0];
        $details[11] = addslashes($details[11]);
        $details[12] = addslashes($details[12]);
        $columns = array("student_rcs", "student_late_warning", "student_allowed_lates", "student_first_name",
            "student_last_name", "student_section_id", "student_grading_id");
        $values = array("'{$rcs}'", 0, 3, "'{$details[12]}'", "'{$details[11]}'", intval($details[6]), 1);
        $db->query("INSERT INTO students (".(implode(",", $columns)).") VALUES (".(implode(",", $values)).")", array());
    }
    fclose($handle);
}
else {
    die("Could not properly upload the file");
}

header("Location: {$BASE_URL}/account/admin-classlist.php?course={$_GET['course']}");
?>