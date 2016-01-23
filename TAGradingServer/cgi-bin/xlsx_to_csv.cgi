#!/usr/bin/env php

<?php

/* SuPHP security policy prohibits calling/executing an external processes, but
 * this is permitted in PHP processed as CGI.  An external process is necessary
 * to conduct an XLSX to CSV data conversion. 
 */

define('TMP_XLSX_PATH', '/tmp/_HSS_xlsx');
define('TMP_CSV_PATH',  '/tmp/_HSS_csv');

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

	//Check is xlsx2csv exists
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
$err_code |= (file_exists(TMP_XLSX_PATH)) ? 0 : 0x00000010;

//Print error and die if any checks failed.
die_on_error($err_code);

//XLSX to CSV conversion
$proc_handle = popen("xlsx2csv -d , -i -s 0 -p '' " . TMP_XLSX_PATH . " " . TMP_CSV_PATH . " 2>&1", "r");

//Validate result after process.  Error codes are bitwise set.
$err_code = 0;

//Check for traceback from xlsx2csv process (no message when process successful).
$err_code |= (empty(fread($proc_handle, 1))) ? 0 : 0x00010000;
pclose($proc_handle);

//Check to make sure _HSS_csv was written
$err_code |= (file_exists(TMP_CSV_PATH)) ? 0 : 0x00020000;

//Print error and die if any checks failed.
die_on_error($err_code);

//CSV conversion all done.  Return to HWgrading.
print '<HTML><META HTTP-EQUIV="refresh" CONTENT="1;URL=https://192.168.56.103/account/submit/admin-classlist.php?' . $query_string . '&xlsx2csv=1"></HTML>';
//fprintf(STDOUT, "Location: https://192.168.56.103/account/submit/admin-classlist.php?%s&xlsx2csv=1\n\n", $query_string);

function die_on_error($err_code) {
	if (!empty($err_code)) {
		die ("xlsx_to_csv error " . dechex($err_code) . ".  Please contact sysadmin.");
	}
}
?>
