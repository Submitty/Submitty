<?php

//Author: Peter Bailie, Systems Programmer, RPI Computer Science, August 2016

/* MAIN ===================================================================== */

include "../header.php";

check_administrator();

/* Process ------------------------------------------------------------------ */

$view = new local_view();

//$state affects what's displayed in browser.
//Default state.
$state = "";

//Validate submission
//Is User ID submitted? (required!)
if (isset($_POST['user_id']) && $_POST['user_id'] !== "") {

	//Is this a lookup or upsert?
	//(determined whether *'d fields are filled or not)
	if ((isset($_POST['first_name']) && $_POST['first_name'] === "") &&
	    (isset($_POST['last_name'])  && $_POST['last_name']  === "") &&
	    (isset($_POST['email'])      && $_POST['email']      === "")) {

		//Do DB lookup for user by user ID
		$row = lookup_user_in_db($_POST['user_id']);
		if (!empty($row)) {
			$view->fill_form($row);		
		} else {
			$state = "user_not_found";
		}		
	} else {
		//Do form data validation in preperation for upsert.
		//No validation on user id as pattern varies among Universities.
		//No validation on is_manual checkbox as it is not SET when not ticked.
		//Email regex should match most cases, including unusual TLDs and
		//      IPv4 addresses.
		//VERY LOW PRIORITY TO DO: adjust regex to validate IPv6 addresses.
		if (preg_match("~^[a-zA-Z.'`\- ]+$~", $_POST['first_name']) &&
		    preg_match("~^[a-zA-Z.'`\- ]+$~", $_POST['last_name'] ) &&
		    preg_match("~^[a-zA-Z0-9._\-]+@[a-zA-Z0-9.\-]+.[a-zA-Z0-9]+$~", $_POST['email']) &&
		    preg_match("~^[1-4]{1}$~", strval($_POST['user_group'])) &&
			preg_match("~^$|^[0-9]+$~", $_POST['r_section'])) {
		    $is_validated = true;
		} else {
			$is_validated = false;
		}

		if ($is_validated) {
			//upsert's argument expects 2D array.
			//NULL values must be passed as keyword null, but true/false values
			//must be passed as 'true'/'false' strings.  Otherwise, DB throws
			//an exception.
			upsert(array( array( $_POST['user_id'],
			                     $_POST['first_name'],
			                     $_POST['last_name'],
			                     $_POST['email'],
								 $_POST['user_group'],
			                    ($_POST['r_section'] === "") ? null : $_POST['r_section'],
		                        (isset($_POST['manual_flag']) && $_POST['manual_flag'] === 'on') ? 'true' : 'false' )));
		    $state = 'upsert_done';
		} else {
			$state = 'invalid_user_info';
		}
	}
}

//display
$view->display($state);

/* END Process -------------------------------------------------------------- */

include "../footer.php";
exit;

/* END MAIN ================================================================= */

function lookup_user_in_db($user_id) {

	$sql = <<<SQL
SELECT
	user_id,
	user_firstname,
	user_lastname,
	user_email,
	user_group,
	registration_section,
	manual_registration
FROM users
WHERE user_id=?
SQL;

		\lib\Database::query($sql, array($user_id));
		return \lib\Database::row();
}

/* END FUNCTION lookup_user_in_db() ====================================== */

function upsert(array $data) {
//IN:  Data to be "upserted" as 2D array.
//OUT: No return.  This is assumed to work.  (Server should throw an exception
//     if this process fails)
//PURPOSE:  "Update/Insert" data into the database.  Code capable of "batch"
//          upserts.

/* -----------------------------------------------------------------------------
 * This SQL code was adapted from upsert discussion on Stack Overflow and is
 * meant to be compatible with PostgreSQL prior to v9.5.
 *
 * 	q.v. http://stackoverflow.com/questions/17267417/how-to-upsert-merge-insert-on-duplicate-update-in-postgresql
 * -------------------------------------------------------------------------- */
 
/* -----------------------------------------------------------------------------
 * SUGGESTION: For future-facing maintenance, maintain this code for batch 
 *             transactions even though this page currently transacts only 
 *             single rows of information.
 * -------------------------------------------------------------------------- */

	$sql = array();
	
	//TEMPORARY table to hold all new values that will be "upserted"
	$sql['temp_table'] = <<<SQL
CREATE TEMPORARY TABLE temp
	(user_id     VARCHAR(255),
	 first_name  VARCHAR(255),
	 last_name   VARCHAR(255),
	 email       VARCHAR(255),
	 user_group  INTEGER,
	 r_section   INTEGER,
	 manual_flag BOOLEAN)
ON COMMIT DROP;
SQL;

	//INSERT new data into temporary table -- prepares all data to be upserted
	//in a single DB transaction.
	for ($i=0; $i<count($data); $i++) {
		$sql["data_{$i}"] = <<<SQL
INSERT INTO temp VALUES (?,?,?,?,?,?,?);
SQL;

	}

	//LOCK will prevent sharing collisions while upsert is in process.
	$sql['lock'] = <<<SQL
LOCK TABLE users IN EXCLUSIVE MODE;
SQL;

	//This portion ensures that UPDATE will only occur when a record already exists.
	$sql['update'] = <<<SQL
UPDATE users
SET
	user_firstname=temp.first_name,
	user_lastname=temp.last_name,
	user_email=temp.email,
	user_group=temp.user_group,
	registration_section=temp.r_section,
	manual_registration=temp.manual_flag
FROM temp
WHERE users.user_id=temp.user_id;
SQL;

	//This portion ensures that INSERT will only occur when data record is new.
	$sql['insert'] = <<<SQL
INSERT INTO users
	(user_id,
	 user_firstname,
	 user_lastname,
	 user_email,
	 user_group,
	 registration_section,
	 manual_registration)
SELECT
	temp.user_id,
	temp.first_name,
	temp.last_name,
	temp.email,
	temp.user_group,
	temp.r_section,
	temp.manual_flag
FROM temp 
LEFT OUTER JOIN users
	ON users.user_id=temp.user_id
WHERE users.user_id is NULL;
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
<h3>Review Individual User Enrollment</h3>
</div>
<div class="modal-body" style="padding-top:10px; padding-bottom:10px;">
HTML;

		self::$view['tail'] = <<<HTML
</div>
</div>
</div>
HTML;

		self::$view['user_not_found'] = <<<HTML
<p style="margin:0;"><em style="color:red; font-weight:bold; font-style:normal;">
{$utf8_styled_x} User not found.</em>
HTML;

		self::$view['invalid_user_info'] = <<<HTML
<p style="margin:0;"><em style="color:red; font-weight:bold; font-style:normal;">
{$utf8_styled_x} Invalid user information.</em>
HTML;

		self::$view['upsert_done'] = <<<HTML
<p style="margin:0;"><em style="color:green; font-weight:bold; font-style:normal;">
{$utf8_checkmark} User info is updated.</em>
HTML;

		//Build form with default values
		self::fill_form();
	}
	
/* END CLASS CONSTRUCTOR ---------------------------------------------------- */	

	public function fill_form($db_data = array("","","","",3,"",true)) {
	//IN:  data from database used to build form.
	//OUT: no return, although form data is propogated as a class property
	//PURPOSE: Craft HTML required to display the form.
	//NOTE:    Instead of creating a table to display a user's info, the info
	//         is filled into the form for easy and quick tweaking.

/* -----------------------------------------------------------------------------
 * $db_data expected indices:
 * [0]: (string) user id
 * [1]: (string) user first name
 * [2]: (string) user last name
 * [3]: (string) user email
 * [4]: (int)    user group
 * [5]: (string) user registered section
 * [6]: (bool)   user "manual registration" flag
 * -------------------------------------------------------------------------- */

 		//Build form
		//Javascript to clear textboxes when user_id textbox changes.
		$js = <<<JS
document.getElementById('first_name').value='';
document.getElementById('last_name').value='';
document.getElementById('email').value='';
document.getElementById('r_section').value='';
JS;

		//Construct selectbox (drop-down) box.
		$group_names = array( 1 => "Course Instructor",
		                      2 => "Course TA Grader",
							  3 => "Section TA Grader",
							  4 => "Student" );
		
		$ug_select = <<<HTML
<div style="width:30%; display:inline-block; vertical-align:top;">Group:<br><select id="user_group" name="user_group">
HTML;
		
		//Determine default selection for selectbox while building option list.
		for ($i = 1; $i <= 4; $i++) {
			$d_selected = ($i == $db_data[4]) ? ' selected="selected"' : '';
			$ug_select .= <<<HTML
<option value="{$i}"{$d_selected}>{$group_names[$i]}</option>
HTML;
		
		}
		
		$ug_select .= <<<HTML
</select>
</div>
HTML;

		//FINISHED constructing selectbox.
		//Determine if 'manual_flag' checkbox should be checked by default
		$is_checked = ($db_data[6]) ? "checked" : "";
			
		//Construct form.  Note string expansions in HTML: {}
		self::$view['form'] = <<<HTML
<form action="admin-single-user-review.php" method="POST" enctype="multipart/form-data">
<div style="width:30%; display:inline-block; vertical-align:top; padding-right:10px;">User ID:<br><input type="text" id="user_id" name="user_id" value="{$db_data[0]}" style="width:95%;" oninput="{$js}"></div>
<div style="width:30%; display:inline-block; vertical-align:top; padding-right:10px;"> </div>
{$ug_select}
<p>To only lookup a user's enrollment, leave blank all fields marked <span style="color:red;">*</span>.
<div style="width:30%; display:inline-block; vertical-align:top; padding-right:10px;">First Name: <span style="color:red;">*</span><br><input type="text" id="first_name" name="first_name" value="{$db_data[1]}" style="width:95%;"></div>
<div style="width:30%; display:inline-block; vertical-align:top; padding-right:10px;">Last Name: <span style="color:red;">*</span><br><input type="text" id="last_name" name="last_name" value="{$db_data[2]}" style="width:95%;"></div>
<div style="width:30%; display:inline-block; vertical-align:top; padding-right:10px;">Email: <span style="color:red;">*</span><br><input type="text" id="email" name="email" value="{$db_data[3]}" style="width:95%;"></div>
<div style="width:30%; display:inline-block; vertical-align:top; padding-right:10px;">Registered Section:<br><input type="text" id="r_section" name="r_section" value="{$db_data[5]}" style="width:95%;"></div>
<div style="width:50%; display:inline-block; vertical-align:top; padding-right:5px;"><p><input type="checkbox" id="manual_flag" name="manual_flag" style="position:inherit; text-align:center; padding-top:1em;" {$is_checked}> Manually Registered User<br>
	<p><span style="color:red;">*</span> Required <span style="font-style:italic;">only</span> for add/updating user.</div>
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
		case 'user_not_found':
			echo self::$view['head']           .
			     self::$view['form']           . 
			     self::$view['user_not_found'] .
			     self::$view['tail'];
			break;
		case 'invalid_user_info':
			echo self::$view['head']              .
			     self::$view['form']              . 
			     self::$view['invalid_user_info'] . 
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
