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
 * q.v. driver.php
 *
 * -------------------------------------------------------------------------- */

class submitty_student_auto_feed {
    private static $semester;
    private static $course_list;
    private static $submitty_db;
    private static $course_db;
    private static $psql_version;
    private static $data = array();
    private static $log_msg_queue;

    public function __construct() {

        //Important: Make sure we are running from CLI
        if (PHP_SAPI != "cli") {
            die("This is a command line tool.");
        }

        //Make sure log msg queue is empty on start.
        self::$log_msg_queue = "";

        //Determine current semester
        $month = intval(date("m", time()));
        $year  = date("y", time());

        //(s)pring is month <= 5, (f)all is month >= 8, su(m)mer are months 6 and 7.
        //if ($month <= 5) {...} else if ($month >= 8) {...} else {...}
        self::$semester = ($month <= 5) ? "s{$year}" : (($month >= 8) ? "f{$year}" : "m{$year}");

        //Get course list, mapped to all lowercase
        self::$course_list = array_map('strtolower', get_participating_courses());

        //Make connection to submitty_db
        $db_host     = DB_HOST;
        $db_user     = DB_LOGIN;
        $db_password = DB_PASSWORD;
        self::$submitty_db = pg_connect("host={$db_host} dbname=submitty user={$db_user} password={$db_password}");

        //Make sure there's a DB connection to Submitty.
        if (!test_db_conn(self::$submitty_db)) {
            $this->log_it("Error: Cannot connect to submitty DB");
        } else {
            //$psql_version will determine which upsert method is used.
            //Version >= 9.5 permits update on conflict.  Version <= 9.4 uses batch transaction.
            self::$psql_version = floatval(pg_parameter_status($submitty_db, 'server_version'));

            //Auto-run class processes by executing them in constructor.
            //Halts when FALSE is returned by a method.
            switch(false) {
            case $this->load_and_validate_csv():
                $this->log_it("CSV feed file could not be loaded or failed validation.");
                break;
            case $this->upsert():
                $this->log_it("Error during upsert of data.");
                break;
            }
        }
    }

    public function __destruct() {

        //Close DB connection, if it exists.
        foreach (array(self::$submitty_db, self::$course_db) as $db_conn) {
            if (is_resource($db_conn) && get_resource_type($db_conn) === 'pgsql link') {
                pg_close($db_con);
            }
        }

        //Output logs, if any.
        if (!empty($log_msg_queue)) {
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

        /* TO DO: NEED COURSE LIST FROM SUBMITTY DB */

        //Validate CSV
        $validate_num_fields = VALIDATE_NUM_FIELDS;
        foreach($loaded_data as $index => &$row) {
            //Trim any extraneous whitespaces from end of each row.
            //Split each row by delim character so that individual fields are indexed.
            $row = explode(CSV_DELIM_CHAR, trim($row, ' '));

            //BEGIN VALIDATION
            $course = strtolower($row[COLUMN_COURSE_PREFIX]) . $row[COLUMN_COURSE_NUMBER];
            $num_fields = count($row);

            //Row validation filters.  If any prove false, row is discarded.
            switch(false) {
            //Check to see if course is participating in Submitty
            case (in_array($course, self::$course_list)):
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
                //First name must be alpha characters, white-space, or certain punctuation.
                case (preg_match("~^[a-zA-Z'`\-\. ]+$~", $row[COLUMN_FNAME])):
                    $this->log_it("Row {$index} failed validation for student first name ({$row[COLUMN_FNAME]}).  Row discarded.");
                    break;
                //Last name must be alpha characters white-space, or certain punctuation.
                case (preg_match("~^[a-zA-Z'`\-\. ]+$~", $row[COLUMN_LNAME])):
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
                    //Validation passed. Include row in data set.
                    self::$data[] = $row;
                }
            }
        } unset($row);

        //TRUE:  validated data set will have at least 1 row.
        //FALSE: an empty set that shouldn't be processed.
        return (count(self::$data) > 0);
    }

    private function get_participating_course_list() {
        //EXPECTED: self::$db has an active/open Postgres connection.
        if (!test_db_conn(self::$submitty_db)) {
            $this->log_it("Error: not connected to submitty DB when retrieving active course list.");
            return false;
        }

        $sql = <<<SQL
SELECT course
FROM courses
WHERE semester = $1
AND status = 1
SQL;

        $result = pg_query_params(self::$submitty_db, $sql, array(self::$semester));

        if ($result === false) {
            $this->log_it("Error: DB query failed to retrieve course list.");
            return false;
        }

        return pg_fetch_all($result);
    }

    private function test_db_conn($db_conn) {
        //Make sure $db connection is good.
        if (!is_resource($db_conn) || get_resource_type($db_conn) !== 'pgsql link') {
            return false;
        }

        return true;
    }

    private function upsert($course) {
    //IN:  No parameters (works with class property data)
    //OUT: TRUE when upsert is complete.
    //PURPOSE:  "Update/Insert" data into the database.  Code capable of "batch"
    //          upserts.

        if (self::$psql_vesion < 9.5) {

/* -----------------------------------------------------------------------------
 * This SQL code was adapted from upsert discussion on Stack Overflow and is
 * meant to be compatible with PostgreSQL prior to v9.5.
 *
 *  q.v. http://stackoverflow.com/questions/17267417/how-to-upsert-merge-insert-on-duplicate-update-in-postgresql
 * -------------------------------------------------------------------------- */

            $sql = array( 'begin'  => 'BEGIN',
                          'commit' => 'COMMIT' );

        //TEMPORARY table to hold all new values that will be "upserted"
        $sql['temp_tables']['users'] = <<<SQL
CREATE TEMPORARY TABLE upsert_users (
    u_user_id                  VARCHAR,
    u_user_firstname           VARCHAR,
    u_user_preferred_firstname VARCHAR,
    u_last_name                VARCHAR,
    u_email                    VARCHAR,
) ON COMMIT DROP;
SQL;

        $sql['temp_tables']['courses_users'] = <<<SQL
CREATE TEMPORARY TABLE upsert_courses_users (
    u_semester             VARCHAR,
    u_course               VARCHAR,
    u_user_id              VARCHAR,
    u_user_group           INTEGER,
    u_registration_section VARCHAR,
    u_manual_registration  BOOLEAN
) ON COMMIT DROP;
SQL;

            //INSERT new data into temporary table -- prepares all data to be
            //upserted in a single DB transaction.
            foreach(self::$data as $i => $row) {
                $sql['data'][$i]['users'] = <<<SQL
INSERT INTO upsert_users VALUES ($1,$2,$3,$4,$5);
SQL;

                $sql['data'][$i]['courses_users'] = <<<SQL
INSERT INTO upsert_courses_users VALUES ($1,$2,$3,$4,$5);
SQL;
            }

            //LOCK will prevent sharing collisions while upsert is in process.
            $sql['lock']['users'] = <<<SQL
LOCK TABLE users IN EXCLUSIVE MODE;
SQL;

            $sql['lock']['courses_users'] = <<<SQL
LOCK TABLE courses_users IN EXCLUSIVE MODE;
SQL;

            //This portion ensures that UPDATE will only occur when a record already exists.
            //FIX ME
            $sql['update']['users'] = <<<SQL
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

            $sql['update']['courses_users'] = <<<SQL
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
            $sql['insert']['users'] = <<<SQL
INSERT INTO users (
    user_id,
    user_firstname,
    user_lastname,
    user_preferred_firstname,
    user_email
) SELECT
    upsert_users.u_user_id,
    upsert_users.u_user_firstname,
    upsert_users.u_user_lastname,
    upsert_users.u_user_preferred_firstname,
    upsert_users.u_user_email
FROM upsert_users
LEFT OUTER JOIN users
    ON users.user_id=upsert_users.u_user_id
WHERE users.user_id IS NULL
SQL;

            $sql['insert']['courses_users'] = <<<SQL
INSERT INTO courses_users (
    semester,
    course,
    user_id.
    user_group,
    registration_section,
    manual_registration
) SELECT
    upsert_courses_users.u_semester,
    upsert_courses_users.u_course,
    upsert_courses_users.u_user_id,
    upsert_courses_users.u_user_group,
    upsert_courses_users.u_registration_secton,
    upsert_courses_users.u_manual_registration
FROM upsert_courses_users
LEFT OUTER JOIN users
    ON users.user_id=upsert_courses_users.u_user_id
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
            pg_query(self::$db, $sql['temp_tables']);

            //fills temp table with batch upsert data.
            foreach(self::$data as $i => $row) {
                pg_query_params(self::$db, $sql['data'][$i], array( $row[COLUMN_CSID],
                                                                    $row[COLUMN_FNAME],
                                                                    $row[COLUMN_LNAME],
                                                                    $row[COLUMN_PNAME],
                                                                    $row[COLUMN_EMAIL],
                                                                    $row[COLUMN_SECTION] ));

            }

            pg_query(self::$db, $sql['lock']);
            pg_query(self::$db, $sql['update']);
            pg_query(self::$db, $sql['insert']);
            pg_query_params(self::$db, $sql['dropped_students'], array(4, 'FALSE'));
            pg_query(self::$db, $sql['commit']);

        } else {
/* -----------------------------------------------------------------------------
 * SQL upsert code for Postgres v9.5+ (permits update on conflict)
 * -------------------------------------------------------------------------- */

        }

        //indicate success.
        return true;
    }

    private function log_it($msg) {
    //IN:  Message to write to log file
    //OUT: No return, although log ms queue is updated.
    //PURPOSE: log msg queue holds messages intended for email and text logs.

        self::$log_msg_queue .= date('m/d/y H:i:s : ', time()) . $msg . PHP_EOL;
    }

}
/* EOF ====================================================================== */
?>
