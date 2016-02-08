#!/usr/bin/env php

<?php

/**
 * FILE: cgi-bin/xlsx_to_csv.cgi
 *
 * Due to security policy enacted by SuPHP, use of xslx2csv is disallowed in
 * this script, but is permitted in a separate CGI script.
 *
 * This script will work with account/submit/admin-classlist.php to convert an
 * uploaded XLSX file to CSV.  Pertininent info must be passed to this script
 * via URL parameters, which cannot be considered secure information.
 *
 * IMPORTANT: Expected data uploads contain data regulated by
 * FERPA (20 U.S.C. ¤ 1232g)
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

/* ERROR codes are bitwise set, and multiple can be set at the same time.
 *
 * Validate before CSV conversion
 * 0x00000001: popen() function isn't defined (maybe mistakenly disallowed in php.ini?)
 * 0x00000002: xlsx2csv command doesn't exist.  apt-get install xslx2csv
 * 0x00000004: HTML tags detected in URL parameters (possible XSS hacking attempt?)
 * 0x00000008: Cannot determine what course is being admin'd from URL paramters.
 * 0x00000010: Cannot find _HSS_xlsx file needed to do conversion to csv.
 *
 * Validate after CSV conversion
 * 0x00010000: xlsx2csv process issued traceback.  Error during csv conversion.
 * 0x00020000: _HSS_csv does not exist / was not written.
 */ 

//Validate before process.  Error codes are bitwise set.
$err_code = 0;

//Check if popen() is allowed
if (function_exists('popen')) {

	//Check if xlsx2csv file exists
	$proc_handle = popen("command -v xlsx2csv", "r");
	$err_code |= (!empty(fread($proc_handle, 1))) ? 0 : 0x00000002;
	pclose($proc_handle);
} else {
	$err_code |= 0x00000001;
}

//Check if URL parameters exist (at least "course={code}" is expected)
if (isset($_SERVER['QUERY_STRING'])) {
	
	//Check for HTML tags as a deterrent against possible XSS attack.
	$query_string = filter_var($_SERVER['QUERY_STRING'], FILTER_SANITIZE_STRING);
	$err_code |= ($query_string === $_SERVER['QUERY_STRING']) ? 0 : 0x00000004;

	//Check to make sure course code is provided.
	$err_code |= (strpos($query_string, 'course=') !== false) ? 0 : 0x00000008;
} else {
	$err_code |= 0x00000008;
}
 
//Check that XLSX file was uploaded.
$err_code |= (file_exists(__TMP_XLSX_PATH__)) ? 0 : 0x00000010;

//Print error and die if any checks failed.
die_on_error($err_code);

//XLSX to CSV conversion
$proc_handle = popen("xlsx2csv -d , -i -s 0 -p '' " . __TMP_XLSX_PATH__ . " " . __TMP_CSV_PATH__ . " 2>&1", "r");

//Validate result after process.  Error codes are bitwise set.
$err_code = 0;

//Check for traceback from xlsx2csv process (no message when process successful).
$err_code |= (empty(fread($proc_handle, 1))) ? 0 : 0x00010000;
pclose($proc_handle);

//Check to make sure _HSS_csv was written.
$err_code |= (file_exists(__TMP_CSV_PATH__)) ? 0 : 0x00020000;

//Print error and die if any checks failed.
die_on_error($err_code);

//CSV conversion all done.  Return to HWgrading.
print '<HTML><META HTTP-EQUIV="refresh" CONTENT="0;URL=' . __BASE_URL__ . '/account/submit/admin-classlist.php?' . $query_string . '&xlsx2csv=1"></HTML>';

function die_on_error($err_code) {
	if (!empty($err_code)) {
		die ("xlsx_to_csv error " . dechex($err_code) . ".  Please contact sysadmin.");
	}
}
?>
