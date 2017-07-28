<?php

/* HEADING ---------------------------------------------------------------------
 *
 * config.php script used by submitty_student_auto_feed
 * By Peter Bailie, Systems Programmer (RPI dept of computer science)
 *
 * Requires minimum PHP version 5.4 with pgsql and iconv extensions.
 *
 * This class will read a student enrollment CSV feed provided by the campus
 * registrar or data warehouse and "upsert" (insert/update) the feed into
 * Submitty's course databases.
 *
 * THIS SOFTWARE IS PROVIDED AS IS AND HAS NO GUARANTEE THAT IT IS SAFE OR
 * COMPATIBLE WITH YOUR UNIVERSITY'S INFORMATION SYSTEMS.  THIS IS ONLY A CODE
 * EXAMPLE FOR YOUR UNIVERSITY'S SYSYTEM'S PROGRAMMER TO PROVIDE AN
 * IMPLEMENTATION.  IT MAY REQUIRE SOME ADDITIONAL MODIFICATION TO SAFELY WORK
 * WITH YOUR UNIVERSITY'S AND/OR DEPARTMENT'S INFORMATION SYSTEMS.
 *
 * -------------------------------------------------------------------------- */

/* HOW TO USE ------------------------------------------------------------------
 *
 * Process flow code exists in the constructor, so all that is needed is to
 * (1) include "config.php" so that constants are defined.
 * (2) instantiate this class to process a data feed.
 *
 * q.v. driver.php
 *
 * -------------------------------------------------------------------------- */

class submitty_student_auto_feed {
	private static $course_list;
	private static $course_mappings;
	private static $db;
	private static $data = array();

	public function __construct() {

		//Important: Make sure we are running from CLI
		if (PHP_SAPI != "cli") {
			die("This is a command line tool.");
		}

		//Ensure all course labels have a common alpha case (lower).
		self::$course_list     = array_map('strtolower', unserialize(COURSE_LIST));
		self::$course_mappings = array_change_key_case(unserialize(COURSE_MAPPINGS), CASE_LOWER);
		foreach(self::$course_mappings as &$courses) {
			$courses = array_map('strtolower', $courses);
		} unset($courses);

		//Execute processes as soon as object is instantiated.
		//(halts when FALSE is returned by a method)
		switch(false) {
		case $this->load_and_validate_csv():
			fwrite(STDERR, "CSV feed file could not be loaded or failed validation." . PHP_EOL);
			break;
		case $this->process_csv():
			break;
		}
 	}

	private function load_and_validate_csv() {
	//IN:  No parameters
	//OUT: No specific return, but self::$data property will contain csv data.
	//PURPOSE: Load CSV data, run some error checks, set data to class property.

		$csv_file = CSV_FILE;
		$loaded_data = file($csv_file, FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES);
		if ($loaded_data === false) {
			fwrite(STDERR, "Failed to load {$csv_file}." . PHP_EOL);
			return false;
		}

		//Windows generated data feeds should be converted to UTF-8
		if (CONVERT_CP1252) {
			foreach($loaded_data as &$row) {
				$row = iconv("WINDOWS-1252", "UTF-8//TRANSLIT", $row);
			} unset($row);
		}

		//Validate CSV
		$validate_num_fields = VALIDATE_NUM_FIELDS;
		foreach($loaded_data as $index => &$row) {
			//Trim any extraneous whitespaces from end of each row.
			//Split each row by delim character so that individual fields are indexed.
			$row = explode(CSV_DELIM_CHAR, trim($row, ' '));

			//BEGIN VALIDATION
			$course = strtolower($row[COLUMN_COURSE_PREFIX]) . $row[COLUMN_COURSE_NUMBER];
			$mapping = $this->course_is_mapped($course);
			$num_fields = count($row);

			//Row validation filters.  If any prove false, row is discarded.
			switch(false) {
			//Check to see if course is participating in Submitty
			case (in_array($course, self::$course_list) || boolval($mapping)):
				break;
			//Check that row shows student is registered.
			case (in_array($row[COLUMN_REGISTRATION], unserialize(STUDENT_REGISTERED_CODES))):
				break;
			//Validate expected number of fields
			case ($num_fields === $validate_num_fields):
			//Log that row is invalid per number of columns
				fwrite(STDERR, "Row {$index} has {$num_fields} columns.  {$validate_num_fields} expected.  Row discarded." . PHP_EOL);
				break;
			//Check row columns
			default:
				//Column validation filters.  If any prove false, the entire row is discarded.
				switch(false) {
				//First name must be alpha characters, white-space, or certain punctuation.
				case (preg_match("~^[a-zA-Z'`\-\. ]+$~", $row[COLUMN_FNAME])):
					fwrite(STDERR, "Row {$index} failed validation for student first name ({$row[COLUMN_FNAME]}).  Row discarded." . PHP_EOL);
					break;
				//Last name must be alpha characters white-space, or certain punctuation.
				case (preg_match("~^[a-zA-Z'`\-\. ]+$~", $row[COLUMN_LNAME])):
					fwrite(STDERR, "Row {$index} failed validation for student last name ({$row[COLUMN_LNAME]}).  Row discarded." . PHP_EOL);
					break;
				//Student section must be greater than zero.  intval($str) returns zero when $str is not integer.
				case (intval($row[COLUMN_SECTION]) > 0):
					fwrite(STDERR, "Row {$index} failed validation for student section ({$row[COLUMN_SECTION]}).  Row discarded." . PHP_EOL);
					break;
				//Loose email address check for format of "address@domain" or "address@[ipv4]"
				case (preg_match("~.+@{1}[a-zA-Z0-9:\.\-\[\]]+$~", $row[COLUMN_EMAIL])):
					fwrite(STDERR, "Row {$index} failed validation for student email ({$row[COLUMN_EMAIL]}).  Row discarded." . PHP_EOL);

				default:
					//Validation passed.

					//Adjust data for any mapped course.  Such students will be
					//assigned to a new section in the base course.  Row data
					//will be grouped into the base course's data.
					if (boolval($mapping)) {
						$row[COLUMN_SECTION] = $mapping['offset'];
						$course = $mapping['base_course'];
					}

					//Include row in data set.  Group data by course.
					self::$data[$course][] = $row;
				}
			}
		} unset($row);

		//TRUE:  validated data set will have at least 1 row.
		//FALSE: an empty set that shouldn't be processed.
		return (count(self::$data) > 0);
	}

	private function process_csv() {
	//IN:  No parameters
	//OUT: No return
	//PURPOSE: Process csv fields to be upserted to DB tables.
	//NOTE:    $month 1 - 5 is (s)pring semester.
	//         $month 8 - 12 is (f)all semester.
	//         Other $month values are maybe s(u)mmer courses...?

		//copied for string expansion with "{$var}"
		$db_host     = DB_HOST;
		$db_user     = DB_LOGIN;
		$db_password = DB_PASSWORD;
		$month       = intval(date("m", time()));
		$year        = date("y", time());
		//if ($month <= 5) {...} else if ($month >= 8) {...} else {...}
		$semester    = ($month <= 5) ? "s{$year}" : (($month >= 8) ? "f{$year}" : "m{$year}");

		foreach(self::$course_list as $course) {
			$course_name = strtolower($course);
			$db_name = "submitty_{$semester}_{$course_name}";
			self::$db = pg_connect("host={$db_host} dbname={$db_name} user={$db_user} password={$db_password}");
			if (self::$db === false) {
				fwrite(STDERR, "ERROR: Failed to connect to DB {$db_host} or {$db_name}.  Skipping {$course}." . PHP_EOL);
				continue;  //skip upserting and move on to next course.
			}

			switch(false) {
			//update sections_registration DB constraint.
			case ($this->update_sections_fk($course)):
				fwrite(STDERR, "Could not update sections_id foreign key." . PHP_EOL);
				break;
			//Upsert data.
			case ($this->upsert($course)):
				fwrite(STDERR, "Error during upsert." . PHP_EOL);
				break;
			}

			pg_close(self::$db);
		}

		//successfully completed.
		return true;
	}

	private function update_sections_fk($course) {
	//IN:  Course ID as whole string (prefix and number)
	//OUT: FALSE when DB is not accessible (process aborts).
	//     TRUE when process is complete.
	//PURPOSE:  DB column users/registration_section has a foreign constraint
	//          that must be satisfied before upserting student data.

		//EXPECTED: self::$db has an active/open Postgres connection.
		if (!is_resource(self::$db) || get_resource_type(self::$db) !== 'pgsql link') {
			fwrite(STDERR, "Error: not connected to DB while updating sections_registration foreign key constraint for {$course}" . PHP_EOL);
			return false;
		}

		//Determine highest registered section number from course data.  Needed
		//when expanding mapped courses and updating users/regsitration_section
		//in DB.
		$csv_max_section = 1;
		foreach(self::$data[$course] as $row) {
			$tmp = intval($row[COLUMN_SECTION]);
			if ($tmp > $csv_max_section) {
				$csv_max_section = $tmp;
			}
		}

		//Determine max value permitted by DB foreign constraint.
		$query = pg_query(self::$db, "SELECT MAX(sections_registration_id) FROM sections_registration");
		$db_max_section = pg_fetch_result($query, 'max');

		//Determine that foreign constraint should be updated.
		if (is_null($db_max_section)) {
			//There are no registered sections.  Start adding sections at 01.
			$new_sections_start = 1;
		} else {
			$db_max_section = intval($db_max_section);
			if ($csv_max_section - $db_max_section > 0) {
				//There are registered sections, but more are needed to process
				//student data.
				$new_sections_start = ($db_max_section + 1);
			} else {
				//All required sections already exist, so no updates needed.
				//PHP_INT_MAX is a trigger to prevent update loop.
				$new_sections_start = PHP_INT_MAX;
			}
		}

		//Update foreign constraint in DB.
		//$new_sections_start = PHP_INT_MAX prevents this from running.
		for($i = $new_sections_start; $i <= $csv_max_section; $i++) {
			pg_query_params(self::$db, "INSERT INTO sections_registration VALUES ($1)", array($i));
		}

		return true;
	}

	private function course_is_mapped($tmp_course) {
	//IN:  Course as full string (e.g. "cs100").
	//OUT: Array of the base course that is mapped and the mapping index.
	//PURPOSE:  Course mappings are two dimensional arrays, which cannot be
	//          parsed with array_search.  Offset is incremented as it will be
	//          used to set section for student enrollment.
	//TO DO:  Student section reassignment for mapped courses are specific at
	//        setting the section to 2+ as this assumes the base course has only
	//        one section.  This may be a false assumption at some Universities.

		foreach(self::$course_mappings as $base_course=>$mappings) {
			$offset = array_search($tmp_course, $mappings);
			if ($offset !== false) {
				return array( 'base_course' => strtolower($base_course),
				              'offset'      => strval($offset + 2) );
			}
		}

		return false;
	}

	private function upsert($course) {
	//IN:  No parameters (works with class property data)
	//OUT: TRUE when upsert is complete.
	//PURPOSE:  "Update/Insert" data into the database.  Code capable of "batch"
	//          upserts.

		//EXPECTED: self::$db has an active/open Postgres connection.
		if (!is_resource(self::$db) || get_resource_type(self::$db) !== 'pgsql link') {
			fwrite(STDERR, "Error: not connected to DB when attempting data upsert for {$course}" . PHP_EOL);
			return;
		}

/* -----------------------------------------------------------------------------
 * This SQL code was adapted from upsert discussion on Stack Overflow and is
 * meant to be compatible with PostgreSQL prior to v9.5.
 *
 * 	q.v. http://stackoverflow.com/questions/17267417/how-to-upsert-merge-insert-on-duplicate-update-in-postgresql
 * -------------------------------------------------------------------------- */

		$sql = array( 'begin'  => 'BEGIN',
		              'commit' => 'COMMIT' );

		//TEMPORARY table to hold all new values that will be "upserted"
		$sql['temp_table'] = <<<SQL
CREATE TEMPORARY TABLE temp
	(student_id           VARCHAR,
	 first_name           VARCHAR,
	 last_name            VARCHAR,
	 preferred_first_name VARCHAR,
	 email                VARCHAR,
	 s_group              INTEGER,
	 r_section            INTEGER,
	 is_manual            BOOLEAN)
ON COMMIT DROP
SQL;

		//INSERT new data into temporary table -- prepares all data to be
		//upserted in a single DB transaction.
		foreach(self::$data[$course] as $i => $row) {
			$sql['data'][$i] = <<<SQL
INSERT INTO temp VALUES ($1,$2,$3,$4,$5,$6,$7,$8)
SQL;
		}

		//LOCK will prevent sharing collisions while upsert is in process.
		$sql['lock'] = <<<SQL
LOCK TABLE users IN EXCLUSIVE MODE
SQL;

		//This portion ensures that UPDATE will only occur when a record already exists.
		$sql['update'] = <<<SQL
UPDATE users
SET
	user_firstname=temp.first_name,
	user_lastname=temp.last_name,
	user_preferred_firstname=temp.preferred_first_name,
	user_email=temp.email,
	registration_section=temp.r_section
FROM temp
WHERE users.user_id=temp.student_id
	AND users.user_group=temp.s_group
SQL;

		//This portion ensures that INSERT will only occur when data record is new.
		$sql['insert'] = <<<SQL
INSERT INTO users
	(user_id,
	 user_firstname,
	 user_lastname,
	 user_preferred_firstname,
	 user_email,
	 user_group,
	 registration_section,
	 manual_registration)
SELECT
	temp.student_id,
	temp.first_name,
	temp.last_name,
	temp.preferred_first_name,
	temp.email,
	temp.s_group,
	temp.r_section,
	temp.is_manual
FROM temp
LEFT OUTER JOIN users
	ON users.user_id=temp.student_id
WHERE users.user_id IS NULL
SQL;

		//We also need to move students no longer in auto feed to the NULL registered section
		//Make sure this only affects students (AND users.user_group=$1)
		$sql['dropped_students'] = <<<SQL
UPDATE users
SET registration_section=NULL,
	rotating_section=NULL
FROM (SELECT users.user_id
	FROM users
	LEFT OUTER JOIN temp
		ON users.user_id=temp.student_id
	WHERE temp.student_id IS NULL)
AS dropped
WHERE users.user_id=dropped.user_id
AND users.user_group=$1
AND users.manual_registration=$2
SQL;

		pg_query(self::$db, $sql['begin']);
		pg_query(self::$db, $sql['temp_table']);

		//fills temp table with batch upsert data.
		foreach(self::$data[$course] as $i => $row) {
			pg_query_params(self::$db, $sql['data'][$i], array( $row[COLUMN_CSID],
			                                                    $row[COLUMN_FNAME],
			                                                    $row[COLUMN_LNAME],
			                                                    $row[COLUMN_PNAME],
			                                                    $row[COLUMN_EMAIL],
			                                                    4,
			                                                    $row[COLUMN_SECTION],
			                                                    'FALSE' ));
		}

		pg_query(self::$db, $sql['lock']);
		pg_query(self::$db, $sql['update']);
		pg_query(self::$db, $sql['insert']);
		pg_query_params(self::$db, $sql['dropped_students'], array(4, 'FALSE'));
		pg_query(self::$db, $sql['commit']);

		//indicate success.
		return true;
	}
}
/* EOF ====================================================================== */
?>
