<?php

//Author: Peter Bailie, Systems Programmer, RPI Computer Science

/* MAIN ===================================================================== */

include "../header.php";

//Retrieve view data
$view = array();
set_views($view);

echo $view['head'];

check_administrator();

if($user_is_administrator) {
//User is administrator -- proceed with process.

	/* Process -------------------------------------------------------------- */
	//Retrieve rubrics/gradeables DB data
	$rubrics = retrieve_gradeables_from_db();

	
	//print form

	
	//Build student table
	$students = retrieve_students_from_db($g_id); //TODO: g_id retrieved from POST
	$view['student_form_body'] = '';
	foreach ($students as $count) {
		//TODO: build HTML table from data
	}


	//print student table


	
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

function retrieve_students_from_db($gradeable_id) {

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

function upsert($student_id, $late_days) {

}

function set_views(array &$view) {

	$view['head'] = <<<HTML
<div id="container" style="width:100%; margin-top:40px;">
<div class="modal hide fade in" style="display:block; margin-top:5%; z-index:100;">
<div class="modal-header">
<h3>Add Late Day Exceptions</h3>
</div>
HTML;

	$view['tail'] = <<<HTML
</div></div>	
HTML;

	//%s = student_id.  Output with printf().
	$view['student_not_found'] = <<<HTML
<div class="modal-body" style="padding-top:20px; padding-bottom:20px;">
<em style="color:red; font-weight:bold; font-style:normal;">&#x2718 Student %s not found.</em>
</div>
HTML;

	$view['form_head'] = <<<HTML
HTML;
         
	$view['form_tail'] = <<<HTML
HTML;

	$view['student_review_head'] = <<<HTML
<div class="modal-body" style="padding-top:20px; padding-bottom:20px;">
<h4>Students With Late Days</4>
HTML;

	$view['student_review_tail'] = <<<HTML
</div>
HTML;

	//%s = student_id, %d = late days, %s = gradeable.  Output with printf().
	$view['confirmed'] = <<<HTML
<div class="modal-body" style="padding-top:20px; padding-bottom:20px;">
<em style="color:green; font-weight:bold; font-style:normal;">
&#x2714 Student %s now has %d late days for %s.
</em>
</div>
HTML;

	$view['unauthorized'] = <<<HTML
<div class="modal-body" style="padding-top:20px; padding-bottom:20px;">
<em style="color:red; font-weight:bold; font-style:normal;">&#x2718 You are not permitted to add late day exceptions.</em>
</div>
HTML;
}

/* EOF ====================================================================== */
