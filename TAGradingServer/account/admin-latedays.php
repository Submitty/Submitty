<?php

//Author: Peter Bailie, Systems Programmer, RPI Computer Science, July 2016

/* MAIN ===================================================================== */

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
} else if (isset($_POST['student_id']) && ($_POST['student_id'] !== "") &&
		   isset($_POST['late_days'])  && ($_POST['late_days']  !== "")) {

	//Validate that late days entered is an integer >= 0.
	//Negative values will fail ctype_digit test.
	if (!ctype_digit($_POST['late_days'])) {
		$state = 'late_days_not_integer';
	}
	
	//Validate that student does exist in DB (per rcs_id)
	//"Student Not Found" error has precedence over late days being non-numerical
	//as it is the more likely error to happen.
	if (!verify_student_in_db($_POST['student_id'])) {
		$state = 'student_not_found';
	}
	
	//Process upsert if no errors were flagged.
	if (empty($state)) {

		//upsert argument requires 2D array.
		upsert(array(array($_POST['student_id'], $g_id, intval($_POST['late_days']))));
		$state = 'upsert_done';
	}
}
/* END POST/FILES SUPERGLOBAL ----------------------------------------------- */

//configure student table
$student_table_db_data = retrieve_students_from_db();
$view->configure_table($student_table_db_data);

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
	
	if ($mime_type !== "text/plain") {
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
		
		//Each row has three fields
		if (count($fields) !== 3) {
			$data = null;
			return false;
		}
		
		//$fields[0]: Verify student exists in class (check by RCS ID)
		if (!verify_student_in_db($fields[0])) {
			$data = null;
			return false;
		}		
		
		/* this may require php5-intl extension (Ubuntu 14.04) -------------- */
		/* sudo apt-get install php5-intl                                     */

		//$fields[1] represents timestamp.  Format will be inputted through an
		//HTML date field.  Some browsers (notably Chrome) provide a date
		//picker in the locally defined date format.
/*
			$data = null;
			return false;
*/
		
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

function verify_student_in_db($student) {
//IN:  RCS student ID
//OUT: TRUE should RCS ID be found in the database.  FALSE otherwise.
//PURPOSE:  Verify that student is in database (indicating the student is enrolled)

	//SQL for "old schema"
	$sql = <<<SQL
SELECT COUNT(1)
FROM students
WHERE student_rcs=?
SQL;

	//SQL for "new schema"
	//FIXME: update 'user_group' property to match the STUDENTS group
/* DISABLED CODE ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	$sql = <<<SQL
SELECT COUNT(1)
FROM users
WHERE user_id=?
AND	user_group=0
SQL;
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ END DISABLED CODE */

	\lib\Database::query($sql, array($student));

	//row() will be either 1 (true) or 0 (false)
	return boolval(\lib\Database::row()['count']);
}

/* END FUNCTION verify_student_in_db() ====================================== */

function retrieve_students_from_db() {
//IN:  gradeable ID from database
//OUT: all students who have late day exceptions, per gradeable ID parameter.
//     retrieves student rcs, first name, last name, and late day exceptions.
//PURPOSE:  Retrieve list of students to display current late day exceptions.

	//SQL for "old schema"
	$sql = <<<SQL
SELECT 
	students.student_rcs,
	students.student_first_name,
	students.student_last_name,
	late_days.allowed_lates,
	late_days.since_timestamp::timestamp::date
FROM students
FULL OUTER JOIN late_days
	ON students.student_rcs=late_days.student_rcs
WHERE late_days.allowed_lates IS NOT NULL
	AND late_days.allowed_lates>0
ORDER BY students.student_rcs ASC;
SQL;
	
	//SQL for "new schema"
	//FIXME: update user.user_group property to match value representing students.
/* DISABLED CODE ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	$sql = <<<SQL
SELECT
	users.user_email,
	users.user_firstname,
	users.user_lastname,
	late_days.user_id,
	late_days.since_timestamp
FROM users
FULL OUTER JOIN late_days
	ON users.user_id=late_days.user_id
WHERE late_day_exceptions.g_id=?
	AND users.user_group=0
	AND	late_days.allowed_late_days IS NOT NULL
	AND	late_days.allowed_late_days>0
ORDER BY users.user_email ASC;
SQL;
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ END DISABLED CODE */

	\lib\Database::query($sql);
	return \lib\Database::rows();
}

/* END FUNCTION retrieve_students_from_db() ================================= */

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

	//SQL for "old schema"
	$sql = array();
	
	//TEMPORARY table to hold all new values that will be "upserted"
	$sql['temp_table'] = <<<SQL
CREATE TEMPORARY TABLE temp
	(student_rcs VARCHAR(255),
	date TIMESTAMP,
	late_days INTEGER)
ON COMMIT DROP;
SQL;

	//INSERT new data into temporary table -- prepares all data to be upserted
	//in a single batch DB transaction.
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
SET
	allowed_late_days=temp.late_days,
	since_timestamp=temp.date
FROM temp
WHERE late_days.student_rcs=temp.student_rcs
SQL;

	//This portion ensures that INSERT will only occur when data record is new.
	$sql['insert'] = <<<SQL
INSERT INTO late_days
	(student_rcs,
	since_timestamp,
	allowed_lates
SELECT
	temp.student_rcs,
	temp.gradeable_id,
	temp.late_days
FROM temp 
LEFT OUTER JOIN late_days
	ON late_days.student_rcs=temp.student_rcs
WHERE late_days.student_rcs IS NULL
SQL;

	//SQL code for "new schema"
/* DISABLED CODE ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	//TEMPORARY table to hold all new values that will be "upserted"
	$sql['temp_table'] = <<<SQL
CREATE TEMPORARY TABLE temp
	(student_rcs VARCHAR(255),
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
WHERE late_day.user_id=temp.student_rcs
SQL;

	//This portion ensures that INSERT will only occur when data record is new.
	$sql['insert'] = <<<SQL
INSERT INTO late_day_exceptions
	(user_id,
	since_timestamp,
	allowed_late_days)
SELECT
	temp.student_rcs,
	temp.date,
	temp.late_days
FROM temp 
LEFT OUTER JOIN late_days
	ON late_days.user_id=temp.student_rcs
WHERE late_days.user_id IS NULL
SQL;

~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ END DISABLED CODE */	

	//Begin DB transaction
	\lib\Database::beginTransaction();
	\lib\Database::query($sql['temp_table']);
	
	foreach ($data as $index => $record) {
		\lib\Database::query($sql["data_{$index}"], array($record[0], $record[1], $record[2]));
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
//View class for admin-latedays-exceptions.php

	//Properties
	static private $utf8_styled;
	static private $utf8_checkmark;
	static private $view;  //HTML data to be sent to browser
	
	//Constructor
	public function __construct() {
		$this->utf8_styled_x  = "&#x2718";
		$this->utf8_checkmark = "&#x2714";
		$this->view = array();
		
		$this->view['head'] = <<<HTML
<div id="container" style="width:100%; margin-top:40px;">
<div class="modal hide fade in" style="display:block; margin-top:5%; z-index:100;">
<div class="modal-header">
<h3>Add Late Days</h3>
</div>
<div class="modal-body" style="padding-top:10px; padding-bottom:10px;">
HTML;

		$this->view['tail'] = <<<HTML
</div>
</div>
</div>
HTML;

		$this->view['bad_upload'] = <<<HTML
<div class="modal-body">
<p><em style="color:red; font-weight:bold; font-style:normal;">
{$this->utf8_styled_x} Something is wrong with the CSV upload.  No update done.</em>
</div>
HTML;

		$this->view['student_not_found'] = <<<HTML
<div class="modal-body">
<p><em style="color:red; font-weight:bold; font-style:normal;">
{$this->utf8_styled_x} Student not found.</em>
</div>
HTML;

		$this->view['late_days_not_integer'] = <<<HTML
<div class="modal-body">
<em style="color:red; font-weight:bold; font-style:normal;">
{$this->utf8_styled_x} Late days must be an integer at least 0.</em>
</div>
HTML;

		$this->view['upsert_done'] = <<<HTML
<div class="modal-body">
<p><em style="color:green; font-weight:bold; font-style:normal;">
{$this->utf8_checkmark} Late day exceptions are updated.</em>
</div>
HTML;

		$this->view['form'] = <<<HTML
<form action="admin-latedays-exceptions.php" method="POST" enctype="multipart/form-data">
<h4>Single Student Entry</h4>
<div style="width:30%; display:inline-block; vertical-align:top; padding-right:10px;">Student ID:<br><input type="text" name="student_id" style="width:95%;"></div>
<div style="width:30%; display:inline-block; vertical-align:top; padding-right:10px;">Timestamp:<br><input type="date" name="timestamp" style="width:95%;"></div>
<div style="width:15%; display:inline-block; vertical-align:top; padding-right:15px;">Late Days:<br><input type="text" name="late_days" style="width:95%;"></div>
<div style="display:inline-block; vertical-align:top; padding-top:1.5em;"><input type="submit" value="Submit"></div>
<div style="display:inline-block; vertical-align:top; padding-bottom:20px;">If you leave Timestamp blank, the current date will be assumed.</div>
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
	//PUTRPOSE: Craft HTML required to display a table of existing late day
	//          exceptions	
	
		if (!is_array($db_data) || count($db_data) < 1) {
		//No late days in DB -- indicate as much.

			$this->view['student_review_table'] = <<<HTML
<p style="font-weight:bold; font-size:1.2em;">No late days are currently entered.
HTML;
		} else {
		//Late days found in DB -- build table to display

			//Table HEAD
			$this->view['student_review_table'] = <<<HTML
<table style="border:5px solid white; border-collapse:collapse; margin: 0 auto; text-align:center;">
<caption style="caption-side:top; font-weight:bold; font-size:1.2em;">
Current Late Day Exceptions
</caption>
<th style="background:lavender; width:20%;">Student ID</th>
<th style="background:lavender; width:20%;">First Name</th>
<th style="background:lavender; width:20%;">Last Name</th>
<th style="background:lavender; width:20%;">Allowed Lates</th>
<th style="background:lavender;">Since Date</th>
HTML;
	
			//Table BODY
			$cell_color = array('white', 'aliceblue');
			foreach ($db_data as $index => $record) {
				$this->view['student_review_table'] .= <<<HTML
<tr>
<td style="background:{$cell_color[$index%2]};">{$record[0]}</td>
<td style="background:{$cell_color[$index%2]};">{$record[1]}</td>
<td style="background:{$cell_color[$index%2]};">{$record[2]}</td>
<td style="background:{$cell_color[$index%2]};">{$record[3]}</td>
<td style="background:{$cell_color[$index%2]};">{$record[4]}</td>
</tr>
HTML;
			}

			//Table TAIL
			$this->view['student_review_table'] .= <<<HTML
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
			echo $this->view['head']                 .
   				 $this->view['form']                 . 
			     $this->view['bad_upload']           .
			     $this->view['student_review_table'] .
			     $this->view['tail'];
			break;
		case 'student_not_found':
			echo $this->view['head']                 .
   				 $this->view['form']                 . 
			     $this->view['student_not_found']    .
			     $this->view['student_review_table'] .
			     $this->view['tail'];
			break;
		case 'late_days_not_integer':
			echo $this->view['head']                  .
			     $this->view['form']                  . 
			     $this->view['late_days_not_integer'] .   				 
			     $this->view['student_review_table']  .
			     $this->view['tail'];
		    break;
		case 'upsert_done':
			echo $this->view['head']                 .
				 $this->view['form']                 . 
				 $this->view['upsert_done']          . 
			     $this->view['student_review_table'] .
			     $this->view['tail'];
			break;
		default:
			echo $this->view['head']                 .
				 $this->view['form']                 . 
			     $this->view['student_review_table'] .
			     $this->view['tail'];
			break;
		}
	}
}
/* END CLASS METHOD display() ----------------------------------------------- */
/* END CLASS local_view ===================================================== */
/* EOF ====================================================================== */
?>
