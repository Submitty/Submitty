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

$csvFile = null;

//Verify:  Is this a new upload or a CSV converted from XLSX?
if (isset($_GET['xlsx2csv']) && $_GET['xlsx2csv'] == 1) {

	//CSV converted from XLSX
	$csvFile = __TMP_CSV_PATH__;

	//Callback to purge temporary files that contain data restricted by FERPA.
	//The temp files will be purged when this script ends, FOR ANY REASON.
	register_shutdown_function(
		function() {
			if (file_exists(__TMP_XLSX_PATH__)) {
				unlink(__TMP_XLSX_PATH__);
			}

			if (file_exists(__TMP_CSV_PATH__)) {
				unlink(__TMP_CSV_PATH__);
			}
		}
	);
} else {

	//New upload.
	//Preserve POST data so it isn't lost should CGI script be called.
	$_SESSION['post'] = $_POST;

	//Verify that upload is a true CSV or XLSX file (check file extension and MIME type)
	$fileType = pathinfo($_FILES['classlist']['name'], PATHINFO_EXTENSION);
	$fh = finfo_open(FILEINFO_MIME_TYPE);
	$mimeType = finfo_file($fh, $_FILES['classlist']['tmp_name']);
	finfo_close($fh); //No longer needed once mime type is determined.

	if ($fileType == 'xlsx' && $mimeType == 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {

		//XLSX detected, need to do conversion.  Call up CGI script.
		if (copy($_FILES['classlist']['tmp_name'], __TMP_XLSX_PATH__)) {
			header("Location: {$BASE_URL}/cgi-bin/xlsx_to_csv.cgi?course={$_GET['course']}");
		} else {
			die("Error isolating uploaded XLSX.  Please contact tech support.");
		}
	} else if (($fileType == 'csv' && $mimeType == 'text/plain')) {

		//CSV detected.  No conversion needed.
		$csvFile = $_FILES['classlist']['tmp_name'];
	} else {

		//Neither XLSX or CSV detected.  Good bye...
		die("Only xlsx or csv files are allowed!");
	}
}

if (!isset($_SESSION['post']['csrf_token']) || $_SESSION['post']['csrf_token'] !== $_SESSION['csrf']) {
	die("invalid csrf token");
}

// Get CSV ini config
$csvFieldsINI = parse_ini_file("../../toolbox/configs/student_csv_fields.ini", false, INI_SCANNER_RAW);
if ($csvFieldsINI === false) {
	die("Cannot read student list CSV configuration file.  Please contact your sysadmin to run setcsvfields tool.");
}

// Read file into row-by-row array.  Returns false on failure.
$contents = file($csvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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
	                 'student_rcs'        => explode("@", $details[$csvFieldsINI['student_email']])[0],
	                 'student_section'    => intval($details[$csvFieldsINI['student_section']]) );

	//Validate massaged data.  First, make sure we're working on the most recent entry (should be the "end" element).
	$val = end($rows);

	//First name must be alpha characters, white-space, or certain punctuation.
	$error_message .= (preg_match("~^[a-zA-Z.'`\- ]+$~", $val['student_first_name'])) ? "" : "Error in student first name column, row #{$row_being_processed}: {$val['student_first_name']}<br>";

	//Last name must be alpha characters white-space, or certain punctuation.
	$error_message .= (preg_match("~^[a-zA-Z.'`\- ]+$~", $val['student_last_name'])) ? "" : "Error in student last name column, row #{$row_being_processed}: {$val['student_last_name']}<br>";

	//Student section must be greater than zero (intval($str) returns zero when $str is not integer)
	$error_message .= ($val['student_section'] > 0) ? "" : "Error in student section column, row #{$row_being_processed}: {$val['student_section']}<br>";

	//No check on rcs (computing login ID) -- different Univeristies have different formats.
}

//Display any accumulated errors.  Quit on errors, otherwise continue.
if (empty($error_message) === false) {
	die($error_message . "Contact your sysadmin.");
}

//Collect existing student list, group data by rcs
$students = array();
\lib\Database::query("SELECT * FROM students");
foreach(\lib\Database::rows() as $student) {
    $students[$student['student_rcs']] = $student;
}

$inserted = 0;
$updated = 0;

// Go through all students in the CSV file. Either the student is in the database so we have to update his
// section, the student doesn't exist in the database and is in the CSV so we have to insert the student completely
// or the student exists in the database, but not the CSV, in which case we have to drop the student (unless
// student_manual is true)
\lib\Database::beginTransaction();
foreach ($rows as $row) {
	$columns = array("student_rcs", "student_first_name", "student_last_name", "student_section_id", "student_grading_id");
	$values = array($row['student_rcs'], $row['student_first_name'], $row['student_last_name'], $row['student_section'], 1);
	$rcs = $row['student_rcs'];
	if (array_key_exists($rcs, $students)) {
		if (isset($_SESSION['post']['ignore_manual_1']) && $_SESSION['post']['ignore_manual_1'] == true && $students[$rcs]['student_manual'] == 1) {
			continue;
		}
		\lib\Database::query("UPDATE students SET student_section_id=? WHERE student_rcs=?", array($row['student_section'], $rcs));
        $updated++;
		unset($students[$rcs]);
	}
	else {
		$db->query("INSERT INTO students (" . (implode(",", $columns)) . ") VALUES (?, ?, ?, ?, ?)", $values);
		\lib\Database::query("INSERT INTO late_days (student_rcs, allowed_lates, since_timestamp) VALUES(?, ?, TIMESTAMP '1970-01-01 00:00:01')", array($rcs, __DEFAULT_LATE_DAYS_STUDENT__));
        $inserted++;
	}
}

$moved = 0;
$deleted = 0;
foreach ($students as $rcs => $student) {
	if (isset($_SESSION['post']['ignore_manual_2']) && $_SESSION['post']['ignore_manual_2'] == true && $student['student_manual'] == 1) {
		continue;
	}
	$_SESSION['post']['missing_students'] = intval($_SESSION['post']['missing_students']);
	if ($_SESSION['post']['missing_students'] == -2) {
		continue;
	}
	else if ($_SESSION['post']['missing_students'] == -1) {
		\lib\Database::query("DELETE FROM students WHERE student_rcs=?", array($rcs));
        $deleted++;
	}
	else {
		\lib\Database::query("UPDATE students SET student_section_id=? WHERE student_rcs=?", array($_SESSION['post']['missing_students'], $rcs));
        $moved++;
	}
}

unset($_SESSION['post']);

\lib\Database::commit();
header("Location: {$BASE_URL}/account/admin-classlist.php?course={$_GET['course']}&update=1&inserted={$inserted}&updated={$updated}&deleted={$deleted}&moved={$moved}");
