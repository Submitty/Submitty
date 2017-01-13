<?php
require "../toolbox/functions.php";

check_administrator();

if ($_SERVER['REQUEST_METHOD'] === "POST") {
	/**
	 *
	 *  Process
	 *
	 */

	if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf']) {
	    die("invalid csrf token");
	}

	//Verify that upload is a true CSV or XLSX file (check file extension and MIME type)
	$fileType = pathinfo($_FILES['classlist']['name'], PATHINFO_EXTENSION);
	$fh = finfo_open(FILEINFO_MIME_TYPE);
	$mimeType = finfo_file($fh, $_FILES['classlist']['tmp_name']);
	finfo_close($fh); //No longer needed once mime type is determined.

	if ($fileType == 'xlsx' && $mimeType == 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {

		//XLSX detected.  Conversion needed.
		$csv_file = "/tmp/".\lib\Utils::generateRandomString();
		$old_umask = umask(0007);
		file_put_contents($csv_file, "");
		umask($old_umask);
		//@chmod($csv_file, 0660);
		$xlsx_file = "/tmp/".\lib\Utils::generateRandomString();

		if (move_uploaded_file($_FILES['classlist']['tmp_name'], $xlsx_file)) {

			//Call up CGI script to process conversion.
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
			} else if ($output['error'] === true) {
				die("Error parsing xlsx to csv: ".$output['error_message']);
			} else if ($output['success'] !== true) {
				die("Error on response on parsing xlsx: ".curl_error($ch));
			}

			curl_close($ch);
		} else {

			die("Error isolating uploaded XLSX.  Please contact tech support.");
		}

	} else if ($fileType == 'csv' && $mimeType == 'text/plain') {

	    //CSV detected.  No conversion needed.
    	$csv_file = $_FILES['classlist']['tmp_name'];
	    $xlsx_file = null;
	} else {

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

	/**
	 *
	 *  Process CSV to DB
	 *
	 */

	// Read file into row-by-row array.  Returns false on failure.
	$contents = file($csv_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	if ($contents === false) {
		die("File was not properly uploaded.  Please contact tech support.");
	}

	//TO DO: SQL processing of CSV







	header("Location: admin-classlist.php?course={$_GET['course']}&semester={$_GET['semester']}&update=1&inserted={$inserted}&updated={$updated}&deleted={$deleted}&moved={$moved}&this[]=Graders&this[]=Upload%20Grader%20List");

} else {

	/**
	 *
	 *  Form
	 *
	 */

	include "../header.php";

	$account_subpages_unlock = true;
	echo <<<HTML
<div id="container" style="width:100%; margin-top:40px;">
<div class="modal hide fade in" tabindex="-1" role="dialog" aria-labelledby="classlist" aria-hidden="false" style="display: block; margin-top:5%; z-index:100;">
<form action="admin-classlist.php?course={$_GET['course']}&semester={$_GET['semester']}" method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="{$_SESSION['csrf']}" />
<div class="modal-header">
<h3 id="myModalLabel">Upload Grader List</h3>
</div>

<div class="modal-body" style="padding-top:20px; padding-bottom:20px;">
HTML;

	if (isset($_GET['update']) && $_GET['update'] == '1') {
		$updated =  isset($_GET['updated'])  ? intval($_GET['updated'])  : 0;
		$inserted = isset($_GET['inserted']) ? intval($_GET['inserted']) : 0;
		$deleted =  isset($_GET['deleted'])  ? intval($_GET['deleted'])  : 0;
		$moved =    isset($_GET['moved'])    ? intval($_GET['moved'])    : 0;

		echo <<<HTML
<div style='color:red'>
Graders List Updated:<br />
<span style="margin-left: 20px">{$inserted} graders added</span><br />
<span style="margin-left: 20px">{$updated} graders updated</span><br />
</div><br />
HTML;

	}

	echo <<<HTML
Upload Grader List: <input type="file" name="classlist" id="classlist"><br />
Ignore graders marked manual in the grader list? <input type="checkbox" name="ignore_manual_1" checked="checked" /><br />
What to do with graders in DB, but not grader list?
<select name="missing_students">
<option value="-2">Nothing</option>
<option value="-1">Delete</option>
</select><br />
Ignore graders marked manual from above option? <input type="checkbox" name="ignore_manual_2" checked="checked" />
</div>

<div class="modal-footer">
<div style="width:50%; float:right; margin-top:5px;">
<input class="btn btn-primary" type="submit" value="Upload Grader List" />
</div>
</div>
</form>
</div>
</div>
HTML;

	include "../footer.php";
}
