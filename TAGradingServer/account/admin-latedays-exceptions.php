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
//Examine drop-down and get $g_id (gradeable_id)
if (isset($_POST['selected_gradeable'])) {
	$g_id = $_POST['selected_gradeable'];
} else {
	$g_id = retrieve_newest_gradeable_id_from_db();
}
	
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

//configure form
$gradeables_db_data = retrieve_gradeables_from_db();
$view->configure_form($g_id, $gradeables_db_data);

//configure student table
$student_table_db_data = retrieve_students_from_db($g_id);
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
		
		//$fields[1] represents gradeable id.  It must (1) be an integer >= 0
		//           AND exist in database
		//           ctype_digit() returns false with negative integers as strings
		if (!ctype_digit($fields[1]) || !verify_gradeable_in_db($fields[1])) {
			$data = null;
			return false;
		}		
		
		//$fields[2]: Number of late day exceptions must be an integer >= 0
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

	$sql = <<<SQL
SELECT COUNT(1)
FROM users
WHERE user_id=?
AND	user_group=4
SQL;

	\lib\Database::query($sql, array($student));

	//row() will be either 1 (true) or 0 (false)
	return boolval(\lib\Database::row()['count']);
}

/* END FUNCTION verify_student_in_db() ====================================== */

function verify_gradeable_in_db($gradeable_id) {
//IN:   gradeable's ID to verify its existence
//OUT:  TRUE when gradeable ID is found in database.  FALSE otherwise.
//PURPOSE:  Find a gradeable's serial ID by a gradeable's title.

	$sql = <<<SQL
SELECT COUNT(1)
FROM gradeable
WHERE g_id=?
SQL;

	\lib\Database::query($sql, array($gradeable_id));
	
	//row() will be either 1 (true) or 0 (false)
	return boolval(\lib\Database::row()['count']);
}

/* END FUNCTION verify_gradeable_in_db() ==================================== */

function retrieve_newest_gradeable_id_from_db() {
//IN:  No parameters
//OUT: Find "maximum value" of the gradeable sequential ID.
//PURPOSE:  Gradeable drop down menu is ordered with newest first.
//          In "old schema", by locating the "maximum value" of the sequential
//          ID, the "newest" gradeable is determined.  This is used as the
//          "default" gradeable selection.


	$sql = <<<SQL
SELECT g_id
FROM gradeable
ORDER BY g_grade_start_date DESC LIMIT 1;
SQL;

	\lib\Database::query($sql);
	return \lib\Database::row()['g_id'];
}

/* END FUNCTION retrieve_newest_gradeable_id_from_db() ====================== */

function retrieve_gradeables_from_db() {
//IN:  No parameterd
//OUT: All permissable gradeables ID and title, ordered dscending by ID
//PURPOSE:  To build drop down menu of selectable gradeables.  Ordered
//          descending so "newer" are higher in the menu.


	$sql = <<<SQL
SELECT g_id, g_title
FROM gradeable
WHERE g_gradeable_type=0
ORDER BY g_grade_released_date DESC;
SQL;

	\lib\Database::query($sql);
	return \lib\Database::rows();
}

/* END FUNCTION retrieve_gradeables_from_db() =============================== */

function retrieve_students_from_db($gradeable_id = 0) {
//IN:  gradeable ID from database
//OUT: all students who have late day exceptions, per gradeable ID parameter.
//     retrieves student rcs, first name, last name, and late day exceptions.
//PURPOSE:  Retrieve list of students to display current late day exceptions.

	$sql = <<<SQL
SELECT
	users.user_email,
	users.user_firstname,
	users.user_lastname,
	late_day_exceptions.late_day_exceptions
FROM users
FULL OUTER JOIN late_day_exceptions
	ON users.user_id=late_day_exceptions.user_id
WHERE late_day_exceptions.g_id=?
	AND users.user_group=4
	AND	late_day_exceptions.late_day_exceptions IS NOT NULL
	AND	late_day_exceptions.late_day_exceptions>0
ORDER BY users.user_email ASC;
SQL;

	\lib\Database::query($sql, array($gradeable_id));
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
	

	//SQL code for "new schema"


	//TEMPORARY table to hold all new values that will be "upserted"
	$sql['temp_table'] = <<<SQL
CREATE TEMPORARY TABLE temp
	(student_rcs VARCHAR(255),
	gradeable_id VARCHAR(255),
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
    
    print_r($data);

	//LOCK will prevent sharing collisions while upsert is in process.
	$sql['lock'] = <<<SQL
LOCK TABLE late_day_exceptions IN EXCLUSIVE MODE;
SQL;

	//This portion ensures that UPDATE will only occur when a record already exists.
	$sql['update'] = <<<SQL
UPDATE late_day_exceptions
SET late_day_exceptions=temp.late_days
FROM temp
WHERE late_day_exceptions.user_id=temp.student_rcs
	AND late_day_exceptions.g_id=temp.gradeable_id;
SQL;

	//This portion ensures that INSERT will only occur when data record is new.
	$sql['insert'] = <<<SQL
INSERT INTO late_day_exceptions
	(user_id,
	g_id,
	late_day_exceptions)
SELECT
	temp.student_rcs,
	temp.gradeable_id,
	temp.late_days
FROM temp 
LEFT OUTER JOIN late_day_exceptions
	ON late_day_exceptions.user_id=temp.student_rcs
	AND late_day_exceptions.g_id=temp.gradeable_id
WHERE late_day_exceptions.user_id IS NULL
	OR late_day_exceptions.g_id IS NULL;
SQL;

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
	private $utf8_styled_x;
	private $utf8_checkmark;
	static private $view;  //HTML data to be sent to browser
	
	//Constructor
	public function __construct() {
		$this->utf8_styled_x  = "&#x2718";
		$this->utf8_checkmark = "&#x2714";
		self::$view = array();
		
		self::$view['head'] = <<<HTML
<div id="container" style="width:100%; margin-top:40px;">
<div class="modal hide fade in" style="display:block; margin-top:5%; z-index:100;">
<div class="modal-header">
<h3>Add Late Day Exceptions</h3>
</div>
HTML;

		self::$view['tail'] = <<<HTML
</div>
</div>	
HTML;

		self::$view['bad_upload'] = <<<HTML
<div class="modal-body">
<p><em style="color:red; font-weight:bold; font-style:normal;">
{$this->utf8_styled_x} Something is wrong with the CSV upload.  No update done.</em>
</div>
HTML;

		self::$view['student_not_found'] = <<<HTML
<div class="modal-body">
<p><em style="color:red; font-weight:bold; font-style:normal;">
{$this->utf8_styled_x} Student not found.</em>
</div>
HTML;

		self::$view['late_days_not_integer'] = <<<HTML
<div class="modal-body">
<em style="color:red; font-weight:bold; font-style:normal;">
{$this->utf8_styled_x} Late days must be an integer at least 0.</em>
</div>
HTML;

		self::$view['upsert_done'] = <<<HTML
<div class="modal-body">
<p><em style="color:green; font-weight:bold; font-style:normal;">
{$this->utf8_checkmark} Late day exceptions are updated.</em>
</div>
HTML;

	}
	
/* END CLASS CONSTRUCTOR ---------------------------------------------------- */	

	public function configure_form($g_id, $db_data) {
	//IN:  selected gradeable id and data from database used to build form
	//OUT: no return (although private view['form'] property is filled)
	//PURPOSE: Craft HTML required to display input form.  $g_id and $dv_data
	//         Are essential for crafting a proper drop-down menu for selecting
	//         a gradeable.  That is, only gradeables that exist are shown.
	
		self::$view['form'] = <<<HTML
<div class="modal-body" style="padding-top:20px; padding-bottom:20px;">
<form action="admin-latedays-exceptions.php" method="POST" enctype="multipart/form-data">
<p>Select Rubric:
<select name="selected_gradeable" onchange="this.form.submit()">
HTML;

		foreach($db_data as $index => $gradeable) {
		
			if ($g_id == $gradeable[0]) {
				self::$view['form'] .= <<<HTML
<option value="{$gradeable[0]}" selected="selected">{$gradeable[1]}</option>
HTML;
			} else {
				self::$view['form'] .= <<<HTML
<option value="{$gradeable[0]}">{$gradeable[1]}</option>
HTML;
			}
		}

		self::$view['form'] .= <<<HTML
</select>
<h4>Single Student Entry</h4>
<table style="border:5px solid white;"><tr>
<td style="border:5px solid white;">Student ID:<br><input type="text" name="student_id"></td>
<td style="border:5px solid white;">Late Days:<br><input type="text" name="late_days"></td>
<td style="border:5px solid white;"><input type="submit" value="Submit"></td>
</tr></table>
<h4>Multiple Student Entry Via CSV Upload</h4>
<input type="file" name="csv_upload" onchange="this.form.submit()">
</form>
</div>
HTML;

	}

/* END CLASS METHOD configure_form() ---------------------------------------- */
	
	public function configure_table($db_data) {
	//IN:  data from database used to build table of granted late day exceptions for selected gradeable
	//OUT: no return (although private view['student_review_table'] property is filled)
	//PUTRPOSE: Craft HTML required to display a table of existing late day exceptions	
	
		if (!is_array($db_data) || count($db_data) < 1) {
		//No late days in DB -- indicate as much.

			self::$view['student_review_table'] = <<<HTML
<div class="modal-body" style="padding-top:20px; padding-bottom:20px;">
<p style="font-weight:bold; font-size:1.2em;">No late day exceptions are currently entered for this assignment.
</div>
HTML;
		} else {
		//Late days found in DB -- build table to display

			//Table HEAD
			self::$view['student_review_table'] = <<<HTML
<div class="modal-body" style="padding-top:20px; padding-bottom:20px;">
<table style="border:5px solid white; border-collapse:collapse; margin: 0 auto; text-align:center;">
<caption style="caption-side:top; font-weight:bold; font-size:1.2em;">
Current Late Day Exceptions
</caption>
<th style="background:lavender; width:25%;">Student ID</th>
<th style="background:lavender; width:25%;">First Name</th>
<th style="background:lavender; width:25%;">Last Name</th>
<th style="background:lavender; width:25%;">Late Day Exceptions</th>
HTML;
	
			//Table BODY
			$cell_color = array('white', 'aliceblue');
			foreach ($db_data as $index => $record) {
				self::$view['student_review_table'] .= <<<HTML
<tr>
<td style="background:{$cell_color[$index%2]};">{$record[0]}</td>
<td style="background:{$cell_color[$index%2]};">{$record[1]}</td>
<td style="background:{$cell_color[$index%2]};">{$record[2]}</td>
<td style="background:{$cell_color[$index%2]};">{$record[3]}</td>
</tr>
HTML;
			}

			//Table TAIL
			self::$view['student_review_table'] .= <<<HTML
</table>
</div>
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
			echo self::$view['head']                 .
   				 self::$view['form']                 . 
			     self::$view['bad_upload']           .
			     self::$view['student_review_table'] .
			     self::$view['tail'];
			break;
		case 'student_not_found':
			echo self::$view['head']                 .
   				 self::$view['form']                 . 
			     self::$view['student_not_found']    .
			     self::$view['student_review_table'] .
			     self::$view['tail'];
			break;
		case 'late_days_not_integer':
			echo self::$view['head']                  .
   				 self::$view['form']                  . 
			     self::$view['late_days_not_integer'] .   				 
			     self::$view['student_review_table']  .
			     self::$view['tail'];
		    break;
		case 'upsert_done':
			echo self::$view['head']                 .
				 self::$view['form']                 . 
				 self::$view['upsert_done']          . 
			     self::$view['student_review_table'] .
			     self::$view['tail'];
			break;
		default:
			echo self::$view['head']                 .
				 self::$view['form']                 . 
			     self::$view['student_review_table'] .
			     self::$view['tail'];
			break;
		}
	}
}
/* END CLASS METHOD display() ----------------------------------------------- */
/* END CLASS local_view ===================================================== */
/* EOF ====================================================================== */
?>
