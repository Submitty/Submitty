<?php

//Author: Peter Bailie, Systems Programmer, RPI Computer Science, July 2016

/* MAIN ===================================================================== */

include "../header.php";

check_administrator();

if($user_is_administrator) {
//User is administrator -- proceed with process.

	$view = new local_view();

	/* Process -------------------------------------------------------------- */
	//$state affects what's displayed in browser)
	//Default state.
	$state = "";

	/* POST SUPERGLOBAL ----------------------------------------------------- */
	//Examine drop-down and get $g_id (gradeable_id)
	if (isset($_POST['selected_gradeable'])) {
		$g_id = intval(substr($_POST['selected_gradeable'], 2));
	} else {
		$g_id = retrieve_newest_gradeable_id_from_db();
	}
		
	//Examine Student ID and Late Day input fields.
	if (isset($_POST['student_id']) && ($_POST['student_id'] !== "") &&
	    isset($_POST['late_days']) && ($_POST['late_days'] !== "")) {

		//Validate that late days entered is an integer
		if (!ctype_digit($_POST['late_days'])) {
			$state = 'late_days_not_integer';
		}
		
		//"Student Not Found" error has precedence over late days being non-numerical.
		//This is the more likely error to happen of these two.
		if (verify_student_in_db($_POST['student_id']) === 0) {
			$state = 'student_not_found';
		}
		
		//Process upsert if no errors were flagged.
		if (empty($state)) {
			//upsert argument requires 2D array
			upsert(array(array($_POST['student_id'], $g_id, intval($_POST['late_days']))));
			$state = 'upsert_confirmed';
		}
	}
	/* END POST SUPERGLOBAL ------------------------------------------------- */

	//configure form
	$gradeables_db_data = retrieve_gradeables_from_db();
	$view->configure_form($g_id, $gradeables_db_data);
	
	//configure student table
	$student_table_db_data = retrieve_students_from_db($g_id);
	$view->configure_table($student_table_db_data);
	
	//display
	$view->display($state);
	
	/* END Process ---------------------------------------------------------- */

} else {
//User is NOT administrator -- operation not permitted.  Display error.

    $view = new local_view();
    $view->display('unauthorized');
}

include "../footer.php";
exit;

/* END MAIN ================================================================= */

function verify_student_in_db($student_id) {

	//Old Schema
	$sql = <<<SQL
SELECT
	COUNT(1)
FROM
	students
WHERE
	student_rcs=?
SQL;

	\lib\Database::beginTransaction();
	\lib\Database::query($sql, array($student_id));
	\lib\Database::commit();
	
 	return \lib\Database::row()['count'];
}

function retrieve_newest_gradeable_id_from_db() {

	//Old Schema
	$sql = <<<SQL
SELECT
	MAX(rubric_id)
FROM rubrics;
SQL;

	//New Schema
	//FIXME: update 'gradeable_type' property to locate any gradeable of which late days are permissible.
/* DISABLED CODE ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	$sql = <<<SQL
SELECT
	MAX(gradeable_id)
FROM gradeables
WHERE
	gradeable_type=0"
SQL;
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ END DISABLED CODE */

	\lib\Database::beginTransaction();
	\lib\Database::query($sql);
	\lib\Database::commit();
	
	return \lib\Database::row()['max'];
}

/* END FUNCTION retrieve_newest_gradeable_id_from_db() ====================== */

function retrieve_gradeables_from_db() {

	//Old Schema
	$sql = <<<SQL
SELECT
	rubric_id,
	rubric_name
FROM rubrics
ORDER BY rubric_id DESC;
SQL;

	//New Schema
	//FIXME: update 'gradeable_type' property to locate any gradeable of which late days are permissible.
/* DISABLED CODE ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	$sql = <<<SQL
SELECT
	gradeable_id,
	g_title
FROM gradeables
WHERE
	gradeable_type=0"
ORDER BY gradeable_id DESC;
SQL;
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ END DISABLED CODE */

	\lib\Database::beginTransaction();
	\lib\Database::query($sql);
	\lib\Database::commit();

	return \lib\Database::rows();
}

/* END FUNCTION retrieve_gradeables_from_db() =============================== */

function retrieve_students_from_db($gradeable_id = 0) {

	//Old Schema
	$sql = <<<SQL
SELECT
	students.student_rcs,
	students.student_first_name,
	students.student_last_name,
	late_day_exceptions.ex_late_days
FROM students
FULL OUTER JOIN
	late_day_exceptions
ON
	students.student_rcs=late_day_exceptions.ex_student_rcs
WHERE
	late_day_exceptions.ex_rubric_id=?
AND
	late_day_exceptions.ex_late_days IS NOT NULL
AND
	late_day_exceptions.ex_late_days>0
ORDER BY students.student_rcs ASC;
SQL;
	
	//New Schema
	//FIXME: update user.user_group property to equal value representing students.
/* DISABLED CODE ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	$sql = <<<SQL
SELECT
	users.user_email,
	users.user_firstname,
	users.user_lastname,
	late_day_exceptions.late_day_exceptions
FROM users
FULL OUTER JOIN late_day_expceptions
ON users.user_id=late_day_exceptions.student_id
WHERE
	late_day_exceptions.assignmemt_id=?
AND
	users.user_group=0
AND
	late_day_exceptions.ex_late_days IS NOT NULL
AND
	late_day_exceptions.ex_late_days>0
ORDER BY users.user_email ASC;
SQL;
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ END DISABLED CODE */

	\lib\Database::beginTransaction();
	\lib\Database::query($sql, array($gradeable_id));
	\lib\Database::commit();

	return \lib\Database::rows();
}

/* END FUNCTION retrieve_students_from_db() ================================= */

function upsert(array $data) {

	echo nl2br("\n\n\n\n\n" . count($data));

	//q.v. http://stackoverflow.com/questions/17267417/how-to-upsert-merge-insert-on-duplicate-update-in-postgresql

	//Old Schema
	$sql = array();
	
	//TEMPORARY table to hold all new values that will be "upserted"
	$sql['temp'] = <<<SQL
CREATE TEMPORARY TABLE temp
	(student_rcs VARCHAR(255),
	gradeable_id INTEGER,
	late_days INTEGER)
ON COMMIT DROP;
SQL;

	//Needed for "upsert" records
	for ($i=0; $i<count($data); $i++) {
		$sql["data_{$i}"] = <<<SQL
INSERT INTO temp VALUES (?,?,?);
SQL;
	}

	//LOCK will prevent sharing collisions while upsert is in process.
	$sql['lock'] = <<<SQL
LOCK TABLE late_day_exceptions IN EXCLUSIVE MODE;
SQL;

	//UPDATE will only occur when a record already exists.
	$sql['update'] = <<<SQL
UPDATE
	late_day_exceptions
SET
	ex_late_days=temp.late_days
FROM
	temp
WHERE
	late_day_exceptions.ex_student_rcs=temp.student_rcs
AND
	late_day_exceptions.ex_rubric_id=temp.gradeable_id;
SQL;

	//INSERT will only occur when a record does not exist
	$sql['insert'] = <<<SQL
INSERT INTO
	late_day_exceptions
	(ex_student_rcs,
	ex_rubric_id,
	ex_late_days)
SELECT
	temp.student_rcs,
	temp.gradeable_id,
	temp.late_days
FROM 
	temp 
LEFT OUTER JOIN
	late_day_exceptions
ON
	late_day_exceptions.ex_student_rcs=temp.student_rcs
AND
	late_day_exceptions.ex_rubric_id=temp.gradeable_id
WHERE
	late_day_exceptions.ex_student_rcs IS NULL
OR
	late_day_exceptions.ex_rubric_id IS NULL;
SQL;

	/* TO DO: Need SQL for new Schema */

	//Begin!
	\lib\Database::beginTransaction();
	\lib\Database::query($sql['temp']);
	
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

	static private $utf8_styled;
	static private $utf8_checkmark;
	static private $view;
	
	public function __construct() {
		$this->utf8_styled_x  = "&#x2718";
		$this->utf8_checkmark = "&#x2714";
		$this->view = array();
		
		$this->view['head'] = <<<HTML
<div id="container" style="width:100%; margin-top:40px;">
<div class="modal hide fade in" style="display:block; margin-top:5%; z-index:100;">
<div class="modal-header">
<h3>Add Late Day Exceptions</h3>
</div>
HTML;

		$this->view['tail'] = <<<HTML
</div>
</div>	
HTML;

		$this->view['bad_upload'] = <<<HTML
<div class="modal-body">
<p><em style="color:red; font-weight:bold; font-style:normal;">
{$this->utf8_styled_x} Could not process CSV upload.</em>
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

		$this->view['upsert_confirmed'] = <<<HTML
<div class="modal-body">
<p><em style="color:green; font-weight:bold; font-style:normal;">
{$this->utf8_checkmark} Late days are updated.</em>
</div>
HTML;

		$this->view['unauthorized'] = <<<HTML
<div class="modal-body" style="padding-top:20px; padding-bottom:20px;">
<p><em style="color:red; font-weight:bold; font-style:normal;">
{$this->utf8_styled_x} You are not permitted to add late day exceptions.</em>
</div>
HTML;

	}
	
	public function configure_form($g_id, $db_data) {
		$this->view['form'] = <<<HTML
<div class="modal-body" style="padding-top:20px; padding-bottom:20px;">
<form action="admin-latedays-exceptions.php" method="POST">
<p>Select Rubric:
<select name="selected_gradeable" onchange="this.form.submit()">
HTML;

		foreach($db_data as $index => $gradeable) {
		
			if ($g_id == $gradeable[0]) {
				$this->view['form'] .= <<<HTML
<option value="g_{$gradeable[0]}" selected="selected">{$gradeable[1]}</option>
HTML;
			} else {
				$this->view['form'] .= <<<HTML
<option value="g_{$gradeable[0]}">{$gradeable[1]}</option>
HTML;
			}
		}

		$this->view['form'] .= <<<HTML
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
	
	public function configure_table($db_data) {
		if (!is_array($db_data) || count($db_data) < 1) {
		//No late days in DB -- indicate as much.

			$this->view['student_review_table'] = <<<HTML
<div class="modal-body" style="padding-top:20px; padding-bottom:20px;">
<p style="font-weight:bold; font-size:1.2em;">No late day exceptions are currently entered for this assignment.
</div>
HTML;
		} else {
		//Late days found in DB -- build table to display

			//Table HEAD
			$this->view['student_review_table'] = <<<HTML
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
				$this->view['student_review_table'] .= <<<HTML
<tr>
<td style="background:{$cell_color[$index%2]};">{$record[0]}</td>
<td style="background:{$cell_color[$index%2]};">{$record[1]}</td>
<td style="background:{$cell_color[$index%2]};">{$record[2]}</td>
<td style="background:{$cell_color[$index%2]};">{$record[3]}</td>
</tr>
HTML;
			}

			//Table TAIL
			$this->view['student_review_table'] .= <<<HTML
</table>
</div>
HTML;
		}
	}

	public function display($state) {
	
		switch($state) {
		case 'unauthorized':
			echo $this->view['head']         .
			     $this->view['unauthorized'] .
			     $this->view['tail'];
			break;
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
		case 'upsert_confirmed':
			echo $this->view['head']                 .
				 $this->view['form']                 . 
				 $this->view['upsert_confirmed']     . 
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

/* END CLASS local_view ===================================================== */
/* EOF ====================================================================== */
?>
