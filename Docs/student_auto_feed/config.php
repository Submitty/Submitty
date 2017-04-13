<?php

/* HEADING ---------------------------------------------------------------------
 *
 * config.php script used by submitty_student_auto_feed
 * By Peter Bailie, Systems Programmer (RPI dept of computer science)
 *
 * Requires minimum PHP version 5.4 with pgsql and iconv extensions.
 *
 * Configuration of submitty_student_auto_feed is structured through a series
 * of named constants.  This configuration is also used by
 * submitty_users_data_backup.php
 * submitty_users_data_rollback.php
 *
 * THIS SOFTWARE IS PROVIDED AS IS AND HAS NO GUARANTEE THAT IT IS SAFE OR
 * COMPATIBLE WITH YOUR UNIVERSITY'S INFORMATION SYSTEMS.  THIS IS ONLY A CODE
 * EXAMPLE FOR YOUR UNIVERSITY'S SYSYTEM'S PROGRAMMER TO PROVIDE AN
 * IMPLEMENTATION.  IT MAY REQUIRE SOME ADDITIONAL MODIFICATION TO SAFELY WORK
 * WITH YOUR UNIVERSITY'S AND/OR DEPARTMENT'S INFORMATION SYSTEMS.
 *
 * -------------------------------------------------------------------------- */

//Course list to be processed.  Other courses in the CSV feed will be ignored.
//Elements should be string data representing the course, both prefix and
//number.  Do not seperate or deliminate prefix and number.
//***THIS NEEDS TO BE SET as a serialized array.
define('COURSE_LIST', serialize( array(
'cs100',
'cs150',
'cs200',
'cs250',
'cs300',
'cs350',
'cs400'
)));

//Some Universities will hold mixed enrollment of graduate and undergraduate
//students and/or corss registered courses in a single classroom.  Having
//multiple Submitty courses for one whole classroom may be inconvenient, so this
//list will map enrollment of a "base" course to all related courses.
//
//Enrollees in any mapped courses will appear in additional sections in the
//"base" course.  ***THIS NEEDS TO BE SET as arrays of a serialized array.

/* EXAMPLES --------------------------------------------------------------------
 *
 * CS-300 is mapped to graduate course CS-500 only.  Entry is as follows:
 *
 * 'cs300' => array('cs500')
 *
 * CS-400 is mapped to graduate course CS-600 and cross registered with courses
 * IT-400 and IT-600.  Entry is as follows:
 *
 * 'cs400' => array('cs600', 'it400', 'it600')
 *
 * -------------------------------------------------------------------------- */

define('COURSE_MAPPINGS', serialize( array(
'cs300' => array('cs500'),
'cs400' => array('cs600', 'it400', 'it600')
)));

//Student registration status is important, as data dumps can contain students
//who have dropped a course either before the semester starts or during the
//semester.  This serialized array will contain all valid registered-student
//codes can be expected in the data dump.
//***THIS NEEDS TO BE SET as a serialized array.
//
//IMPORTANT: Consult with your University's IT administrator and/or registrar to
//           add all pertinant student-is-registered codes that can be found in
//           your CSV data dump.  EXAMPLE: 'RA' may mean "registered by advisor"
//           and 'RW' may mean "registered via web"
define('STUDENT_REGISTERED_CODES', serialize( array(
'RA',
'RW',
)));

//An exceptionally small file size can indicate a problem with the feed, and
//therefore the feed should not be processed to preserve data integrity of the
//users table.  Value is in bytes.  You should pick a reasonable minimum
//threshold based on the expected student enrollment (this could vary a lot by
//university and courses taught).
define('VALIDATE_MIN_FILESIZE', 65536);

//How many columns the CSV feed has (this includes any extraneous columns in the
//CSV that are not needed by submitty_student_auto_feed).
define('VALIDATE_NUM_FIELDS', 10);

//The path/file or URL provided by the registrar.  ***THIS NEEDS TO BE SET.
define('CSV_FILE', '/path/to/datafile.csv');

//Try uncommenting this if there are problems accessing CSV file by URL.
//ini_set("allow_url_fopen", true);

//Define what character is delimiting each field.  ***THIS NEEDS TO BE SET.
//EXAMPLE: chr(9) is the tab character.
define('CSV_DELIM_CHAR', chr(9));

//Properties for database access.  ***THESE NEED TO BE SET.
define('DB_HOST',     'submitty.cs.myuniversity.edu');
define('DB_LOGIN',    'hsdbu');
define('DB_PASSWORD', 'DB.p4ssw0rd');

/* The following constants identify what columns to read in the CSV dump. --- */
//these properties are used to group data by individual course and student.
//NOTE: If your University does not support "Student's Preferred Name" in its
//      students' registration data -- define COLUMN_PNAME as null.
define('COLUMN_COURSE_PREFIX', 8);  //Course prefix
define('COLUMN_COURSE_NUMBER', 9);  //Course number
define('COLUMN_REGISTRATION',  7);  //Student enrollment status
define('COLUMN_SECTION',       10); //Section student is enrolled
define('COLUMN_CSID',          5);  //Student's computer systems ID
define('COLUMN_FNAME',         2);  //Student's First Name
define('COLUMN_LNAME',         1);  //Student's Last Name
define('COLUMN_PNAME',         3);  //Student's Preferred Name
define('COLUMN_EMAIL',         4);  //Student's Campus Email

//Sometimes data feeds are generated by Windows systems, in which case the data
//file probably needs to be converted from Windows-1252 (aka CP-1252) to UTF-8.
//Set to true to convert data feed file from Windows char encoding to UTF-8.
//Set to false if data feed is already provided in UTF-8.
define('CONVERT_CP1252', true);

//Allow "\r" EOL encoding when reading CSV.  This is rare, but just in case...
ini_set("auto_detect_line_endings", true);


/* USER TABLE BACKUP OPTIONS ------------------------------------------------ */
//Folder where backup data is stored.  Backups are CSV files sorted into folders
//by each indiividual Submitty course.  **THIS NEEDS TO BE SET
define('SUBMITTY_AUTO_FEED_BACKUP', '/path/to/user_data_backups');

//How many days of user data backups to retain per course.
define('DATA_BACKUP_RECORDS_KEPT', 7);

//Set to TRUE to use file encryption of backup data.
define('ENABLE_BACKUP_ENCRYPTION', false);

//Access permissions to the keyfile must be strictly maintained.  Just like with
//accessing the CSV, the path to the key_file may also be a URL.
define('ENCRYPTION_KEY_FILE',  '/path/to/key_file');


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

?>
