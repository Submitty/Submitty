<?php
require "../../toolbox/functions.php";

check_administrator();

$fileType = pathinfo($_FILES["classlist"]["name"], PATHINFO_EXTENSION);
if ($fileType != 'csv') {
    die("Only csv files are allowed!");
}

$students = array();
\lib\Database::query("SELECT * FROM students");
foreach(\lib\Database::rows() as $student) {
    $students[$student['student_rcs']] = $student;    
}
\lib\Database::beginTransaction();

if (($handle = fopen($_FILES['classlist']['tmp_name'], "r")) !== FALSE) {
    $contents = explode("\r",fread($handle,filesize($_FILES['classlist']['tmp_name'])));
    unset($contents[0]);
    
    // Go through all students in the CSV file. Either the student is in the database so we have to update his
    // section, the student doesn't exist in the database and is in the CSV so we have to insert the student completely
    // or the student exists in the database, but not the CSV, in which case we have to drop the student (unless
    // student_manual is true)
    foreach ($contents as $content) {
        $content = trim($content);
        if (empty($content)) {
            continue;
        }
        $details = explode(",", $content);
        $rcs = explode("@", $details[14]);
        $rcs = $rcs[0];
        $columns = array("student_rcs", "student_first_name", "student_last_name", "student_section_id", "student_grading_id");
        $values = array($rcs, $details[12],  $details[11], intval($details[6]), 1);
        if (array_key_exists($rcs, $students)) {
            \lib\Database::query("UPDATE students SET student_section_id=? WHERE student_rcs=?", array(intval($details[6]), $rcs));
            unset($students[$rcs]);
        }
        else {
            $db->query("INSERT INTO students (" . (implode(",", $columns)) . ") VALUES (?, ?, ?, ?, ?)", $values);
            \lib\Database::query("INSERT INTO late_days (student_rcs, allowed_lates, since_timestamp) VALUES(?, ?, TIMESTAMP '1970-01-01 00:00:01')", array($rcs, __DEFAULT_LATE_DAYS_STUDENT__));
        }
    }
    fclose($handle);
    
    foreach ($students as $rcs => $student) {
        if (isset($_POST['ignore_manual']) && $_POST['ignore_manual'] == true && $student['student_manual'] == 1) {
            continue;
        }
        $_POST['missing_students'] = intval($_POST['missing_students']);
        if ($_POST['missing_students'] == -2) {
            continue;
        }
        else if ($_POST['missing_students'] == -1) {
            \lib\Database::query("DELETE FROM students WHERE student_rcs=?", array($rcs));
        }
        else {
            \lib\Database::query("UPDATE students SET student_section_id=? WHERE student_rcs=?", array($_POST['missing_students'], $rcs));
        }
    }
    
    \lib\Database::commit();
}
else {
    die("Could not properly upload the file");
}

header("Location: {$BASE_URL}/account/admin-classlist.php?course={$_GET['course']}&update=1");
?>