#!/usr/bin/env php
<?php

/* HEADING ---------------------------------------------------------------------
 *
 * submitty_student_auto_feed.php
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
 * EXAMPLE FOR YOUR UNIVERSITY'S SYSTEMS PROGRAMMER TO PROVIDE AN
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
 * -------------------------------------------------------------------------- */

require "config.php";
new submitty_student_auto_feed();

class submitty_student_auto_feed {
    private static $semester;
    private static $course_list;
    private static $course_mappings;
    private static $db;
    private static $psql_version;
    private static $data;
    private static $log_msg_queue;

    public function __construct() {

        //Important: Make sure we are running from CLI
        if (PHP_SAPI != "cli") {
            die("This is a command line tool.");
        }

        //Make sure CSV data array is empty on start.
        self::$data = array('users'         => array(),
                            'courses_users' => array());

        //Make sure log msg queue string is empty on start.
        self::$log_msg_queue = "";

        //Determine current semester
        $month = intval(date("m", time()));
        $year  = date("y", time());

        //(s)pring is month <= 5, (f)all is month >= 8, su(m)mer are months 6 and 7.
        //if ($month <= 5) {...} else if ($month >= 8) {...} else {...}
        self::$semester = ($month <= 5) ? "s{$year}" : (($month >= 8) ? "f{$year}" : "m{$year}");

        //Connect to submitty_db
        $db_host     = DB_HOST;
        $db_user     = DB_LOGIN;
        $db_password = DB_PASSWORD;
        self::$db = pg_connect("host={$db_host} dbname=submitty user={$db_user} password={$db_password}");

        //Make sure there's a DB connection to Submitty.
        if (pg_connection_status(self::$db) === PGSQL_CONNECTION_BAD) {
            $this->log_it("Error: Cannot connect to submitty DB");
        } else {
            //$psql_version will determine which upsert method is used.
            //Version >= 9.5 permits update on conflict.  Version <= 9.4 uses batch transaction.
            self::$psql_version = floatval(pg_parameter_status(self::$db, 'server_version'));

			//Get course list
			self::$course_list = $this->get_participating_course_list();

			//Get mapped_courses list (when one class is merged into another)
			self::$course_mappings = $this->get_course_mappings();

            //Auto-run class processes by executing them in constructor.
            //Halts when FALSE is returned by a method.
            switch(false) {
            //Load CSV data and make sure data is Ok to process.
            case $this->load_and_validate_csv():
                $this->log_it("CSV feed file could not be loaded or failed validation.");
                break;
            //Chooses which data upsert function to run based on psql version.
            //(upsert v9.4 with batch upsert or v9.5 with CONFLICT resloution upsert)
            //Case condition is resolved by function return value
            //
            //upsert_psql95 not yet ready.
            //case ((self::$psql_version >= 9.5) ? $this->upsert_psql95() : $this->upsert_psql94()):
            case $this->upsert_psql94():
                $this->log_it("Error during upsert of data.");
                break;
            }
        }

        //END EXECUTION
        exit(0);
    }

    public function __destruct() {

        //Close DB connection, if it exists.
        if (pg_connection_status(self::$db) === PGSQL_CONNECTION_OK) {
        	pg_close(self::$db);
        }

        //Output logs, if any.
        if (!empty(self::$log_msg_queue)) {
            error_log(self::$log_msg_queue, 1, ERROR_E_MAIL);    //to email
            error_log(self::$log_msg_queue, 3, ERROR_LOG_FILE);  //to file
        }
    }

    private function load_and_validate_csv() {
    //IN:  No parameters
    //OUT: No specific return, but self::$data property will contain csv data.
    //PURPOSE: Load CSV data, run some error checks, set data to class property.

        $csv_file = CSV_FILE;
        $loaded_data = file($csv_file, FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES);
        if ($loaded_data === false) {
            $this->log_it("Failed to load {$csv_file}.");
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
        foreach($loaded_data as $index => $row) {
            //Trim any extraneous whitespaces from end of each row.
            //Split each row by delim character so that individual fields are indexed.
            $row = explode(CSV_DELIM_CHAR, trim($row, ' '));

            //BEGIN VALIDATION
            $course = strtolower($row[COLUMN_COURSE_PREFIX]) . $row[COLUMN_COURSE_NUMBER];
            $section = $row[COLUMN_SECTION];
            $num_fields = count($row);

            //Row validation filters.  If any prove false, row is discarded.
            switch(false) {
            //Check to see if course is participating in Submitty (or a mapped course)
            case (in_array($course, self::$course_list) || array_key_exists($course, self::$course_mappings)):
                break;
            //Check that row shows student is registered.
            case (in_array($row[COLUMN_REGISTRATION], unserialize(STUDENT_REGISTERED_CODES))):
                break;
            //Validate expected number of fields
            case ($num_fields === $validate_num_fields):
            //Log that row is invalid per number of columns
                $this->log_it("Row {$index} has {$num_fields} columns.  {$validate_num_fields} expected.  Row discarded.");
                break;
            //Check row columns
            default:
                //Column validation filters.  If any prove false, the entire row is discarded.
                switch(false) {
                //Check term code (skips when set to null).
                case ((is_null(EXPECTED_TERM_CODE)) ? true : ($row[COLUMN_TERM_CODE] === EXPECTED_TERM_CODE)):
                	$this->log_it("Row {$index} failed validation for mismatched term code.  Row discarded");
                	break;
                //First name must be alpha characters, white-space, or certain punctuation.
                case (preg_match("~^[a-zA-Z'`\-\. ]+$~", $row[COLUMN_FIRSTNAME])):
                    $this->log_it("Row {$index} failed validation for student first name ({$row[COLUMN_FNAME]}).  Row discarded.");
                    break;
                //Last name must be alpha characters white-space, or certain punctuation.
                case (preg_match("~^[a-zA-Z'`\-\. ]+$~", $row[COLUMN_LASTNAME])):
                    $this->log_it("Row {$index} failed validation for student last name ({$row[COLUMN_LNAME]}).  Row discarded.");
                    break;
                //Student section must be greater than zero.  intval($str) returns zero when $str is not integer.
                case (intval($row[COLUMN_SECTION]) > 0):
                    $this->log_it("Row {$index} failed validation for student section ({$row[COLUMN_SECTION]}).  Row discarded.");
                    break;
                //Loose email address check for format of "address@domain" or "address@[ipv4]"
                case (preg_match("~^.+@{1}[a-zA-Z0-9:\.\-\[\]]+$~", $row[COLUMN_EMAIL])):
                    $this->log_it("Row {$index} failed validation for student email ({$row[COLUMN_EMAIL]}).  Row discarded.");

                default:
                	//Check for mapped (merged) course.
                	if (array_key_exists($course, self::$course_mappings)) {
                		if (array_key_exists($section, self::$course_mappings[$course])) {
                			$tmp_course  = $course;
                			$tmp_section = $section;
							$course = self::$course_mappings[$tmp_course][$tmp_section]['mapped_course'];
							$section = self::$course_mappings[$tmp_course][$tmp_section]['mapped_section'];
						} else {
							$this->log_it("{$course} has been mapped, but section {$section} is in feed, but not mapped.");
						}
                	}

                    //Validation passed. Include row in data set.
                    self::$data['users'][] = array('user_id'            => $row[COLUMN_USER_ID],
                                                   'user_firstname'     => $row[COLUMN_FIRSTNAME],
                                                   'user_preferredname' => $row[COLUMN_PREFERREDNAME],
                                                   'user_lastname'      => $row[COLUMN_LASTNAME],
                                                   'user_email'         => $row[COLUMN_EMAIL]);

					//Group 'courses_users' data by individual courses, so
					//upserts can be transacted per course.  This helps prevent
					//FK violations blocking upserts for other courses.
                    self::$data['courses_users'][$course][] = array('semester'             => self::$semester,
                                                                    'course'               => $course,
                                                                    'user_id'              => $row[COLUMN_USER_ID],
                                                                    'user_group'           => 4,
                                                                    'registration_section' => $section,
                                                                    'manual_registration'  => 'FALSE');
                }
            }
        }

		/* ---------------------------------------------------------------------
		 * Individual students can be listed on multiple rows if they are
		 * enrolled in two mor more courses.  'users' table needs to be
		 * deduplicated.  Deduplication will be keyed by 'user_id' since that is
		 * also the table's primary key.  Note that 'courses_users' should NOT
		 * be deduplicated.
		 * ------------------------------------------------------------------ */

        deduplicate::deduplicate_data(self::$data['users'], 'user_id');

        //TRUE:  Validated data set will have at least 1 row per table.
        //FALSE: Empty sets shouldn't be processed.
        return (count(self::$data['users']) > 0 && count(self::$data['courses_users']) > 0);
    }

    private function get_participating_course_list() {
        //EXPECTED: self::$db has an active/open Postgres connection.
        if (pg_connection_status(self::$db) === PGSQL_CONNECTION_BAD) {
            $this->log_it("Error: not connected to submitty DB when retrieving active course list.");
            return false;
        }

        $sql = <<<SQL
SELECT course
FROM courses
WHERE semester = $1
AND status = 1
SQL;

        $res = pg_query_params(self::$db, $sql, array(self::$semester));

        if ($res === false) {
            $this->log_it("RETRIEVE PARTICIPATING COURSES : " . pg_last_error(self::$db));
            return false;
        }

        return pg_fetch_all_columns($res, 0);
    }

    private function get_course_mappings() {
        //EXPECTED: self::$db has an active/open Postgres connection.

        if (pg_connection_status(self::$db) === PGSQL_CONNECTION_BAD) {
            $this->log_it("Error: not connected to submitty DB when retrieving active course list.");
            return false;
        }

        $sql = <<<SQL
SELECT course, registration_section, mapped_course, mapped_section
FROM mapped_courses
WHERE semester = $1
SQL;

	    $res = pg_query_params(self::$db, $sql, array(self::$semester));

        if ($res === false) {
            $this->log_it("RETRIEVE MAPPED COURSES : " . pg_last_error(self::$db));
            return false;
        }

        $results = pg_fetch_all($res);

        $mappings = array();
        foreach ($results as $row) {
        	$course = $row['course'];
        	$registration_section = $row['registration_section'];
        	$mapped_course = $row['mapped_course'];
        	$mapped_section = $row['mapped_section'];
        	$mappings[$course][$registration_section] = array('mapped_course'  => $mapped_course,
        	                                                  'mapped_section' => $mapped_section);
        }

        return $mappings;
    }

    private function upsert_psql94() {
    //IN:  No parameters.
    //OUT: TRUE when upsert is complete.
    //PURPOSE:  "Update/Insert" data into the database.  Code capable of "batch"
    //          upserts.

/* -----------------------------------------------------------------------------
 * This SQL code was adapted from upsert discussion on Stack Overflow and is
 * meant to be compatible with PostgreSQL prior to v9.5.
 *
 *  q.v. http://stackoverflow.com/questions/17267417/how-to-upsert-merge-insert-on-duplicate-update-in-postgresql
 * -------------------------------------------------------------------------- */

        $sql = array('users'         => array('begin'  => 'BEGIN',
                                              'commit' => 'COMMIT'),
                     'courses_users' => array('begin'  => 'BEGIN',
                                              'commit' => 'COMMIT'));

        //TEMPORARY table to hold all new values that will be "upserted"
        $sql['users']['temp_table'] = <<<SQL
CREATE TEMPORARY TABLE upsert_users (
    user_id                  VARCHAR,
    user_firstname           VARCHAR,
    user_preferred_firstname VARCHAR,
    user_lastname            VARCHAR,
    user_email               VARCHAR
) ON COMMIT DROP;
SQL;

        $sql['courses_users']['temp_table'] = <<<SQL
CREATE TEMPORARY TABLE upsert_courses_users (
    semester             VARCHAR,
    course               VARCHAR,
    user_id              VARCHAR,
    user_group           INTEGER,
    registration_section INTEGER,
    manual_registration  BOOLEAN
) ON COMMIT DROP;
SQL;

        //INSERT new data into temporary table -- prepares all data to be
        //upserted in a single DB transaction.
        $sql['users']['data'] = <<<SQL
INSERT INTO upsert_users VALUES ($1,$2,$3,$4,$5);
SQL;

        $sql['courses_users']['data'] = <<<SQL
INSERT INTO upsert_courses_users VALUES ($1,$2,$3,$4,$5,$6);
SQL;

        //LOCK will prevent sharing collisions while upsert is in process.
        $sql['users']['lock'] = <<<SQL
LOCK TABLE users IN EXCLUSIVE MODE;
SQL;

        $sql['courses_users']['lock'] = <<<SQL
LOCK TABLE courses_users IN EXCLUSIVE MODE;
SQL;

        //This portion ensures that UPDATE will only occur when a record already exists.
        $sql['users']['update'] = <<<SQL
UPDATE users
SET
    user_firstname=upsert_users.user_firstname,
    user_lastname=upsert_users.user_lastname,
    user_preferred_firstname=upsert_users.user_preferred_firstname,
    user_email=upsert_users.user_email
FROM upsert_users
WHERE users.user_id=upsert_users.user_id
SQL;

        $sql['courses_users']['update'] = <<<SQL
UPDATE courses_users
SET
    semester=upsert_courses_users.semester,
    course=upsert_courses_users.course,
    user_id=upsert_courses_users.user_id,
    user_group=upsert_courses_users.user_group,
    registration_section=upsert_courses_users.registration_section,
    manual_registration=upsert_courses_users.manual_registration
FROM upsert_courses_users
WHERE courses_users.user_id=upsert_courses_users.user_id
AND courses_users.manual_registration=FALSE
SQL;

        //This portion ensures that INSERT will only occur when data record is new.
        $sql['users']['insert'] = <<<SQL
INSERT INTO users (
    user_id,
    user_firstname,
    user_lastname,
    user_preferred_firstname,
    user_email
) SELECT
    upsert_users.user_id,
    upsert_users.user_firstname,
    upsert_users.user_lastname,
    upsert_users.user_preferred_firstname,
    upsert_users.user_email
FROM upsert_users
LEFT OUTER JOIN users
    ON users.user_id=upsert_users.user_id
WHERE users.user_id IS NULL
SQL;

        $sql['courses_users']['insert'] = <<<SQL
INSERT INTO courses_users (
    semester,
    course,
    user_id,
    user_group,
    registration_section,
    manual_registration
) SELECT
    upsert_courses_users.semester,
    upsert_courses_users.course,
    upsert_courses_users.user_id,
    upsert_courses_users.user_group,
    upsert_courses_users.registration_section,
    upsert_courses_users.manual_registration
FROM upsert_courses_users
LEFT OUTER JOIN courses_users
    ON courses_users.user_id=upsert_courses_users.user_id
WHERE courses_users.user_id IS NULL
SQL;

        //We also need to move students no longer in auto feed to the NULL registered section
        //Make sure this only affects students (AND users.user_group=$1)

        //Nothing to drop in users table.
        $sql['users']['dropped_students'] = null;

        $sql['courses_users']['dropped_students'] = <<<SQL
UPDATE courses_users
SET registration_section=NULL
FROM (SELECT courses_users.user_id
    FROM courses_users
    JOIN upsert_courses_users
        ON courses_users.user_id=upsert_courses_users.user_id
    WHERE upsert_courses_users.user_id IS NULL
    AND courses_users.course=upsert_courses_users.course
    AND courses_users.semester=upsert_courses_users.semester)
AS dropped
WHERE courses_users.user_id=dropped.user_id
AND courses_users.user_group=$1
AND courses_users.manual_registration=$2
SQL;

		//Transactions
		//'users' table
		pg_query(self::$db, $sql['users']['begin']);
		pg_query(self::$db, $sql['users']['temp_table']);
		//fills temp table with batch upsert data.
		foreach(self::$data['users'] as $row) {
			pg_query_params(self::$db, $sql['users']['data'], $row);
		}
		pg_query(self::$db, $sql['users']['lock']);
		if (pg_query(self::$db, $sql['users']['update']) === false) {
			$this->log_it("USERS (UPDATE) : " . pg_last_error(self::$db));
		}
		if (pg_query(self::$db, $sql['users']['insert']) === false) {
			$this->log_it("USERS (INSERT) : " . pg_last_error(self::$db));
		}
		$this->log_it(pg_last_error(self::$db));
		pg_query(self::$db, $sql['users']['commit']);

		//'courses_users' table (per course)
		foreach(self::$data['courses_users'] as $course_name => $course_data) {
			pg_query(self::$db, $sql['courses_users']['begin']);
			pg_query(self::$db, $sql['courses_users']['temp_table']);
			//fills temp table with batch upsert data.
			foreach($course_data as $row) {
				pg_query_params(self::$db, $sql['courses_users']['data'], $row);
			}
			pg_query(self::$db, $sql['courses_users']['lock']);
			if (pg_query(self::$db, $sql['courses_users']['update']) === false) {
				$this->log_it(strtoupper($course_name) . " (UPDATE) : " . pg_last_error(self::$db));
			}
			if (pg_query(self::$db, $sql['courses_users']['insert']) === false) {
				$this->log_it(strtoupper($course_name) . " (INSERT) : " . pg_last_error(self::$db));
			}

			pg_query_params(self::$db, $sql['courses_users']['dropped_students'], array(4, 'false'));
			pg_query(self::$db, $sql['courses_users']['commit']);
		}

        //indicate success.
        return true;
    }

    private function upsert_psql95() {
    //IN:  No parameters.
    //OUT: TRUE when upsert is complete.
    //PURPOSE:  "Update/Insert" data into the database.  Code takes advantage
    //          of ON CONFLICT clause for UPDATE

/* -----------------------------------------------------------------------------
 * SQL upsert code for Postgres v9.5+ (permits update on conflict)
 *
 * q.v. http://stackoverflow.com/questions/17267417/how-to-upsert-merge-insert-on-duplicate-update-in-postgresql
 * -------------------------------------------------------------------------- */

	//To do at a later time.  upsert_psql94() will work with postgresql v9.5+.

    }

    private function log_it($msg) {
    //IN:  Message to write to log file
    //OUT: No return, although log ms queue is updated.
    //PURPOSE: log msg queue holds messages intended for email and text logs.

		if (!empty($msg)) {
	        self::$log_msg_queue .= date('m/d/y H:i:s : ', time()) . $msg . PHP_EOL;
	    }
    }
}

class deduplicate {

	public static function deduplicate_data(&$arr, $key='user_id') {

		self::merge_sort($arr, $key);
		self::dedup($arr, $key);
	}

	private static function merge_sort(&$arr, $key) {
    //NOTE: PHP's sort() function is unstable and does not sort by column.

		//Arrays of size < 2 require no action.
		if (count($arr) < 2) {
			return;
		}

		//Split the array in half
		$halfway = count($arr) / 2;
		$arr1 = array_slice($arr, 0, $halfway);
		$arr2 = array_slice($arr, $halfway);

		//Recurse to sort the two halves
		self::merge_sort($arr1, $key);
		self::merge_sort($arr2, $key);

		//If all of $array1 is <= all of $array2, just append them.
		if (strcasecmp(end($arr1)[$key], $arr2[0][$key]) < 1) {
			$arr = array_merge($arr1, $arr2);
			return;
		}

		//Merge the two sorted arrays into a single sorted array
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

		//Merge the remainder
		for (/* no var init */; $i < count($arr1); $i++) {
			$arr[] = $arr1[$i];
		}

		for (/* no var init */; $j < count($arr2); $j++) {
			$arr[] = $arr2[$j];
		}
	}

	private static function dedup(&$arr, $key) {

		for ($i = 1; $i < count($arr); $i++) {
			if ($arr[$i][$key] === $arr[$i-1][$key]) {
				unset($arr[$i-1]);
			}
		}
	}
}

/* EOF ====================================================================== */
?>
