#!/usr/bin/env php
<?php
/* HEADING ---------------------------------------------------------------------
 *
 * submitty_student_auto_feed.php script example
 * By Peter Bailie, Systems Programmer (RPI dept of computer science)
 *
 * Requires minimum PHP version 5.4 with pgsql and iconv extensions.  This
 * script is intended to be run from the CLI as a scheduled cron job.
 *
 * This script is designed so that the university's registrar is sending a data
 * dump of student enrollment for all courses used in Submitty, perhaps even
 * the whole department, in a single CSV file.
 *
 * The defined constants marked '***THIS NEEDS TO BE SET' need to be adjusted to
 * match your information system's configuration.
 *
 * Attempts have been made to generalize this code, but different Universities
 * have different information systems, some of which cannot be accounted for.
 *
 * This script has no log module, but pertinant error messages are written to
 * STDERR, which could be redirected to a text file.
 *
 * THIS SCRIPT IS PROVIDED AS IS AND HAS NO GUARANTEE THAT IT IS SAFE OR
 * COMPATIBLE WITH YOUR UNIVERSITY'S INFORMATION SYSTEMS.  THIS IS ONLY A CODE
 * EXAMPLE FOR YOUR UNIVERSITY'S SYSYTEM'S PROGRAMMER TO PROVIDE AN
 * IMPLEMENTATION.  IT MAY REQUIRE SOME ADDITIONAL MODIFICATION TO SAFELY WORK
 * WITH YOUR UNIVERSITY'S AND/OR DEPARTMENT'S INFORMATION SYSTEMS.
 *
 * -------------------------------------------------------------------------- */

//Course dept prefix used in DB naming convention.  ***THIS NEEDS TO BE SET.
//e.g. If your course's DB is named 'submitty_f16_cs1000', then set this to
//'cs'.  Make sure to match the upper/lowercase used in DB name.
define('COURSE_PREFIX', 'cs');

//Course numbers to be processed.  Other courses in the CSV dump will be
//ignored.  Data should be string data representing the course number.
//***THIS NEEDS TO BE SET as a serialized array.
define('COURSES', serialize( array(
'1000',
'1500',
'2000',
'2500',
'3000',
'3500',
'4000',
)));

//Some Universities will hold mixed enrollment of graduate and undergraduate
//students in the same classroom.  Having two separate Submitty courses for
//one whole classroom may be inconvenient, so this list will map enrollment of
//one course into another course that is listed above.  It is recommended that
//the graduate student enrollment be merged into an undergraduate course.
//
//In any case, course enrollment merged into another course will show up in
//Submitty as "Section 02", as per official design.
//***THIS NEEDS TO BE SET as a serialized array.
define('COURSE_MAPPINGS', serialize( array(
'5500' => '3500',
'6000' => '4000',
)));

//Student registration status is important, as data dumps can contain students
//who have dropped a course either before the semester starts or during the
//semester.  This serialized array will contain all valid registered-student
//codes can be expected in the data dump.
//***THIS NEEDS TO BE SET as a serialized array.
//
//IMPORTANT: Consult with your University's IT administrator or registrar to
//           add all pertinant student-is-registered codes that can be found in
//           your CSV data dump.  EXAMPLE: 'RA' may mean "registered by advisor"
//           and 'RW' may mean "registered via web"
define('STUDENT_REGISTERED_CODES', serialize( array(
'RA',
'RW',
)));

//The path/file provided by the registrar.  ***THIS NEED TO BE SET.
define('CSV_FILE', '/path/to/datafile.csv');

//Where to copy/access temporary file.  Temporary files should be auto-deleted
//when the script exits.  You can use a more "private" directory, but it needs
//RW permissions owned by the same user that runs this script.
define('AUTO_FEED_TEMP', '/tmp/submitty_student_auto_feed.csv');

//define the character that is delimiting each field.  ***THIS NEEDS TO BE SET.
//To define using the tab character, set this to chr(9).
define('CSV_DELIM_CHAR', chr(9));

//Properties for database access.  ***THESE NEED TO BE SET.
define('DB_HOST', '192.168.56.101');
define('DB_LOGIN', 'hsdbu');
define('DB_PASSWORD', 'hsdbu');

/* The following constants identify what columns to read in the CSV dump. --- */
//these properties are used to group data by individual course and student.
//NOTE: If your University does not support "Preferred Name" in its student
//      registration data -- set the column value to null.
define('COURSE_GROUP',        '9');   //course number column
define('COURSE_REGISTRATION', '8');   //course registration status column
define('COURSE_STUDENT_ID',   '5');   //student's campus ID number column
define('COURSE_RSECTION',     '10');  //student's registered section column
//These properties are for inserting/updating student enrollment.
define('UPSERT_CSLOGIN',      '6');   //student's computer systems login column
define('UPSERT_FNAME',        '2');   //student's first name column
define('UPSERT_LNAME',        '1');   //student's last name column
define('UPSERT_PNAME',        '4');   //student's "preferred" name column
define('UPSERT_EMAIL',        '7');   //student's campus email column
define('UPSERT_RSECTION',     '10');  //student's registered section column

//Sometimes data feeds are generated by Windows systems, in which case the data
//file probably needs to be converted from Windows-1252 (aka CP-1252) to UTF-8.
//Set to true to convert data feed file from Windows char encoding to UTF-8.
//Set to false if data feed is already provided in UTF-8.
define('CONVERT_CP1252', true);

/* SUGGESTED SETTINGS FOR TIMEZONES IN USA -------------------------------------
 *
 * Eastern ........... America/New_York
 * Central ........... America/Chicago
 * Mountain .......... America/Denver
 * Mountain no DST ... America/Phoenix
 * Pacific ........... America/Los_Angeles
 * Alaska ............ America/Anchorage
 * Hawaii ............ America/Adak
 * Hawaii no DST ..... Pacific/Honolulu
 *
 * For complete list of timezones, view http://php.net/manual/en/timezones.php
 *
 * -------------------------------------------------------------------------- */

// Univeristy campus's timezone.  ***THIS NEEDS TO BE SET.
date_default_timezone_set('America/New_York');

/* DRIVER =================================================================== */

$auto_feed_obj = new submitty_student_auto_feed();
$auto_feed_obj->run_process();

exit(0);

/* CLASS ==================================================================== */
class submitty_student_auto_feed {
	static private $db;
	static private $data;

	public function __construct() {
	}

	public function __destruct() {
	//Ensure temp file is purged after processing.
		if (file_exists(AUTO_FEED_TEMP) && unlink(AUTO_FEED_TEMP) === false) {
			fwrite(STDERR, "WARNING: Unable to delete temp file" . PHP_EOL);
		}
	}

	public function run_process() {
	//IN:  No parameters
	//OUT: No retun
	//PURPOSE: Run script process flow

		$this->load_csv();
		$this->process_csv();
	}

	private function load_csv() {
	//IN:  No parameters
	//OUT: No specific return, but self::$data property will contain csv data.
	//PURPOSE: Load CSV data.

		//copy script to tmp for processing.
		if (copy(CSV_FILE, AUTO_FEED_TEMP) === false) {
			fwrite(STDERR, "ERROR: Cannot copy CSV file to tmp" . PHP_EOL);
			exit(1);
		}

		$loaded_data = file(AUTO_FEED_TEMP, FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES);

		//Windows generated data feeds should be converted to UTF-8
		if (CONVERT_CP1252) {
			foreach($loaded_data as &$row) {
				$row = iconv("WINDOWS-1252", "UTF-8//TRANSLIT", $row);
			} unset($row);
		}

		//Read CSV into RAM
		foreach($loaded_data as $index => &$row) {
			//Trim any extraneous whitespaces from end of each row.
			//Split each row by delim character so that individual fields are indexed.
			$row = explode(CSV_DELIM_CHAR, trim($row, ' '));

			//Discard ALL rows that students are NOT specifically registered.
			if (array_search($row[COURSE_REGISTRATION], unserialize(STUDENT_REGISTERED_CODES)) === false) {
				//Student NOT registered.  Data useless.  Discard row.
				unset($loaded_data[$index]);
			}
		} unset($row);

		//group data by course ID and set $data as class property
		self::$data = $this->group_by_course($loaded_data, COURSE_GROUP);
	}

	private function process_csv() {
	//IN:  No parameters
	//OUT: No return
	//PURPOSE: Process csv fields to be upserted to DB tables.

		//copied for string expansion with "{$var}"
		$prefix      = COURSE_PREFIX;
		$db_host     = DB_HOST;
		$db_user     = DB_LOGIN;
		$db_password = DB_PASSWORD;
		$semester    = $this->determine_semester();

		foreach(unserialize(COURSES) as $course) {
			$db_name = "submitty_{$semester}_{$prefix}{$course}";
			self::$db = pg_connect("host={$db_host} dbname={$db_name} user={$db_user} password={$db_password}");
			if (self::$db === false) {
				fwrite(STDERR, "Failed to connect to DB, host: {$db_host}, name: {$db_name}" . PHP_EOL);
				exit(1);
			}

			$this->update_sections($course);
			$this->upsert($course);
			pg_close(self::$db);
		}
	}

	private function group_by_course($arr, $key = COURSE_GROUP) {
	//IN:  2-dim array of student registration data ($arr[row][column]).
	//OUT: New array with data organized by course number.
	//PURPOSE: Student data is reorganized by course number so that data can
	//         be processed course by course because each different course
	//         has its own distinct database.
	//IMPORTANT: Current Submitty design designates that "mapped courses" to be
	//           identified as section 02.  This is hard coded in this function.

		//data to be organized by course and returned.
		$set = array();
		//All courses to be processed.  Any course data not in this list is
		//not copied for processing.s
		$courses = unserialize(COURSES);
		//For courses that have multiple registered classes (e.g. undergrad and
		//grad registrations in the same classroom)
		$course_mappings = unserialize(COURSE_MAPPINGS);

		foreach($arr as $index => $row) {
			//First look for courses to group by
			if (array_search($row[$key], $courses, true) !== false) {
				$course = $row[$key];
				$set[$course][] = $row;
			//else look for course mappings and add them to their mapped course group.
			} else if (array_key_exists($row[$key], $course_mappings)) {
				$mapped_course = $course_mappings[$row[$key]];
				$tmp_row = $row;
				$tmp_row[UPSERT_RSECTION] = '02';
				$set[$mapped_course][] = $tmp_row;
			}
		}

		// Sort all course groups by Section (needed for section validation in DB)
		foreach($set as &$subset) {
			$this->merge_sort($subset, COURSE_RSECTION);
		} unset($subset);

		return $set;
	}

	private function determine_semester() {
	//IN:  No parameters
	//OUT: Semester code used in Submitty database naming convention.
	//PURPOSE: Automatically determine what the current semester is for database
	//         access.
	//IMPORTANT: This operates from the server's clock.  It is important that
	//           the current time is maintained and the university campus's
	//           timezone is appropriately set.

		$month = intval(date("m"));
		$year  = date("y");

		if ($month <= 5) {
			//spring is between months 1 - 5.
			$semester = "s{$year}";
		} else if ($month >=8 ) {
			//fall is between months 8 - 12.
			$semester = "f{$year}";
		} else {
			//maybe it is a summer class...?
			$semester = "u{$year}";
		}

		return $semester;
	}

	private function update_sections($course) {
	//IN:  Course number
	//OUT: No return value
	//PURPOSE:  DB column users/registration_section has a foreign constraint
	//          that must be satisfied before upserting student data.  This
	//          function will add available registration sections for any
	//          course, as needed by CSV data dump.

		//EXPECTED: self::$db has an active/open Postgres connection.
		if (!is_resource(self::$db) || get_resource_type(self::$db) !== 'pgsql link') {
			$prefix = COURSE_PREFIX;
			fwrite(STDERR, "Error: not connected to DB while updating available sections for {$prefix}{$course}" . PHP_EOL);
			return;
		}

		$max_csv_section = intval(end(self::$data[$course])[COURSE_RSECTION]);

		//What is the highest value of available sections?  Needed to see if
		//more sections are to be automaticaly added.
		$query = pg_query(self::$db, "SELECT MAX(sections_registration_id) FROM sections_registration;");
		$max_registered_section = pg_fetch_result($query, 'max');

		if (is_null($max_registered_section)) {
			//There are no registered sections.  Start adding sections at 01.
			$min = 1;
		} else {
			//There are registered sections, but more are needed to process
			//student data.
			$max_registered_section = intval($max_registered_section);
			$tmp = ($max_csv_section - $max_registered_section);
			if ($tmp > 0) {
				$min = ($max_registered_section + 1);
			} else {
				//All required sections already exist, so no new sections are
				//to be added.  This statement ensures for upcoming loop doesn't
				//run so there are no additions made.
				$min = PHP_INT_MAX;
			}
		}

		for($i = $min; $i <= $max_csv_section; $i++) {
			pg_query_params(self::$db, "INSERT INTO sections_registration VALUES ($1)", array($i));
		}
	}

	private function upsert($course) {
	//IN:  No parameters (works with class property data)
	//OUT: No return value, although data will be inserted/updated in a course
	//     database.
	//PURPOSE:  "Update/Insert" data batches into the database.

		//EXPECTED: self::$db has an active/open Postgres connection.
		if (!is_resource(self::$db) || get_resource_type(self::$db) !== 'pgsql link') {
			$prefix = COURSE_PREFIX;
			fwrite(STDERR, "Error: not connected to DB when attempting data upsert for {$prefix}{$course}" . PHP_EOL);
			return;
		}

/* -----------------------------------------------------------------------------
 * This function is adapted from upsert discussion on Stack Overflow and is
 * meant to be compatible with PostgreSQL prior to v9.5.
 *
 * 	q.v. http://stackoverflow.com/questions/17267417/how-to-upsert-merge-insert-on-duplicate-update-in-postgresql
 * -------------------------------------------------------------------------- */

		$sql = array( 'begin'  => 'BEGIN;',
		              'commit' => 'COMMIT;' );

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
ON COMMIT DROP;
SQL;

		//INSERT new data into temporary table -- prepares all data to be
		//upserted in a single DB transaction.
		foreach(self::$data[$course] as $i => $row) {
			$sql["data_{$i}"] = <<<SQL
INSERT INTO temp VALUES ($1,$2,$3,$4,$5,$6,$7,$8);
SQL;
		}

		//LOCK will prevent sharing collisions while upsert is in process.
		$sql['lock'] = <<<SQL
LOCK TABLE users IN EXCLUSIVE MODE;
SQL;

		//This portion ensures that UPDATE will only occur when a record already
		//exists.
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
	AND users.user_group=temp.s_group;
SQL;

		//This portion ensures that INSERT will only occur when data record is
		//new.
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
WHERE users.user_id IS NULL;
SQL;

		//Students NOT listed as active in the data feed are assumed to have
		//dropped.  These students are NOT deleted, but instead have their
		//registered and rotating sections set to NULL.
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
AND users.manual_registration=$2;
SQL;

		pg_query(self::$db, $sql['begin']);
		pg_query(self::$db, $sql['temp_table']);

		//fills temp table with batch upsert data.
		foreach(self::$data[$course] as $i => $row) {
			pg_query_params(self::$db, $sql["data_{$i}"], array( $row[UPSERT_CSLOGIN],
			                                                     $row[UPSERT_FNAME],
			                                                     $row[UPSERT_LNAME],
			                                                     (is_null(UPSERT_PNAME) ? null : $row[UPSERT_PNAME]),
			                                                     $row[UPSERT_EMAIL],
			                                                     4,
			                                                     $row[UPSERT_RSECTION],
			                                                     'FALSE' ));
		}

		pg_query(self::$db, $sql['lock']);
		pg_query(self::$db, $sql['update']);
		pg_query(self::$db, $sql['insert']);
		pg_query_params(self::$db, $sql['dropped_students'], array(4, 'FALSE'));
		pg_query(self::$db, $sql['commit']);
	}

	private function merge_sort(&$arr, $key = COURSE_STUDENT_ID) {
	//IN: Two dimensional array $arr and the $key to sort by.
	//OUT: No return, but array is passed by reference and function sorts it
	//     via Merge Sort algorithm
	//PURPOSE: This is a STABLE sort.  Stable sorting is not provided in the
	//         PHP API.
	//IMPORTANT: Array must be contiguous, numerically indexed, and zero-based.

		if (count($arr) < 2) {
			return;
		}

		$halfway = count($arr) / 2;
		$arr1 = array_slice($arr, 0, $halfway);
		$arr2 = array_slice($arr, $halfway);

		$this->merge_sort($arr1, $key);
		$this->merge_sort($arr2, $key);

		if (strcasecmp(end($arr1)[$key], $arr2[0][$key]) < 1) {
			$arr = array_merge($arr1, $arr2);
			return;
		}

		$arr = array();
		$i = 0;
		$j = 0;
		while ($i < count($arr1) && $j < count($arr2)) {
			if (strcasecmp($arr1[$i][$key], $arr2[$j][$key]) < 1) {
				$arr[] = $arr1[$i];
				$i++;
			} else {
				$arr[] = $arr2[$j];
				$j++;
			}
		}

		for (/* no var init */; $i < count($arr1); $i++) {
			$arr[] = $arr1[$i];
		}

		for (/* no var init */; $j < count($arr2); $j++) {
			$arr[] = $arr2[$j];
		}
	}
}
/* EOF ====================================================================== */
?>
