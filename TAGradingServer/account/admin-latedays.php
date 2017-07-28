<?php

//Author: Peter Bailie, Systems Programmer, RPI Computer Science, July 2016
//Update: Feb 8 2017 by pbailie

/* MAIN ===================================================================== */

//Permit '\r' EOL encoding (e.g. CSV export from MS Excel 2008/2011 for Mac).
ini_set("auto_detect_line_endings", true);

include "../header.php";

check_administrator();

/* Process ------------------------------------------------------------------ */

$view = new local_view();

//$state affects what's displayed in browser.
//Default state.
$state = "";

/* POST/FILES SUPERGLOBALS -------------------------------------------------- */

//Check to see if a CSV file was submitted.
if (isset($_FILES['csv_upload']) && (file_exists($_FILES['csv_upload']['tmp_name']))) {

	$data = array();
	if (!parse_and_validate_csv($_FILES['csv_upload']['tmp_name'], $data)) {
		$state = 'bad_upload';
	} else {
		upsert($data);
		$state = 'upsert_done';
	}

//if no file upload, examine Student ID and Late Day input fields.
} else if (isset($_POST['user_id'])   && ($_POST['user_id']   !== "") &&
		   isset($_POST['late_days']) && ($_POST['late_days'] !== "") &&
		   isset($_POST['datestamp']) && ($_POST['datestamp'] !== "")) {

	//Validate that late days entered is an integer >= 0.
	//Negative values will fail ctype_digit test.
	if (!ctype_digit($_POST['late_days'])) {
		$state = 'late_days_not_integer';
	}

	//Timestamp validation
	if (!validate_timestamp($_POST['datestamp'])) {
		$state = 'invalid_datestamp';
	}

	//Validate that student does exist in DB (per rcs_id)
	//"Student Not Found" error has precedence over late days being non-numerical
	//as it is the more likely error to happen.
	if (!verify_user_in_db($_POST['user_id'])) {
		$state = 'user_not_found';
	}

	//Process upsert if no errors were flagged.
	if (empty($state)) {

		//upsert argument requires 2D array.
		upsert(array(array($_POST['user_id'], $_POST['datestamp'], intval($_POST['late_days']))));
		$state = 'upsert_done';
	}
}
/* END POST/FILES SUPERGLOBAL ----------------------------------------------- */

//configure student table
$user_table_db_data = retrieve_users_from_db();
$view->configure_table($user_table_db_data);

//display
$view->display($state);

/* END Process -------------------------------------------------------------- */

include "../footer.php";
exit;

/* END MAIN ================================================================= */

function parse_and_validate_csv($csv_file, &$data) {
//IN:  * csv file name and path
//     * (by reference) empty data array that will be filled.
//OUT: TRUE should csv file be properly validated and data array filled.
//     FALSE otherwise.
//PURPOSE:  (1) validate uploaded csv file so it may be parsed.
//          (2) create data array of csv information that may be batch upserted.

	//Validate file MIME type (needs to be "text/plain")
	$file_info = finfo_open(FILEINFO_MIME_TYPE);
	$mime_type = finfo_file($file_info, $_FILES['csv_upload']['tmp_name']);
	finfo_close($file_info);

	//MIME type must be text, but all subtypes are acceptable.
	if (substr($mime_type, 0, 5) !== "text/") {
		$data = null;
		return false;
	}

	$rows = file($csv_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

	if ($rows === false) {
		$data = null;
		return false;
	}

	foreach($rows as $row) {

		$fields = explode(',', $row);

		//Remove any extraneous whitespace at beginning/end of all fields.
		foreach($fields as &$field) {
			$field = trim($field);
		} unset($field);

		//Each row has three fields
		if (count($fields) !== 3) {
			$data = null;
			return false;
		}

		//$fields[0]: Verify student exists in class (check by student user ID)
		if (!verify_user_in_db($fields[0])) {
			$data = null;
			return false;
		}

		//$fields[1] represents timestamp in the format (MM/DD/YY),
		//(MM/DD/YYYY), (MM-DD-YY), or (MM-DD-YYYY).
		if (!validate_timestamp($fields[1])) {
			$data = null;
			return false;
		}

		//$fields[2]: Number of late days must be an integer >= 0
		if (!ctype_digit($fields[2])) {
			$data = null;
			return false;
		}

		//Fields information seems okay.  Push fields onto data array.
		$data[] = $fields;
	}

	//Validation successful.
	return true;
}

/* END FUNCTION parse_and_validate_csv() ==================================== */

function validate_timestamp($timestamp) {
//IN:  $timestamp is actually a date string, not a Unix timestamp.
//OUT: TRUE when date string conforms to an accetpable pattern
//      FALSE otherwise.
//PURPOSE: Validate string to (1) be a valid date and (2) conform to specific
//         date patterns.
//         'm-d-Y' -> mm-dd-yyyy
//         'm-d-y' -> mm-dd-yy
//         'm/d/Y' -> mm/dd/yyyy
//         'm/d/y' -> mm/dd/yy

	//This bizzare/inverted switch-case block actually does work in PHP.
	//This operates as a form of "white list" of valid patterns.
	//This checks to ensure a date pattern is acceptable AND the date actually
	//exists.  e.g. "02-29-2016" is valid, while "06-31-2016" is not.
	//That is, 2016 is a leap year, but June has only 30 days.
	$tmp = array(DateTime::createFromFormat('m-d-Y', $timestamp),
				 DateTime::createFromFormat('m/d/Y', $timestamp),
				 DateTime::createFromFormat('m-d-y', $timestamp),
				 DateTime::createFromFormat('m/d/y', $timestamp));

	switch (true) {
	case ($tmp[0] && $tmp[0]->format('m-d-Y') === $timestamp):
	case ($tmp[1] && $tmp[1]->format('m/d/Y') === $timestamp):
	case ($tmp[2] && $tmp[2]->format('m-d-y') === $timestamp):
	case ($tmp[3] && $tmp[3]->format('m/d/y') === $timestamp):
		return true;
	default:
		return false;
	}
}

/* END FUNCTION validate_timestamp() ==================================== */

function verify_user_in_db($user_id) {
//IN:  User ID
//OUT: TRUE should user ID be found in the database.  FALSE otherwise.
//PURPOSE:  Verify that user is in database (indicating the user is enrolled)

	$sql = <<<SQL
SELECT COUNT(1)
FROM users
WHERE user_id=?
SQL;

	\lib\Database::query($sql, array($user_id));

	//row() will be either 1 (true) or 0 (false)
	return boolval(\lib\Database::row()['count']);

}

/* END FUNCTION verify_user_in_db() ====================================== */

function retrieve_users_from_db() {
//IN:  gradeable ID from database
//OUT: all students who have late days.  Retrieves student rcs, first name,
//     last name, timestamp and number of late days.
//PURPOSE:  Retrieve list of students to display current late days.

	$sql = <<<SQL
SELECT
	late_days.user_id,
	users.user_firstname,
	users.user_preferred_firstname,
	users.user_lastname,
	late_days.allowed_late_days,
	late_days.since_timestamp::timestamp::date
FROM users
FULL OUTER JOIN late_days
	ON users.user_id=late_days.user_id
WHERE late_days.allowed_late_days IS NOT NULL
	AND	late_days.allowed_late_days>0
	AND	users.user_group=4
ORDER BY
	users.user_email ASC,
	late_days.since_timestamp DESC;
SQL;

	\lib\Database::query($sql);
	return \lib\Database::rows();
}

/* END FUNCTION retrieve_users_from_db() ================================= */

function upsert(array $data) {
//IN:  Data to be "upserted"
//OUT: No return.  This is assumed to work.  (Server should throw an exception
//     if this process fails)
//PURPOSE:  "Update/Insert" data into the database.  This can handle large
//          "batch" upserts in a single transaction (for when upserting via CSV)

/* -----------------------------------------------------------------------------
 * This SQL code was adapted from upsert discussion on Stack Overflow and is
 * meant to be compatible with PostgreSQL prior to v9.5.
 *
 * 	q.v. http://stackoverflow.com/questions/17267417/how-to-upsert-merge-insert-on-duplicate-update-in-postgresql
 * -------------------------------------------------------------------------- */

	$sql = array();

	//TEMPORARY table to hold all new values that will be "upserted"
	$sql['temp_table'] = <<<SQL
CREATE TEMPORARY TABLE temp
	(user_id VARCHAR(255),
	date TIMESTAMP,
	late_days INTEGER)
ON COMMIT DROP;
SQL;

	//INSERT new data into temporary table -- prepares all data to be upserted
	//in a single DB transaction.
	for ($i=0; $i<count($data); $i++) {
		$sql["data_{$i}"] = <<<SQL
INSERT INTO temp VALUES (?,?,?);
SQL;
	}

	//LOCK will prevent sharing collisions while upsert is in process.
	$sql['lock'] = <<<SQL
LOCK TABLE late_days IN EXCLUSIVE MODE;
SQL;

	//This portion ensures that UPDATE will only occur when a record already exists.
	$sql['update'] = <<<SQL
UPDATE late_days
SET allowed_late_days=temp.late_days
FROM temp
WHERE late_days.user_id=temp.user_id
	AND late_days.since_timestamp=temp.date
SQL;

	//This portion ensures that INSERT will only occur when data record is new.
	$sql['insert'] = <<<SQL
INSERT INTO late_days
	(user_id,
	since_timestamp,
	allowed_late_days)
SELECT
	temp.user_id,
	temp.date,
	temp.late_days
FROM temp
LEFT OUTER JOIN late_days
	ON late_days.user_id=temp.user_id
	AND late_days.since_timestamp=temp.date
WHERE late_days.user_id IS NULL
SQL;

	//Begin DB transaction
	\lib\Database::beginTransaction();
	\lib\Database::query($sql['temp_table']);

	foreach ($data as $index => $record) {
		\lib\Database::query($sql["data_{$index}"], $record);
	}

	\lib\Database::query($sql['lock']);
	\lib\Database::query($sql['update']);
	\lib\Database::query($sql['insert']);
	\lib\Database::commit();
	//All Done!
	//Server will throw exception if there is a problem with DB access.
}

/* END FUNCTION upsert() ==================================================== */

class local_view {
//View class for admin-latedays.php

	//Properties
	//Class constants to represent unicode styled X and checkmark symbols.
	const UTF8_STYLED_X = "&#x2718";
	const UTF8_CHECKMARK = "&#x2714";

	//HTML data to be sent to browser
	static private $view;

	//Constructor
	public function __construct() {
		//Class constants cannot be expanded in strings with {}
		//So they are copied here for LOCAL use.
		$utf8_styled_x  = self::UTF8_STYLED_X;
		$utf8_checkmark = self::UTF8_CHECKMARK;

		self::$view = array();

		self::$view['head'] = <<<HTML
<style type="text/css">
	body {
		overflow-y: scroll;
	}

	#container-latedays
	{
		width:700px;
		margin: 70px auto 100px;
		background-color: #fff;
		border: 1px solid #999;
		-webkit-border-radius: 6px;
		-moz-border-radius: 6px;
		border-radius: 6px;outline: 0;
		-webkit-box-shadow: 0 3px 7px rgba(0,0,0,0.3);
		-moz-box-shadow: 0 3px 7px rgba(0,0,0,0.3);
		box-shadow: 0 3px 7px rgba(0,0,0,0.3);
		-webkit-background-clip: padding-box;
		-moz-background-clip: padding-box;
		background-clip: padding-box;
	}
</style>

<div id="container-latedays">
<div class="modal-header">
<h3>Late Days Allowed</h3>
</div>




<div class="modal-body" style="padding-top:10px; padding-bottom:10px;">

<p>
Use this form to grant students additional late days
(beyond the initial number specified in the course configuration).
</p>

<p>
Students may use these additional late days for any future homeworks
(after the specificed date).
</p>

HTML;

		self::$view['tail'] = <<<HTML
</div>
</div>
HTML;

		self::$view['bad_upload'] = <<<HTML
<p style="margin:0; padding-bottom:20px;"><em style="color:red; font-weight:bold; font-style:normal;">
{$utf8_styled_x} Something is wrong with the CSV upload.  No update done.</em>
HTML;

		self::$view['user_not_found'] = <<<HTML
<p style="margin:0; padding-bottom:20px;"><em style="color:red; font-weight:bold; font-style:normal;">
{$utf8_styled_x} User not found.</em>
HTML;

		self::$view['invalid_datestamp'] = <<<HTML
<p style="margin:0; padding-bottom:20px;"><em style="color:red; font-weight:bold; font-style:normal;">
{$utf8_styled_x} Invalid date or timestamp not properly formatted.</em>
HTML;

		self::$view['late_days_not_integer'] = <<<HTML
<p style="margin:0; padding-bottom:20px;"><em style="color:red; font-weight:bold; font-style:normal;">
{$utf8_styled_x} Late days must be an integer at least 0.</em>
HTML;

		self::$view['upsert_done'] = <<<HTML
<p style="margin:0; padding-bottom:20px;"><em style="color:green; font-weight:bold; font-style:normal;">
{$utf8_checkmark} Late days are updated.</em>
HTML;

		$BASE_URL = rtrim(__BASE_URL__, "/");

		self::$view['form'] = <<<HTML
<form action="{$BASE_URL}/account/admin-latedays.php?course={$_GET['course']}&semester={$_GET['semester']}&this=Late%20Days%20Allowed" method="POST" enctype="multipart/form-data">
<h4>Single Student Entry</h4>
<div style="width:30%; display:inline-block; vertical-align:top; padding-right:10px;">Student ID:<br><input type="text" name="user_id" style="width:95%;"></div>
<div style="width:30%; display:inline-block; vertical-align:top; padding-right:10px;">Datestamp (MM/DD/YY):<br><input type="text" name="datestamp" style="width:95%;"></div>
<div style="width:15%; display:inline-block; vertical-align:top; padding-right:15px;">Late Days:<br><input type="text" name="late_days" style="width:95%;"></div>
<div style="display:inline-block; vertical-align:top; padding-top:1.5em;"><input class='btn' type="submit" value="Submit"></div>
<h4>Multiple Student Entry Via CSV Upload</h4>
<div style="padding-bottom:20px;"><input type="file" name="csv_upload" onchange="this.form.submit()"></div>
</form>
HTML;

	}

/* END CLASS CONSTRUCTOR ---------------------------------------------------- */

	public function configure_table($db_data) {
	//IN:  data from database used to build table of granted late day exceptions
	//     for selected gradeable
	//OUT: no return (although private view['student_review_table'] property is
	//     filled)
	//PURPOSE: Craft HTML required to display a table of existing late day
	//         exceptions

		if (!is_array($db_data) || count($db_data) < 1) {
		//No late days in DB -- indicate as much.

			self::$view['user_review_table'] = <<<HTML
<p><em style="font-weight:bold; font-size:1.2em; font-style:normal;">No late days are currently entered.</em>
HTML;
		} else {
		//Late days found in DB -- build table to display

			//Table HEAD
			self::$view['user_review_table'] = <<<HTML
<table style="border:5px solid white; border-collapse:collapse; margin: 0 auto; text-align:center;">
<caption style="caption-side:top; font-weight:bold; font-size:1.2em;">
Late Days Allowed
</caption>
<th style="background:lavender; width:20%;">Student ID</th>
<th style="background:lavender; width:20%;">First Name</th>
<th style="background:lavender; width:20%;">Last Name</th>
<th style="background:lavender; width:20%;">Total Allowed Late Days</th>
<th style="background:lavender;">Effective Date</th>
HTML;

			//Table BODY
			$cell_color = array('white', 'aliceblue');
			foreach ($db_data as $index => $record) {
				$firstname = getDisplayName($record);
				self::$view['user_review_table'] .= <<<HTML
<tr>
<td style="background:{$cell_color[$index%2]};">{$record['user_id']}</td>
<td style="background:{$cell_color[$index%2]};">{$firstname}</td>
<td style="background:{$cell_color[$index%2]};">{$record['user_lastname']}</td>
<td style="background:{$cell_color[$index%2]};">{$record['allowed_late_days']}</td>
<td style="background:{$cell_color[$index%2]};">{$record['since_timestamp']}</td>
</tr>
HTML;
			}

			//Table TAIL
			self::$view['user_review_table'] .= <<<HTML
</table>
HTML;
		}
	}

/* END CLASS METHOD configure_table() --------------------------------------- */

	public function display($state) {
	//IN:  Current "display state" determined in MAIN process
	//OUT: No return, although ALL crafted HTML is sent to browser
	//PURPOSE:  Display appropriate page contents.

		switch($state) {
		case 'bad_upload':
			echo self::$view['head']              .
   				 self::$view['form']              .
			     self::$view['bad_upload']        .
			     self::$view['user_review_table'] .
			     self::$view['tail'];
			break;
		case 'user_not_found':
			echo self::$view['head']              .
   				 self::$view['form']              .
			     self::$view['user_not_found']    .
			     self::$view['user_review_table'] .
			     self::$view['tail'];
			break;
		case 'invalid_datestamp':
			echo self::$view['head']              .
			     self::$view['form']              .
			     self::$view['invalid_datestamp'] .
			     self::$view['user_review_table'] .
			     self::$view['tail'];
		    break;
		case 'late_days_not_integer':
			echo self::$view['head']                  .
			     self::$view['form']                  .
			     self::$view['late_days_not_integer'] .
			     self::$view['user_review_table']     .
			     self::$view['tail'];
		    break;
		case 'upsert_done':
			echo self::$view['head']              .
				 self::$view['form']              .
				 self::$view['upsert_done']       .
			     self::$view['user_review_table'] .
			     self::$view['tail'];
			break;
		default:
			echo self::$view['head']              .
				 self::$view['form']              .
			     self::$view['user_review_table'] .
			     self::$view['tail'];
			break;
		}
	}
}
/* END CLASS METHOD display() ----------------------------------------------- */
/* END CLASS local_view ===================================================== */
/* EOF ====================================================================== */
?>
