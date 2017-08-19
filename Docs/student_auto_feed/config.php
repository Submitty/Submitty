<?php

/* HEADING ---------------------------------------------------------------------
 *
 * config.php script used by submitty_student_auto_feed
 * By Peter Bailie, Systems Programmer (RPI dept of computer science)
 *
 * Requires minimum PHP version 5.4 with pgsql, iconv, and ssh2 extensions.
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


/* Definitions for error logging -------------------------------------------- */
// While not recommended, email reports of errors may be disabled by setting
// 'ERROR_EMAIL' to null.
define('ERROR_EMAIL',    'sysadmins@lists.myuniversity.edu');
define('ERROR_LOG_FILE', '/var/local/submitty/bin/auto_feed_error.log');


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

// The following constants are used to read the CSV auto feed file provided by
// the registrar / data warehouse.  ***THESE NEED TO BE SET.
//
// CSV_AUTH can be set to 'local' or 'remote_password' or 'remote_keypair'.
//
//          'local' means the CSV file can be read locally by the script, so no
//          remote authentication details are needed.
//
//          'remote_password' means that the file must be accessed on another
//           server, and authentication is by password.
//           q.v. CSV_AUTH_PASSWORD
//
//           'remote_key' means that the file must be accessed on another
//           server, and authentication is by RSA key pair.
//           q.v. CSV_AUTH_PUBKEY, CSV_AUTH_PRIVKEY, CSV_PRIVKEY_PASSPHRASE
//
// CSV_FILE is the full path of the student auto feed file, regardless if it is
//          accessed locally or remotely.
//
// CSV_REMOTE_SERVER is the fully qualified domain name of the server that hosts
//                   the student feed CSV file.  This constant is ignored when
//                   CSV_AUTH is set to 'local'.
//
// CSV_AUTH_USER is the user account to access the student feed CSV, when the
//               file exists on a remote server.  This constant is ignored when
//               CSV_AUTH is set to 'local'.
//
// CSV_AUTH_PASSWORD is the user account password required to access the student
//                   feed CSV on a remote server.  This constant is ignored when
//                   CSV_AUTH is set to anything other than 'remote_password'.
//
// CSV_AUTH_PUBKEY is the path to the public key used to authenticate with the
//                 remote server that has the student feed CSV.  The public key
//                 needs to be in OpenSSH format.  This constant is ignored
//                 when CSV_AUTH is set to anything other than 'remote_keypair'.
//
// CSV_AUTH_PRIVKEY is the path to the private key used to communicate with the
//                  remote server that has the student feed CSV.  This constant
//                  is ignored when CSV_AUTH is set to anything other than
//                  'remote_keypair'.
//
// CSV_PRIVKEY_PASSPHRASE is the passphrase used to encrypt the private key.
//                        Set to null, if the private key is not encrypted.
//                        This constant is ignored when CSV_AUTH is set to
//                        anything other than 'remote_keypair'.
define('CSV_AUTH',               'remote_keypair');
define('CSV_FILE',               '/path/to/datafile.csv');
define('CSV_REMOTE_SERVER',      'fileserver.myuniversity.edu');
define('CSV_AUTH_USER',          'remote_user');
define('CSV_AUTH_PASSWORD',      null);
define('CSV_AUTH_PUBKEY',        '/path/to/rsa_key.pub');
define('CSV_AUTH_PRIVKEY',       '/path/to/rsa_key.pfx');
define('CSV_PRIVKEY_PASSPHRASE', 'MySecretPassphrase');

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
//      students' registration data -- define COLUMN_PREFERREDNAME as null.
define('COLUMN_COURSE_PREFIX', 8);  //Course prefix
define('COLUMN_COURSE_NUMBER', 9);  //Course number
define('COLUMN_REGISTRATION',  7);  //Student enrollment status
define('COLUMN_SECTION',       10); //Section student is enrolled
define('COLUMN_USER_ID',       5);  //Student's computer systems ID
define('COLUMN_FIRSTNAME',     2);  //Student's First Name
define('COLUMN_LASTNAME',      1);  //Student's Last Name
define('COLUMN_PREFERREDNAME', 3);  //Student's Preferred Name
define('COLUMN_EMAIL',         4);  //Student's Campus Email
define('COLUMN_TERM_CODE',     11); //Semester code used in data validation

//Validate term code.  Set to null to disable this check.
define('EXPECTED_TERM_CODE', '201705');

//Sometimes data feeds are generated by Windows systems, in which case the data
//file probably needs to be converted from Windows-1252 (aka CP-1252) to UTF-8.
//Set to true to convert data feed file from Windows char encoding to UTF-8.
//Set to false if data feed is already provided in UTF-8.
define('CONVERT_CP1252', true);

//Allows "\r" EOL encoding.  This is rare but exists (e.g. Excel for Macintosh).
ini_set('auto_detect_line_endings', true);

//Needed to access student feed on a remote server.
//You can comment this out if the student feed is accessed locally.
ini_set("allow_url_fopen", true);


/* USER TABLE BACKUP OPTIONS ------------------------------------------------ */

/* *****************************************************************************
 BACKUP TOOLS WERE WRITTEN FOR AN EARLIER VERSION OF SUBMITTY AND HAVE NOT YET
       BEEN UPDATED TO BE COMPATIBLE WITH MORE RECENT DATABASE CHANGES.
                 USE OF THESE TOOLS IS CURRENTLY NOT ADVISED.
***************************************************************************** */

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

?>
