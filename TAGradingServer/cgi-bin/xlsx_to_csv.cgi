#!/usr/bin/env php

<?php
//xlsx_to_csv CGI spcript.  Converts uploaded XLSX spreadsheet to CSV
//Created by Peter Bailie, Systems Programmer, RPI Computer Science
//
//Last code update Feb 23, 2016 by PB

/**
 * This script will work with account/submit/admin-classlist.php to convert an
 * uploaded XLSX file to CSV.  Pertininent info must be passed to this script
 * via URL parameters, which cannot be considered secure information.
 *
 * IMPORTANT: Expected data uploads contain data regulated by
 * FERPA (20 U.S.C. § 1232g)
 * 
 * As this information must be made secure, existence of this data
 * (e.g. filenames) should not be shared by URL paramaters.  Therefore,
 * filenames will be hardcoded.
 *
 * Path for detected XLSX files            /tmp/_HSS_xlsx
 * Path for xlsx to CSV converted files    /tmp/_HSS_csv
 * These should be defined constants in this script and in CGI script.
 *
 * THESE FILES MUST BE IMMEDIATELY PURGED
 * (1) after the information is inserted into DB.  --OR--
 * (2) when the script is abruptly halted due to error condition.  e.g. die()
 *
 * Both conditions can be met as a closure registered with
 * register_shutdown_function()
 */
 
require "../toolbox/configs/master.php";

//Ensure protected data (uploaded spreadsheet) is destroyed should this script die on error.
register_shutdown_function(
	function() {
		if (file_exists(__TMP_XLSX_PATH__)) {
			unlink(__TMP_XLSX_PATH__);
		}
	}
);

//Validate before process.  When $err_msgs is empty, no errors found.
$err_msgs = "";

//Check if popen() is allowed
if (function_exists('popen')) {

	//Check if xlsx2csv file exists
	$proc_handle = popen("command -v xlsx2csv", "r");
	$err_msgs .= (!empty(fread($proc_handle, 1))) ? "" : "xlsx2csv not available." . PHP_EOL;
	pclose($proc_handle);
} else {
	$err_msgs .= "popen not available.\n";
}

//Check if URL parameters exist ("course={code}" is expected)
if (isset($_SERVER['QUERY_STRING'])) {
	
	//Check for HTML tags as a deterrent against possible XSS attack.
	$query_string = filter_var($_SERVER['QUERY_STRING'], FILTER_SANITIZE_STRING);
	$err_msgs .= ($query_string === $_SERVER['QUERY_STRING']) ? "" : "improper URL parameter."  . PHP_EOL;
	
	//Check to make sure course code is provided.
	$err_code .= (strpos($query_string, 'course=') !== false) ? "" : "course code missing." . PHP_EOL;
	
	//Check for directory traversal.
	$err_code .= (strpos($query_string, '/') === false)) ? "" : "improper course code." . PHP_EOL;

} else {
	$err_msgs .= "course code not provided." . PHP_EOL;
}
 
//Check that XLSX file was uploaded.
$err_msgs .= (file_exists(__TMP_XLSX_PATH__)) ? "" : "spreadsheet not available." . PHP_EOL;

//Print error and die if any checks failed.
die_on_error($err_msgs);

//XLSX to CSV conversion
$proc_handle = popen("xlsx2csv -d , -i -s 0 -p '' " . __TMP_XLSX_PATH__ . " " . __TMP_CSV_PATH__ . " 2>&1", "r");

//Validate result after process.
//Check for traceback from xlsx2csv process (no message when process successful).
$err_msgs .= (empty(fread($proc_handle, 1))) ? "" : "failed converting xlsx to csv." . PHP_EOL;
pclose($proc_handle);

//Check to make sure _HSS_csv was written.
$err_msgs .= (file_exists(__TMP_CSV_PATH__)) ? "" : "file not available after csv conversion." . PHP_EOL;

//Print error and die if any checks failed.
die_on_error($err_msgs);

//CSV conversion all done, and tmp csv file written.  Return to HWgrading.
print '<HTML><META HTTP-EQUIV="refresh" CONTENT="0;URL=' . __BASE_URL__ . '/account/submit/admin-classlist.php?' . $query_string . '&xlsx2csv=1"></HTML>';
exit(0);

/* -------------------------------------------------------------------------- */

function die_on_error($errors) {
	if (!empty($errors)) {
		die("xlsx_to_csv error(s):" . PHP_EOL . $errors . "Please contact your sysadmin.");
	}
}
?>
