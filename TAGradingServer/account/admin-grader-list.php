<?php
/**
 *  File:    TAGradingServer/account/admin-grader-list.php
 *  Author:  (Jan 13 2017) Peter Bailie, Systems Programmer, RPI Computer Science
 *
 *  This code was requested to be done quickly.  It is based on code from
 *  TAGradingServer/account/admin-classlist.php and
 *  TAGradingserver/account/admin/admin-classlist.php
 */

require "../toolbox/functions.php";

check_administrator();

if ($_SERVER['REQUEST_METHOD'] !== "POST") {
	/**
	 *  DISPLAY FORM
	 */

	include "../header.php";

	$account_subpages_unlock = true;
	echo <<<HTML
<div id="container" style="width:100%; margin-top:40px;">
<div class="modal hide fade in" tabindex="-1" role="dialog" aria-labelledby="graderlist" aria-hidden="false" style="display: block; margin-top:5%; z-index:100;">
<form action="admin-grader-list.php?course={$_GET['course']}&semester={$_GET['semester']}" method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="{$_SESSION['csrf']}" />
<div class="modal-header">
<h3 id="myModalLabel">Upload Grader List</h3>
<p>&nbsp;</p>
<p>
Format your grader data as an .xlsx or .csv file with 5 columns:<br>
<tt>username, LastName, FirstName, email, GraderGroup</tt><br>
</p>
<p>
Where GraderGroup is:<br>
1=Instructor<br>
2=Full Access Grader (graduate teaching assistant)<br>
3=Limited Access Grader (mentor)<br>
4=Student (no grading access)
</p>
</div>

<div class="modal-body" style="padding-top:20px; padding-bottom:20px;">
HTML;

	if (isset($_GET['update']) && $_GET['update'] === '1') {
		$inserted = isset($_GET['inserted']) ? intval($_GET['inserted']) : 0;

		echo <<<HTML
<div style='color:red'>
Graders List Updated: {$inserted} graders added.
</div><br />
HTML;

	}

	echo <<<HTML
Upload Grader List: <input type="file" name="graderlist" id="graderlist" onchange="form.submit();"><br />
</form>
</div>
</div>
HTML;

	include "../footer.php";

} else {
	/**
     *  PROCESS UPLOADED SPREADSHEET
	 */

	if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf']) {
	    die("invalid csrf token");
	}

	//Verify that upload is a true CSV or XLSX file (check file extension and MIME type)
	$fileType = pathinfo($_FILES['graderlist']['name'], PATHINFO_EXTENSION);
	$fh = finfo_open(FILEINFO_MIME_TYPE);
	$mimeType = finfo_file($fh, $_FILES['graderlist']['tmp_name']);
	finfo_close($fh); //No longer needed once mime type is determined.

	if ($fileType === 'xlsx' && $mimeType === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {

		//XLSX detected.  Conversion needed.
		$csv_file = "/tmp/".\lib\Utils::generateRandomString();
		$old_umask = umask(0007);
		file_put_contents($csv_file, "");
		umask($old_umask);
		//@chmod($csv_file, 0660);
		$xlsx_file = "/tmp/".\lib\Utils::generateRandomString();

		if (move_uploaded_file($_FILES['graderlist']['tmp_name'], $xlsx_file)) {

			//Call up CGI script to process conversion.
			$xlsx_tmp = basename($xlsx_file);
			$csv_tmp = basename($csv_file);
			error_reporting(E_ALL);
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, __CGI_URL__."/xlsx_to_csv.cgi?xlsx_file={$xlsx_tmp}&csv_file={$csv_tmp}");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			$output = curl_exec($ch);
			if ($output === false) {
				terminate_on_error("Error parsing xlsx to csv.");
			}

			$output = json_decode($output, true);
			if ($output === null) {
				terminate_on_error("Error parsing JSON response: " . json_last_error_msg());
			} else if ($output['error'] === true) {
				terminate_on_error("Error parsing xlsx to csv: " . $output['error_message']);
			} else if ($output['success'] !== true) {
				terminate_on_error("Error on response on parsing xlsx: " . curl_error($ch));
			}

			curl_close($ch);
		} else {

			terminate_on_error("Error isolating uploaded XLSX.  Please contact tech support.");
		}

	} else if ($fileType === 'csv' && $mimeType === 'text/plain') {

	    //CSV detected.  No conversion needed.
    	$csv_file = $_FILES['graderlist']['tmp_name'];
	    $xlsx_file = null;
	} else {

		//Neither XLSX or CSV detected.  Good bye...
		terminate_on_error("Only xlsx or csv files are allowed!");
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

	/**
	 *  Process CSV to DB
	 */

	//Set environment config to allow '\r' EOL encoding.  (reverts back after script exits)
	//Otherwise, only '\n' and '\r\n' are allowed.  ('\r' is normally expected to be a Unix item seperator)
	//Older versions of Microsoft Excel on Macintosh write CSVs with '\r' EOL encoding.
	ini_set("auto_detect_line_endings", true);

	// Read file into row-by-row array.  Returns false on failure.
	$contents = file($csv_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	if ($contents === false) {
		terminate_on_error("File was not properly uploaded.  Please contact tech support.");
	}

	// Massage student CSV file into generalized data array
	$error_message = ""; //Used to show accumulated errors during data validation
	$row_being_processed = 0;
	$rows = array();
	if ($fileType == 'xlsx' && $mimeType == 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
		unset($contents[0]); // xlsx2csv will add a row to the top of the spreadsheet
	}

	//Validation and error checking.
	foreach($contents as $content) {
		$row_being_processed++;
		$vals = explode(",", trim($content));

		//Data checks
		//No check on user_id (computing login ID) -- different Univeristies have different formats.

		//First name must be alpha characters, white-space, or certain punctuation.
		$error_message .= preg_match("~^[a-zA-Z.'`\- ]+$~", $vals[1]) ? "" : "Error in first name column, row #{$row_being_processed}: {$vals[1]}" . PHP_EOL;

		//Last name must be alpha characters white-space, or certain punctuation.
		$error_message .= preg_match("~^[a-zA-Z.'`\- ]+$~", $vals[2]) ? "" : "Error in last name column, row #{$row_being_processed}: {$vals[2]}" . PHP_EOL;

		/**
		 *  Email address check.  Email address format is governed by RFC 6531,
		 *  and is far, far more complicated than is commonly seen.  We're going
		 *  to be a bit lazy on the check, but any "normal" email address should
		 *  pass the check and any obviously improper addresses should be
		 *  rejected.
		 *
		 *  We will check with the format of "address@domain".
		 *
		 *  - Permitted address formatting is incredibly complex and would make
		 *    the regex inordinately difficult.  But essentially the standard
		 *    does allow letters, digits, punctuation.  So we will be lazy and
		 *    simply check for at least one char of any type.
		 *  - a single '@' char is mandatory.
		 *  - Domain must be letters, digits, hyphens with a top level domain,
		 *    or be an IPv4/IPv6 address surrounded by brackets.  The check will
		 *    be condensed to any combination of letters, digits, hyphens, dots,
		 *    brackets, and colons (IPv6 uses colons).
		 *
		 *  These error check rules can be improved for better accuracy should
		 *  the need arise.
		 */
		$error_message .= preg_match("~.+@{1}[a-zA-Z0-9:\.\-\[\]]+$~", $vals[3]) ? "" : "Error in email column, row #{$row_being_processed}: {$vals[3]}" . PHP_EOL;

		//grader-level check is a digit between 1 - 4.
		$vals[4] = intval($vals[4]); //change float read from xlsx to int
		$error_message .= preg_match("~[1-4]{1}~", $vals[4]) ? "" : "Error in grader-level column, row #{$row_being_processed}: {$vals[4]}" . PHP_EOL;

		//Append content to data rows for processing.
		$rows[] = $vals;
	}

	//Display any accumulated errors.  Quit on errors, otherwise continue.
	if (empty($error_message) === false) {
		terminate_on_error($error_message . "Contact your sysadmin.");
	}

	/**
	 *  We are only INSERTing new graders.  Existing graders are not updated.
	 *  Therefore, we want to eliminate any existing graders from the uploaded
	 *  spreasdsheet.
	 *
	 *  Uploads and grader lists should be very short, so brute force processing
	 *  should be trivial.  But we can improve this with quicksort and binary
	 *  search should data lists become significantly more demanding.
	 */
	\lib\Database::query("SELECT user_id FROM users WHERE user_group != 4");
	foreach(\lib\Database::rows() as $grader) {
		foreach($rows as $index => $row) {
			if ($row[0] === $grader['user_id']) {
				unset($rows[$index]);
			}
		}
	}

	//INSERT remaining graders in spreadsheet data.
	$columns = implode(",", array("user_id", "user_firstname", "user_lastname", "user_email", "user_group", "manual_registration"));

	\lib\Database::beginTransaction();

	foreach($rows as $row) {
		$row[] = true;  //manual registration value is appended to data row, and is always true.
		\lib\Database::query("INSERT INTO users ({$columns}) VALUES (?, ?, ?, ?, ?, ?)", $row);
	}

	\lib\Database::commit();

	//All done -- redirect back to form.
	$inserted = count($rows);
	header("Location: admin-grader-list.php?course={$_GET['course']}&semester={$_GET['semester']}&update=1&inserted={$inserted}&this[]=Graders&this[]=Upload%20Grader%20List");
}

/**
 *  terminate_on_error($msg)
 *  IN:  Error message.
 *  OUT: No return
 *  PURPOSE: Print error message (presumably to web browser) and kill script
 */
function terminate_on_error ($msg) {
	echo nl2br($msg . PHP_EOL);
	echo "<a href=\"admin-grader-list.php?course={$_GET['course']}&semester={$_GET['semester']}&this[]=Graders&this[]=Upload%20Grader%20List\">Return</a>";
	die;
}

exit(0);

?>
