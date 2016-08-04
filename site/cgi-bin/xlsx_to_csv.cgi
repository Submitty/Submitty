#!/usr/bin/env php

<?php
/**
 * This script will convert a given xlsx (excel) file to csv format such
 * that PHP can then natively parse the file using the fgetcsv builtin function.
 * We utilize the xlsx2csv python package which we assume is available on the
 * command line to do this conversion. The parameters to this script are a name
 * for a XLSX file to convert and a name for the resulting CSV file. Deletion of
 * these files should then be handled (if necessary) in the calling script, not
 * in this file. Files handled by this script generally contain data that is
 * regulate by FERPA (20 U.S.C. § 1232g) and thus should be treated in a manner
 * such that unintended access is generally not possible. As such, the URL should
 * not be indicated to the user (ie. through obvious redirection to this script)
 * or by directly encoding it into a javascript ajax call. It should just be called
 * via an internal call of the server, thus not making the url accessible.
 */

function return_error($string) {
    $json = json_encode(array('success' => false, 'error' => true, 'error_message' => $string));
    print "<HTML><BODY>{$json}</BODY></HTML>";
}

function return_success() {
    print "<HTML><BODY>{'success': true, 'error': false}</BODY></HTML>";
}

//Check if popen() is allowed
if (function_exists('popen')) {
    //Check if xlsx2csv file exists
    $proc_handle = popen("command -v xlsx2csv", "r");
    if ((!empty(fread($proc_handle, 1)))) {
        return_error("xlsx2csv not available.");
    }
    pclose($proc_handle);
} else {
    return_error("popen not available");
}

$xlsx_file = basename($_REQUEST['xlsx_file']);
$csv_file = basename($_REQUEST['csv_file']);
 
if (!file_exists("/tmp/".$xlsx_file)) {
    return_error("XLSX spreadsheet not found");
}

//XLSX to CSV conversion
$proc_handle = popen("xlsx2csv -d , -i -s 0 -p '' {$xlsx_file} {$csv_file} 2>&1", "r");

//Validate result after process.
//Check for traceback from xlsx2csv process (no message when process successful).
$tmp = fread($proc_handle, 1);
pclose($proc_handle);
if (empty($tmp)) {
    return_error("Failed converting xlsx to csv.");
}

//Check to make sure _HSS_csv was written.
if (!file_exists(__TMP_CSV_PATH__)) {
    return_error("CSV file not available after the conversion");
}

return_success();
