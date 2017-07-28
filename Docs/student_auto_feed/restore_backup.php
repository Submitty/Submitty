#!/usr/bin/env php
<?php

/* HEADING ---------------------------------------------------------------------
 *
 * Submitty Student Information Auto Feed -- restore_backup.php
 * By Peter Bailie, Systems Programmer (RPI dept of computer science)
 *
 * Requires minimum PHP version 5.4 with the pgsql extension.  This code is
 * intended to be executable and run from the CLI by a sysadmin.
 *
 * submitty_student_auto_feed has several data validation checks intended to
 * prevent a bad data feed from corrupting the users data of any course, but in
 * the event that a bad data feed got past the validation checks and corrupts
 * users table data of one or more Submitty courses, this rollback tool can be
 * used to revert users data back to a specific date.  These backups are written
 * by submitty_users_data_backup.php.
 *
 * There is no log module, but pertinant activity and error messages are written
 * to STDOUT and STDERR respectively.
 *
 * Attempts have been made to generalize this code, but different Universities
 * have different information systems, some of which cannot be accounted for.
 *
 * THIS SOFTWARE IS PROVIDED AS IS AND HAS NO GUARANTEE THAT IT IS SAFE OR
 * COMPATIBLE WITH YOUR UNIVERSITY'S INFORMATION SYSTEMS.  THIS IS ONLY A CODE
 * EXAMPLE FOR YOUR UNIVERSITY'S SYSYTEM'S PROGRAMMER TO PROVIDE AN
 * IMPLEMENTATION.  IT MAY REQUIRE SOME ADDITIONAL MODIFICATION TO SAFELY WORK
 * WITH YOUR UNIVERSITY'S AND/OR DEPARTMENT'S INFORMATION SYSTEMS.
 *
 * April 13, 2017
 * NOTE: Current Submitty design spec requires that there are no DELETE queries
 *       are ever sent to the databases.  Therefore, this rollback tool will
 *       "upsert" (insert/update) from data backup.  This should correct any
 *       corrupted data row with matching user_id columns (the primary key).
 *
 * -------------------------------------------------------------------------- */

/* HOW TO USE ------------------------------------------------------------------
 *
 * This tool is a collection of static classes with inheritance relationships.
 * This file is intended to be invoked as executable from the command line.
 * Sudo is not explicity required, but the user invoking this tool does need to
 * have read access to the backup data files and the keyfile (when encryption is
 * enabled).
 *
 * -------------------------------------------------------------------------- */

/* DRIVER =================================================================== */

require "config.php";

switch(false) {
case restore_backup::init($argc, $argv):
	exit(1);
case restore_backup::process_backup():
	exit(1);
}

exit(0);

/* PARENT CLASS RESTORE_BACKUP ============================================== */
class restore_backup {
/* Static class that controls the data restore system.  Console (view) and
 * database static classes inherit this class.
 */

	private static $init   = false;
	private static $data   = array( 'users'                 => array(),
	                                'sections_rotating'     => array(),
	                                'sections_registration' => array() );
	private static $course = null;
	private static $date   = null;

	private static $backup_root_folder = SUBMITTY_AUTO_FEED_BACKUP;
	private static $encryption_enabled = ENABLE_BACKUP_ENCRYPTION;
	private static $key_file           = ENCRYPTION_KEY_FILE;

	public static function init($arg_c, $arg_v) {

		if (PHP_SAPI !== 'cli') {
			die("This is a command-line tool.");
		}

		if ($arg_c === 3) {
			//course parameter should be lowercase
			$arg_v[1] = strtolower($arg_v[1]);

			//check for common ways to request help dialog.
			switch($arg_v[1]) {
			case '-h':
			case '--help':
			case 'help':
				console::print_help();
				self::$init = false;
				break;
			default:
				//Not requesting help -- validate CLI params.
				self::$init = self::validate_cli_params($arg_v);
				break;
			}
		} else {
			//Wrong number of CLI params.  Print help.
			console::print_help();
			self::$init = false;
		}

		return self::$init;
	}

	public static function process_backup() {
		if (self::$init === false) {
			console::print_error("Rollback tool not initialized -- aborting.");
			return false;
		}

		//Open/read backup file
		$root   = self::$backup_root_folder;
		$course = self::$course;
		$date   = self::$date['year'] . self::$date['month'] . self::$date['day'];
		$file   = "{$root}{$course}/{$date}.backup";

		$data = file_get_contents($file);
		if ($data === false) {
			console::print_error("Could not open/read backup file '{$date}.backup'");
			return false;
		}

		//Do decryption, if enabled.
		if (self::$encryption_enabled) {
			if (self::decryption($data) === false) {
				//Decryption method already printed error.
				return false;
			}
		}

		//Before we restore the users table, let's make sure this is OK.
		//(Last Chance!)
		if (console::prompt_restore(self::$course, self::$date) === false) {
			console::print_message("Aborting.");
			//*technically* not a failure, but we are stopping here.
			return true;
		}

		//Reorganize data into rows/columns.
		$data = explode(chr(10), $data);
		foreach($data as &$row) {
			$row = explode(chr(9), $row);
		} unset($row);

		//First row is header -- discard it.
		array_shift($data);

		//Final row will always be empty -- discard it.
		array_pop($data);

		//New "final row" is now section_rotating_id foreign keys.
		$tmp = array_pop($data);
		self::$data['sections_rotating'] = (!empty($tmp[0])) ? 	array_chunk($tmp, 1) : array();

		//New "final row" is now section_registration_id foreign keys.
		$tmp = array_pop($data);
 		self::$data['sections_registration'] = (!empty($tmp[0])) ? 	array_chunk($tmp, 1) : array();

		//New "final row" is now a header for foreign keys data -- discard it.
		array_pop($data);

		//Remaining $data is all users table data.
		if (empty($data)) {
			console::print_message("Backup file has no users data.");
			return true;
		}

		//Check for null char to convert to PHP NULL type.
		//(null char and PHP NULL type are not the same)
		foreach($data as &$row) {
			foreach($row as &$col) {
				if ($col === chr(0)) {
					$col = null;
				}
			} unset($col);
		} unset ($row);

		self::$data['users'] = $data;

		//Do update of users table and return its success result.
		return self::restore_database();
	}

	private static function validate_cli_params($arg_v) {

		//Validate $arg_v[1], which is the course specified.
		//Scans backup data folder and discards any entries that start with '.'
		//such as '.', '..', and any "hidden" folders.
		$folder_list = array_filter(scandir(self::$backup_root_folder),
			function($elem) {
				return !preg_match("~^\..*$~", $elem);
			}
		);
		if (array_search($arg_v[1], $folder_list) === false) {
			console::print_error("Invalid course.");
			return false;
		}

		//Validate $arg_v[2], which is the date specified.
		//Verifies that the CLI Param date is (1) a real date (which includes
		//leap year dates), and (2) conforms to the pattern "MM/DD/YY".
		//IMPORTANT: date_default_timezone_set() needs to be set in config.php
		$date_tmp = DateTime::createFromFormat('m/d/y', $arg_v[2]);
		if (!boolval($date_tmp) || $date_tmp->format('m/d/y') !== $arg_v[2]) {
			console::print_error("Invalid date.");
			return false;
		}

		//Validation successful, set class properties.
		self::$course = $arg_v[1];
		self::$date   = array('year'  => $date_tmp->format('y'),
		                      'month' => $date_tmp->format('m'),
		                      'day'   => $date_tmp->format('d'));

		//Indicate success
		return true;
	}

	private static function restore_database() {
	//IN:  Course ID as whole string (prefix and number)
	//OUT: FALSE when DB is not accessible (process aborts).
	//     TRUE when process is complete.
	//PURPOSE:  DB column users/registration_section has a foreign constraint
	//          that must be satisfied before upserting student data.

		if (!database::connect()) {
			console::print_error("Could not connect to database.");
			return false;
		}

		switch(false) {
		case database::db_transaction('sections_registration'):
			console::print_error("Restore FK constraints 'sections_registration' called, but process failed.");
			return false;
		case database::db_transaction('sections_rotating'):
			console::print_error("Restore FK constraints 'sections_rotating' called, but process failed.");
			return false;
		case database::db_transaction('users'):
			console::print_error("Restore users table data called, but process failed.");
			return false;
		}

		console::print_message("Backup restore complete.");
		return true;
	}

	private static function decryption(&$data) {
	//IN:  $data to be decrypted.
	//OUT: TRUE when data decryption is successful.  FALSE otherwise.
	//     Decrypted $data is shared by reference.
	//PURPOSE: Decrypt backup data.
	//IMPORTANT:  $cipher and $key_length values are preset to industry
	//            recommended values.

		$cipher = 'aes-128-cbc';
		$length = 16;

		if (empty($data)) {
			console::print_error("Decryption requested, but no backup data read.");
			return false;
		}

		$key = file_get_contents(self::$key_file);
		if ($key === false || strlen($key) !== $length) {
			console::print_error("Decryption requested, but invalid key file.");
			return false;
		}

		$iv = substr(self::$data, 0, $length);
		$tmp = openssl_decrypt(substr($data, $length), $cipher, $key, OPENSSL_RAW_DATA, $iv);

		if ($tmp === false) {
			console::print_error("Decryption attempted, but failed.");
			return false;
		}

		$data = $tmp;
		return true;
	}

	/* Protected getters needed by child classes ---------------------------- */
	protected static function get_date() {

		//Return property value, or FALSE when null.
		return (isset(self::$date)) ? self::$date : false;
	}

	protected static function get_course() {

		//Return property value, or FALSE when null.
		return (isset(self::$course)) ? self::$course : false;
	}

	protected static function get_data($set) {

		//Return property value, or FALSE when null.
		return (isset(self::$data[$set])) ? self::$data[$set] : false;
	}

	protected static function get_num_rows($set) {

		//Return array count for property, or FALSE when property is null.
		return (isset(self::$data[$set])) ? count(self::$data[$set]) : false;
	}
}

/* CHILD CLASS DATABASE ===================================================== */
class database extends restore_backup {

/* IMPORTANT NOTES:
 * Before restoring the user's table, we need to make sure foreign key
 * constraints are first satisfied (otherwise INSERT queries may be blocked).
 * It is not expected to have to restore foreign keys, as auto feed only inserts
 * FK elements as needed by student enrollment (FK elements are never updated or
 * deleted by the auto feed).  But as an integrity check, we need to be certain
 * to prevent potential query errors (blocking by missing FK constraints) when
 * restoring users table.
 *
 * Instead, we will check for and upsert (insert/update) backup data to ensure
 * that an FK constraint won't block a query when restoring users data.
 */

	private static $db   = false;
	private static $sql  = array( 'begin'      => array("BEGIN"),
	                              'temp_table' => array(),
	                              'row'        => array(),
	                              'lock'       => array(),
	                              'restore'    => array(),
	                              'commit'     => array("COMMIT") );

	protected static function connect() {
	//IN:  No parameters
	//OUT: TRUE when DB is connected.  FALSE otherwise.
	//PURPOSE:  Establish DB connection for the appropriate course.

    /* DATABASE CONNECTION -------------------------------------------------- */
		//DB connection parameters
		$host     = DB_HOST;
		$login    = DB_LOGIN;
		$password = DB_PASSWORD;

		//Determine DB Name (based on semester which is derived from month and year)
		$date     = parent::get_date();
		$month    = intval($date['month']);
		$year     = $date['year'];
		//if ($month <= 5) {...} else if ($month >= 8) {...} else {...}
		$semester = ($month <= 5) ? "s{$year}" : (($month >= 8) ? "f{$year}" : "m{$year}");
		$course   = parent::get_course();
		$db_name  = "submitty_{$semester}_{$course}";

		//Connect to database.
		self::$db = pg_connect("host={$host} dbname={$db_name} user={$login} password={$password}");

		//Boolean evaluation to indicate success (TRUE) or failure (FALSE).
		return (is_resource(self::$db) && get_resource_type(self::$db) === 'pgsql link');
	}

	protected static function db_transaction($table) {

		//Get data set from parent class.
		$data     = parent::get_data($table);
		$num_rows = parent::get_num_rows($table);

		//Make sure data was retrieved OK
		if ($data === false) {
			return false;
		}

		//Make sure there is data to restore.
		//Return true if no data to restore -- this situation is not an error.
		if (empty($data)) {
			return true;
		}

		//Make sure DB is connected.
		if (!is_resource(self::$db) || get_resource_type(self::$db) !== 'pgsql link') {
			return false;
		}


		//Get SQL queries for $table.
		switch($table) {
		case 'users':
			self::build_sql_for_users_data();
			break;
		case 'sections_rotating':
		case 'sections_registration':
			self::build_sql_for_fk_data($table);
			break;
		default:
			return false;
		}

		foreach (self::$sql as $key => $query) {
			foreach ($query as $i => $row) {
				$values = ($key === 'row') ? $data[$i] : array();
				pg_query_params(self::$db, $row, $values);
			}
		}

		return true;
	}

	private static function build_sql_for_fk_data($table) {
	//IN:  string represening DB dataset to restore.
	//OUT: TRUE when refresh is complete.
	//PURPOSE:  "Refresh" sections_registration and sections_rotating data into
	//          the database.  Works as a batch query.

		//TEMPORARY table to hold all new values that will be "upserted"
		self::$sql['temp_table']   = array();
		self::$sql['temp_table'][] = <<<SQL
CREATE TEMPORARY TABLE temp
	(id INTEGER)
ON COMMIT DROP
SQL;

		//INSERT new data into temporary table -- prepares all data to be
		//restored in a single DB transaction.
		self::$sql['row'] = array();
		$num_rows = parent::get_num_rows($table); //so method isn't called n times.
		for($i = 0; $i < $num_rows; $i++) {
			self::$sql['row'][$i] = <<<SQL
INSERT INTO temp VALUES ($1)
SQL;
		}

		//LOCK will prevent sharing collisions.
		self::$sql['lock']   = array();
		self::$sql['lock'][] = <<<SQL
LOCK TABLE {$table} IN EXCLUSIVE MODE
SQL;

		//Ensures that INSERT will only occur when data element is missing.
		//We will not touch any data that already exists.
		//NOTE: Column is "{$table}_id"
		$sql['restore']   = array();
		$sql['restore'][] = <<<SQL
INSERT INTO {$table}
	({$table}_id)
SELECT
	temp.id
FROM temp
LEFT OUTER JOIN {table}
	ON {$table}.{$table}_id=temp.id
WHERE {$table}.{$table}_id IS NULL
SQL;

		return true;
	}

	private static function build_sql_for_users_data() {

		//Temp table allows backup data to be restored as a single batch
		//transaction.
		self::$sql['temp_table']   = array();
		self::$sql['temp_table'][] = <<<SQL
CREATE TEMPORARY TABLE temp
	(id                  VARCHAR,
	 password            VARCHAR,
	 firstname           VARCHAR,
	 preferred_firstname VARCHAR,
	 lastname            VARCHAR,
	 email               VARCHAR,
	 grouping            INTEGER,
	 registration        INTEGER,
	 rotating            INTEGER,
	 manual              BOOLEAN)
ON COMMIT DROP
SQL;

		//Insert into temp table all backup data to be restored.
		self::$sql['row'] = array();  //Clear out old SQL
		$num_rows = parent::get_num_rows('users');  //So method isn't called n times.
		for ($i = 0; $i < $num_rows; $i++) {
			self::$sql['row'][$i] = <<<SQL
INSERT INTO temp VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10)
SQL;
		}

   		//LOCK will prevent sharing collisions.
		self::$sql['lock']   = array();
		self::$sql['lock'][] = <<<SQL
LOCK TABLE users IN EXCLUSIVE MODE
SQL;

		//This portion ensures that UPDATE will only occur when a record already exists.
		self::$sql['restore']    = array();  //clear out old SQL
		self::$sql['restore'][0] = <<<SQL
UPDATE users
SET
	user_id=temp.id,
	user_password=temp.password,
	user_firstname=temp.firstname,
	user_lastname=temp.lastname,
	user_preferred_firstname=temp.preferred_firstname,
	user_email=temp.email,
	user_group=temp.grouping,
	registration_section=temp.registration,
	rotating_section=temp.rotating,
	manual_registration=temp.manual
FROM temp
WHERE users.user_id=temp.id
SQL;

		//This portion ensures that INSERT will only occur when data record is new.
		self::$sql['restore'][1] = <<<SQL
INSERT INTO users
	(user_id,
	 user_firstname,
	 user_preferred_firstname,
	 user_lastname,
	 user_email,
	 user_group,
	 registration_section,
	 rotating_section,
	 manual_registration)
SELECT
	temp.id,
	temp.firstname,
	temp.preferred_firstname,
	temp.lastname,
	temp.email,
	temp.grouping,
	temp.registration,
	temp.rotating,
	temp.manual
FROM temp
LEFT OUTER JOIN users
	ON users.user_id=temp.id
WHERE users.user_id IS NULL
SQL;

		/* If there are any users in the table, but weren't in the backup
		 * implying that the data row is rogue), their sections_rotating and
		 * sections_registration assignmemt will be marked NULL.  These users
		 * who are also students (user_group=4) will "disappear" from the
		 * system's UI user list, although their actual data entry will remain
		 * intact.  (this is partly because the system is not engineered to
		 * support deletion of any user entries).
		 */
		self::$sql['restore'][2] = <<<SQL
UPDATE users
SET registration_section=NULL,
	rotating_section=NULL
FROM (SELECT users.user_id
	FROM users
	LEFT OUTER JOIN temp
		ON users.user_id=temp.id
	WHERE temp.id IS NULL)
AS dropped
WHERE users.user_id=dropped.user_id
SQL;

		//all done
		return true;
	}
}

/* CHILD CLASS CONSOLE ====================================================== */
class console extends restore_backup {

/* This class is used to print output to user and occasionally get input from
 * user.  Escape ANSII codes are used to format some output.  e.g. "\e[ ..."
 */

	protected static function prompt_restore() {
	//IN:  No parameters.
	//OUT: TRUE when user confirms to restore from data backup.
	//     FALSE otherwise.
	//PURPOSE:  Get user's confirmation to restore data from a backup.

		$course = parent::get_course();
		$date   = parent::get_date();
		$date   = "{$date['month']}/{$date['day']}/{$date['year']}";

		//"\e[44;97m"    = blue background, white text
		//"\e[1;93;101m" = boldtype, red background, yellow text
		//"\e[0m"        = end all special formatting
		$prompt = <<<PROMPT
This process will restore the Submitty database users table and requisite
foreign keys for course \e[44;97m {$course} \e[0m on \e[44;97m {$date} \e[0m
\e[1;93;101m                    !!!  THIS PROCESS CANNOT BE UNDONE  !!!                    \e[0m

PROMPT;

		self::print_message($prompt);

		$response = strtolower(readline("Proceed?  y/[n]: "));

		return ($response === 'y' || $response === 'yes');
	}

	protected static function print_help() {
	//IN:  No parameters.
	//OUT: No return value
	//PURPOSE: Print help text to STDOUT.

		//"\e[44;97m"    = blue background, white text
		//"\e[1;93;101m" = boldtype, red background, yellow text
		//"\e[0m"        = end all special formatting
		$help = <<<HELP

This tool will restore a Submitty database user's table and requisite foreign
keys.  \e[1;93;101m THIS PROCESS CANNOT BE UNDONE \e[0m

Usage: restore_backup.php <course> <date>

course: Submitty course to restore a users table backup
date:   Date of backup to restore in format MM/DD/YY.

Example: Restore users data for course CS100 with backup taken on March 1, 2017:
\e[44;97m restore_backup.php cs100 03/01/17 \e[0m


HELP;

		self::print_message($help);
	}

	protected static function print_error($msg) {
	//IN:  Message to write to STDERR.
	//OUT: No return value
	//PURPOSE: Print a message to STDERR.

		fwrite(STDERR, $msg . PHP_EOL);
	}

	protected static function print_message($msg) {
	//IN:  Message to write to STDOUT.
	//OUT: No return value
	//PURPOSE: Print a message to STDOUT.

		fwrite(STDOUT, $msg . PHP_EOL);
	}
}
?>
