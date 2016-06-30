<?php

//Author: Peter Bailie, Systems Programmer, RPI Computer Science

/* MAIN ===================================================================== */

include "../header.php";

//Retrieve view data
$view = get_views();

echo $view['head'];

check_administrator();

if($user_is_administrator) {
//User is administrator -- proceed with process.

	/* Process -------------------------------------------------------------- */
	//Check POST

	//print form
	echo $view['form'];
	
	//print student table
	echo $view['student_review_table'];
	
	/* END Process ---------------------------------------------------------- */

} else {
//User is NOT administrator -- operation not permitted.  Display error.

    echo $view['unauthorized'];
}

echo $view['tail'];
include "../footer.php";
exit;

/* END MAIN ================================================================= */

function retrieve_gradeables_from_db() {

	//Old Schema
	$sql = <<<SQL
SELECT
	rubric_id,
	rubric_name
FROM rubrics;
SQL;

	\lib\Database::query($sql);

	//New Schema
	//FIXME: update 'gradeable_type' property to locate any gradeable of which late days are permissible.
/* DISABLED CODE ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	$sql = <<<SQL
SELECT
	gradeable_id,
	g_title
FROM gradeables
WHERE
	gradeable_type=0";
SQL;

	\lib\Database::query($sql);
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ END DISABLED CODE */

	return \lib\Database::rows();
}

/* ========================================================================== */

function retrieve_students_from_db($gradeable_id = 0) {

	//Old Schema
	$sql = <<<SQL
SELECT
	students.student_rcs,
	students.student_first_name,
	students.student_last_name,
	late_day_exceptions.ex_late_days
FROM students
FULL OUTER JOIN late_day_exceptions
ON students.student_rcs=late_day_exceptions.ex_student_rcs
WHERE
	late_day_exceptions.ex_rubric_id={$gradeable_id}
AND
	late_day_exceptions.ex_late_days IS NOT NULL
AND
	late_day_exceptions.ex_late_days>0;
SQL;

	\lib\Database::query($sql);
	
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
	late_day_exceptions.assignmemt_id={$gradeable_id}
AND
	users.user_group=0
AND
	late_day_exceptions.ex_late_days IS NOT NULL
AND
	late_day_exceptions.ex_late_days>0;
SQL;

	\lib\Database::query($sql);
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ END DISABLED CODE */

	return \lib\Database::rows();
}

/* ========================================================================== */

function upsert(array $data) {

}

function get_views() {
	$utf8_styled_x  = "&#x2718";
	$utf8_checkmark = "&#x2714";

	$view = array();
	$view['head'] = <<<HTML
<div id="container" style="width:100%; margin-top:40px;">
<div class="modal hide fade in" style="display:block; margin-top:5%; z-index:100;">
<div class="modal-header">
<h3>Add Late Day Exceptions</h3>
</div>
HTML;

	$view['tail'] = <<<HTML
</div>
</div>	
HTML;

	//BUILD input form
	//Retrieve rubrics/gradeables DB data
	$rubrics = retrieve_gradeables_from_db();
	
	$view['form'] = <<<HTML
HTML;
         
	//BUILD student review table of existing late day exceptions
	$late_day_records = retrieve_students_from_db(/*$g_id*/ 3); //TODO: $g_id retrieved from POST
	
	if (!is_array($late_day_records) || count($late_day_records) < 1) {
	//No late days in DB -- indicate as much.

		$view['student_review_table'] = <<<HTML
<div class="modal-body" style="padding-top:20px; padding-bottom:20px;">
<p style="font-weight:bold; font-size:1.2em;">No late day exceptions are currently entered for this assignment.
</div>
HTML;
	} else {
	//Late days found in DB -- build table to display

		//Table HEAD
		$view['student_review_table'] = <<<HTML
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
		foreach ($late_day_records as $index => $record) {
			$view['student_review_table'] .= <<<HTML
<tr>
<td style="background:{$cell_color[$index%2]};">{$record[0]}</td>
<td style="background:{$cell_color[$index%2]};">{$record[1]}</td>
<td style="background:{$cell_color[$index%2]};">{$record[2]}</td>
<td style="background:{$cell_color[$index%2]};">{$record[3]}</td>
</tr>
HTML;
	}

		//Table TAIL
		$view['student_review_table'] .= <<<HTML
</table>
</div>
HTML;
	}
	
	//%s = student_id.  Output with printf().
	$view['student_not_found'] = <<<HTML
<div class="modal-body" style="padding-top:20px; padding-bottom:20px;">
<p><em style="color:red; font-weight:bold; font-style:normal;">{$utf8_styled_x} Student %s not found.</em>
</div>
HTML;

	//%s = student_id, %d = late days, %s = gradeable.  Output with printf().
	$view['confirmed'] = <<<HTML
<div class="modal-body" style="padding-top:20px; padding-bottom:20px;">
<p><em style="color:green; font-weight:bold; font-style:normal;">
{$utf8_checkmark} Student %s now has %d late days for %s.
</em>
</div>
HTML;

	$view['unauthorized'] = <<<HTML
<div class="modal-body" style="padding-top:20px; padding-bottom:20px;">
<p><em style="color:red; font-weight:bold; font-style:normal;">{$utf8_styled_x} You are not permitted to add late day exceptions.</em>
</div>
HTML;

	return $view;
}

/* EOF ====================================================================== */
?>