<?php
require "../../toolbox/functions.php";

check_administrator();

/**
 * FILE: account/submit/admin-classlist.php
 *
 * Due to security policy enacted by SuPHP, use of xslx2csv is disallowed in
 * this script, but is permitted in a separate CGI script.
 *
 * This script will work with cgi-bin/xlsx_to_csv.cgi to convert an uploaded
 * XLSX file to CSV.  Pertininent info must be passed to CGI via URL parameters,
 * which cannot be considered secure information.
 *
 * IMPORTANT: Expected data uploads contain data regulated by
 * FERPA (20 U.S.C. § 1232g)
 *
 * As this information must be made secure, existence of this data
 * (e.g. filenames) should not be shared by URL paramaters.  Therefore,
 * filenames will be hardcoded.
 *
 * Path for detected XLSX files            /tmp/_SUBMITTY_xlsx
 * Path for xlsx to CSV converted files    /tmp/_SUBMITTY_csv
 * These should be defined constants in this script and in CGI script.
 *
 * THESE FILES MUST BE IMMEDIATELY PURGED
 * (1) after the information is inserted into DB.  --OR--
 * (2) when the script is abruptly halted due to error condition.  e.g. die()
 *
 * Both conditions can be met as a closure registered with
 * register_shutdown_function()
 */

//Verify that upload is a true CSV or XLSX file (check file extension and MIME type)
$fileType = pathinfo($_FILES['classlist']['name'], PATHINFO_EXTENSION);
$fh = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($fh, $_FILES['classlist']['tmp_name']);
finfo_close($fh); //No longer needed once mime type is determined.

if ($fileType == 'xlsx' && $mimeType == 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
    $csv_file = "/tmp/".\lib\Utils::generateRandomString();
    file_put_contents($csv_file, "");
    chmod($csv_file, 0660);
    $xlsx_file = "/tmp/".\lib\Utils::generateRandomString();
}
else if (($fileType == 'csv' && $mimeType == 'text/plain')) {
    //CSV detected.  No conversion needed.
    $csv_file = $_FILES['classlist']['tmp_name'];
    $xlsx_file = null;
}
else {
    //Neither XLSX or CSV detected.  Good bye...
    die("Only xlsx or csv files are allowed!");
}

register_shutdown_function(
    function() use ($csv_file, $xlsx_file) {
        if (file_exists($xlsx_file)) {
            unlink($xlsx_file);
        }
        
        if (file_exists($csv_file)) {
            unlink($csv_file);
        }
    }
);

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf']) {
    die("invalid csrf token");
}

if ($fileType == 'xlsx' && $mimeType == 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
    //XLSX detected, need to do conversion.  Call up CGI script.
    if (move_uploaded_file($_FILES['classlist']['tmp_name'], $xlsx_file)) {
        $xlsx_tmp = basename($xlsx_file);
        $csv_tmp = basename($csv_file);
        error_reporting(E_ALL);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, __CGI_URL__."/xlsx_to_csv.cgi?xlsx_file={$xlsx_tmp}&csv_file={$csv_tmp}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        if ($output === false) {
            die("Error parsing xlsx to csv.");
        }
        $output = json_decode($output, true);
        if ($output === null) {
            die("Error parsing JSON response: ".json_last_error_msg());
        }
        else if ($output['error'] === true) {
            die("Error parsing xlsx to csv: ".$output['error_message']);
        }
        else if ($output['success'] !== true) {
            var_dump($output);
            print(curl_error($ch));
            die("Error on response on parsing xlsx");
        }
        curl_close($ch);
    }
    else {
        die("Error isolating uploaded XLSX.  Please contact tech support.");
    }
}

// Get CSV ini config
$csvFieldsINI = parse_ini_file("../../toolbox/configs/student_csv_fields.ini", false, INI_SCANNER_RAW);
if ($csvFieldsINI === false) {
	die("Cannot read student list CSV configuration file.  Please contact your sysadmin to run setcsvfields tool.");
}

// Read file into row-by-row array.  Returns false on failure.
$contents = file($csv_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if ($contents === false) {
	die("File was not properly uploaded.  Please contact tech support.");
}

// Massage student CSV file into generalized data array
$error_message = ""; //Used to show accumulated errors during data validation
$row_being_processed = 1; //Offset to account for header (user will cross-reference with his data sheet)
$rows = array();
unset($contents[0]); //header should be thrown away (does not affect cross-reference)

foreach($contents as $content) {
	$row_being_processed++;
	$details = explode(",", trim($content));
	$rows[] = array( 'student_first_name' => $details[$csvFieldsINI['student_first_name']],
	                 'student_last_name'  => $details[$csvFieldsINI['student_last_name']],
	                 'user_id'            => explode("@", $details[$csvFieldsINI['student_email']])[0],
                     'student_email'      =>  $details[$csvFieldsINI['student_email']],
	                 'registration_section'    => intval($details[$csvFieldsINI['student_section']]) );

	//Validate massaged data.  First, make sure we're working on the most recent entry (should be the "end" element).
	$val = end($rows);
    
	//First name must be alpha characters, white-space, or certain punctuation.
	$error_message .= (preg_match("~^[a-zA-Z.'`\- ]+$~", $val['student_first_name'])) ? "" : "Error in student first name column, row #{$row_being_processed}: {$val['student_first_name']}<br>";

	//Last name must be alpha characters white-space, or certain punctuation.
	$error_message .= (preg_match("~^[a-zA-Z.'`\- ]+$~", $val['student_last_name'])) ? "" : "Error in student last name column, row #{$row_being_processed}: {$val['student_last_name']}<br>";

	//Student section must be greater than zero (intval($str) returns zero when $str is not integer)
	$error_message .= ($val['registration_section'] > 0) ? "" : "Error in student section column, row #{$row_being_processed}: {$val['student_section']}<br>";

	//No check on user_id (computing login ID) -- different Univeristies have different formats.
}

//Display any accumulated errors.  Quit on errors, otherwise continue.
if (empty($error_message) === false) {
	die($error_message . "Contact your sysadmin.");
}

//Collect existing student list, group data by user_id
$students = array();
\lib\Database::query("SELECT * FROM users");
foreach(\lib\Database::rows() as $student) {
    $students[$student['user_id']] = $student;
}

$inserted = 0;
$updated = 0;

// Go through all students in the CSV file. Either the student is in the database so we have to update his
// section, the student doesn't exist in the database and is in the CSV so we have to insert the student completely
// or the student exists in the database, but not the CSV, in which case we have to drop the student (unless
// student_manual is true)
\lib\Database::beginTransaction();
foreach ($rows as $row) {
	$columns = array("user_id", "user_firstname", "user_lastname", "user_email", "user_group", "registration_section");
	$values = array($row['user_id'], $row['student_first_name'], $row['student_last_name'], $row['student_email'], 4, $row['registration_section']);
	$user_id = $row['user_id'];
	if (array_key_exists($user_id, $students)) {
	if (isset($_POST['ignore_manual_1']) && $_POST['ignore_manual_1'] == true && $students[$user_id]['manual_registration'] == true) {
			continue;
		}
		\lib\Database::query("UPDATE users SET registration_section=? WHERE user_id=?", array($row['registration_section'], $user_id));
        $updated++;
		unset($students[$user_id]);
	}
	else {
		\lib\Database::query("INSERT INTO users (" . (implode(",", $columns)) . ") VALUES (?, ?, ?, ?, ?, ?)", $values);
		\lib\Database::query("INSERT INTO late_days (user_id, allowed_late_days, since_timestamp) VALUES(?, ?, TIMESTAMP '1970-01-01 00:00:01')", array($user_id, __DEFAULT_LATE_DAYS_STUDENT__));
        $inserted++;
	}
}

$moved = 0;
$deleted = 0;
foreach ($students as $user_id => $student) {
	if (isset($_POST['ignore_manual_2']) && $_POST['ignore_manual_2'] == true && $student['manual_registration'] == true) {
		continue;
	}
	$_POST['missing_students'] = intval($_POST['missing_students']);
	if ($_POST['missing_students'] == -2) {
		continue;
	}
	else if ($_POST['missing_students'] == -1) {
		\lib\Database::query("DELETE FROM users WHERE user_id=?", array($user_id));
        $deleted++;
	}
	else {
		\lib\Database::query("UPDATE users SET registration_section=? WHERE user_id=?", array($_POST['missing_students'], $user_id));
        $moved++;
	}
}

\lib\Database::commit();
header("Location: {$BASE_URL}/account/admin-classlist.php?course={$_GET['course']}&semester={$_GET['semester']}&update=1&inserted={$inserted}&updated={$updated}&deleted={$deleted}&moved={$moved}");
