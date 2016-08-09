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

//Validate submission
//Is Student ID submitted (required!)
if (isset($_POST['student_id']) && $_POST['student_id'] !== "") {

	//Is this a lookup or upsert?  (determined in *'d fields are filled or not)
	if ((isset($_POST['first_name']) && $_POST['first_name'] === "") &&
	    (isset($_POST['last_name'])  && $_POST['last_name']  === "") &&
	    (isset($_POST['email'])      && $_POST['email']      === "")) {

		//Do DB lookup for student by Student ID
	} else {
		//Validate all fields
		//No validation on student id as pattern varies among Universities
		if (preg_match("~^[a-zA-Z.'`\- ]+$~",                               $_POST['first_name']) &&
		    preg_match("~^[a-zA-Z.'`\- ]+$~",                               $_POST['last_name'] ) &&
		    preg_match("~^[a-zA-Z0-9._\-]+@[a-zA-Z0-9.\-]+.[a-zA-Z]{2,}$~", $_POST['email']     ) &&
		    preg_match("~^[0-9]+$~",                                        $_POST['r_section'] )) {
		    $is_validated = true;
		} else {
			$is_validated = false;
		}

		if ($is_validated) {		
			//Do upsert
		} else {
			$state = 'invalid_student_info';
		}
	}
}

//display
$view->display($state);

/* END Process -------------------------------------------------------------- */

include "../footer.php";
exit;

/* END MAIN ================================================================= */

class local_view {
//View class for admin-latedays.php

	//Properties
	//Class constants to represent unicode styled X and checkmark symbols.
	const UTF8_STYLED_X  = "&#x2718";
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
<div id="container" style="width:100%; margin-top:40px;">
<div class="modal hide fade in" style="display:block; margin-top:5%; z-index:100;">
<div class="modal-header">
<h3>Review Individual Student Enrollment</h3>
</div>
<div class="modal-body" style="padding-top:10px; padding-bottom:10px;">
HTML;

		self::$view['tail'] = <<<HTML
</div>
</div>
</div>
HTML;

		self::$view['student_not_found'] = <<<HTML
<p style="margin:0; padding-bottom:20px;"><em style="color:red; font-weight:bold; font-style:normal;">
{$utf8_styled_x} Student not found.</em>
HTML;

		self::$view['missing_student_id'] = <<<HTML
<p style="margin:0; padding-bottom:20px;"><em style="color:red; font-weight:bold; font-style:normal;">
{$utf8_styled_x} Student ID field required.</em>
HTML;

		self::$view['invalid_student_info'] = <<<HTML
<p style="margin:0; padding-bottom:20px;"><em style="color:red; font-weight:bold; font-style:normal;">
{$utf8_styled_x} Invalid student information.</em>
HTML;

		self::$view['upsert_done'] = <<<HTML
<p style="margin:0; padding-bottom:20px;"><em style="color:green; font-weight:bold; font-style:normal;">
{$utf8_checkmark} Late days are updated.</em>
HTML;

		//Build form with default values
		self::fill_form();
	}
	
/* END CLASS CONSTRUCTOR ---------------------------------------------------- */	

	public function fill_form($db_data = null) {
	//IN:  data from database used to build form
	//OUT: no return, although form data is propogated as a class property
	//PURPOSE: Craft HTML required to display the form.
	//NOTE:    Instead of creating a table to display a student's info, the info
	//         is filled into the form for easy and quick tweaking.

		//validate data parameters and/or set defaults.
		if (!is_array($db_data)) {
			$db_data = array(
				'student_id' => "",
				'first_name' => "",
				'last_name'  => "",
				'email'      => "",
				'r_section'  => "",
				'is_manual'  => true
			);
		} else {
			//Some fields may be OK, and others may need to be set to default.
			//Check each individually to be thorough.
			if (!isset($db_data['student_id'])) {
				$db_data['student_id'] = "";
			}

			if (!isset($db_data['first_name'])) {
				$db_data['first_name'] = "";
			}

			if (!isset($db_data['last_name'])) {
				$db_data['last_name'] = "";
			}

			if (!isset($db_data['email'])) {
				$db_data['email'] = "";
			}

			if (!isset($db_data['r_section'])) {
				$db_data['r_section'] = "";
			}

			if (!isset($db_data['is_manual'])) {
				$db_data['is_manual'] = true;
			}
		}

		//Build form
		//Determine if is_manual checkbox should be checked by default
		$is_checked = ($db_data['is_manual']) ? "checked" : "";
		
		//Construct rest of form.  Note string expansions in HTML: {}
		self::$view['form'] = <<<HTML
<form action="admin-single-student-review.php" method="POST" enctype="multipart/form-data">
<div style="width:30%; display:block;">Student ID:<br><input type="text" name="student_id" value="{$db_data['student_id']}" style="width:95%;"></div>
<p>To only lookup a student's enrollment, leave blank all fields marked <span style="color:red;">*</span>.
<div style="width:30%; display:inline-block; vertical-align:top; padding-right:10px;">First Name: <span style="color:red;">*</span><br><input type="text" name="first_name" value="{$db_data['first_name']}" style="width:95%;"></div>
<div style="width:30%; display:inline-block; vertical-align:top; padding-right:10px;">Last Name: <span style="color:red;">*</span><br><input type="text" name="last_name" value="{$db_data['last_name']}" style="width:95%;"></div>
<div style="width:30%; display:inline-block; vertical-align:top; padding-right:10px;">Email: <span style="color:red;">*</span><br><input type="text" name="email" value="{$db_data['email']}" style="width:95%;"></div>
<div style="width:30%; display:inline-block; vertical-align:top; padding-right:10px;">Registered Section:<br><input type="text" name="r_section" value="{$db_data['r_section']}" style="width:95%;"></div>
<div style="width:50%; display:inline-block; vertical-align:top; padding-right:5px;"><p><input type="checkbox" name="manual" style="position:inherit; text-align:center; padding-top:1em;" {$is_checked}> Manually Registered Student<br>
	<p><span style="color:red;">*</span> Required <span style="font-style:italic;">only</span> for add/updating student.</div>
<div style="display:inline-block; vertical-align:top; padding-top:1.5em;"><input type="submit" name="submit" value="Submit"></div>
</form>
HTML;

}
	
/* END CLASS METHOD fill_form() --------------------------------------------- */

	public function display($state) {
	//IN:  Current "display state" determined in MAIN process
	//OUT: No return, although ALL crafted HTML is sent to browser
	//PURPOSE:  Display appropriate page contents.
	
		switch($state) {
		case 'student_not_found':
			echo self::$view['head']              .
   				 self::$view['form']              . 
			     self::$view['student_not_found'] .
			     self::$view['tail'];
			break;
		case 'missing_student_id':
			echo self::$view['head']                .
				 self::$view['form']                . 
				 self::$view['missing_student_id']  . 
			     self::$view['tail'];
			break;
		case 'missing_required_fields':
			echo self::$view['head']                  .
				 self::$view['form']                  . 
				 self::$view['invalid_student_info']  . 
			     self::$view['tail'];
			break;
		case 'upsert_done':
			echo self::$view['head']        .
				 self::$view['form']        . 
				 self::$view['upsert_done'] . 
			     self::$view['tail'];
			break;
		default:
			echo self::$view['head'] .
				 self::$view['form'] . 
			     self::$view['tail'];
			break;
		}
	}
}
/* END CLASS METHOD display() ----------------------------------------------- */
/* END CLASS local_view ===================================================== */
/* EOF ====================================================================== */
?>