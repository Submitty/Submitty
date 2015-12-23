#!/usr/bin/env php

<?php

// Expected:  $_GET should have course ID
// Expected:  $_FILE should have uploaded file

// Would-be-nice-to-have:  $_GET data sent by POST

// To-Do: Check file so that it is either XLSX or CSV
// IF XLSX: convert to CSV using xlsx2csv command provided by canonical.
// WHEN CSV: provide file name as URL paramter.  Encrypt parameter?  Another good case for POST session instead of GET.
// Redirect to account/submit/admin_classlist.php for CSV processing.


if (function_exists("popen") === false) {
	die ("CGI error prevents xslx to csv conversion.  Please contact sysadmin.");
}

header("Location: ../account/submit/admin-classlist.php?course={$_GET['course']}&csv={$VAR}");

?>
