#!/usr/bin/env php
<?php

/**
 * Gets users from "master" submitty DB and creates PAM authentication accounts.
 *
 * This script will read all user IDs of all active Submitty courses and create
 * PAM authentication accounts on the Submitty server.  This script is intended
 * to be run from the CLI as a scheduled cron job, and should not be executed as
 * part of a website.  This script is not needed when using database
 * authentication.
 *
 * Example Crontab that runs the script ever half hour on the hour
 * (e.g. 8:30, 9:30, 10:30, etc.)
 *
 * "30 * * * * /var/local/submitty/bin/accounts.php"
 *
 * @author Peter Bailie, Systems Programmer (RPI dept of computer science)
 */

error_reporting(0);
ini_set('display_errors', 0);

//List of courses that also need SVN accounts as serialized array.
//Serialzing the array allows it to be defined as a constant.
//NOTE: If there are no courses using SVN, the serialized array must still be
//      defined, but make it an empty array.
define('SVN_LIST', serialize( array (
'cs1000',
'cs2000',
'cs3000',
'cs4000',
)));

//Database access
define('DB_LOGIN',  'hsdbu');
define('DB_PASSWD', 'hsdbu_pa55w0rd');
define('DB_HOST',   'localhost');

//Location of accounts creation error log file
define('ERROR_LOG_FILE', '/var/local/submitty/bin/accounts_errors.log');

//Where to email error messages so they can get more immediate attention.
define('ERROR_E_MAIL', 'sysadmins@lists.myuniversity.edu');

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

//Univeristy campus's timezone.
date_default_timezone_set('America/New_York');

//Start process
main();
exit(0);

/**
 * Main process
 */
function main() {
	//IMPORTANT: This script needs to be run as root!
	if (posix_getuid() !== 0) {
		exit("This script must be run as root." . PHP_EOL);
	}

	//Determine current semester
	$month = intval(date("m", time()));
	$year  = date("y", time());

	//if ($month <= 5) {...} else if ($month >= 8) {...} else {...}
	$semester = ($month <= 5) ? "s{$year}" : (($month >= 8) ? "f{$year}" : "m{$year}");
	$courses  = determine_courses($semester);

	foreach($courses as $course) {
		if (array_search($course, unserialize(SVN_LIST)) !== false) {
			//Create both auth account and SVN account
			//First make sure SVN repo exists
			if (!file_exists("/var/lib/svn/{$course}")) {
				mkdir("/var/lib/svn/{$course}");
			}
			$user_list = get_user_list_from_course_db($semester, $course);
			foreach($user_list as $user) {
				//Let's make sure SVN account doesn't already exist before making it.
				if (!file_exists("/var/lib/svn/{$course}/{$user}")) {
					system ("/usr/sbin/adduser --quiet --home /tmp --gecos 'RCS auth account' --no-create-home --disabled-password --shell /usr/sbin/nologin {$user} > /dev/null 2>&1");
					system ("svnadmin create /var/lib/svn/{$course}/{$user}");
					system ("touch /var/lib/svn/{$course}/{$user}/db/rep-cache.db");
					system ("chmod g+w /var/lib/svn/{$course}/{$user}/db/rep-cache.db");
					system ("chmod 2770 /var/lib/svn/{$course}/{$user}");
					system ("chown -R www-data:svn-{$course} /var/lib/svn/{$course}/{$user}");
					system ("ln -s /var/lib/svn/hooks/pre-commit /var/lib/svn/{$course}/{$user}/hooks/pre-commit");
				}
			}
			//Restart Apache
			system ("/root/bin/regen.apache > /dev/null 2>&1");
			system ("/usr/sbin/apache2ctl -t > /dev/null 2>&1");
		} else {
			//Only create auth account
			$user_list = get_user_list_from_course_db($semester, $course);
			foreach($user_list as $user) {
				//We don't care if user already exists as adduser will skip over any account that already exists.
				system ("/usr/sbin/adduser --quiet --home /tmp --gecos 'RCS auth account' --no-create-home --disabled-password --shell /usr/sbin/nologin {$user} > /dev/null 2>&1");
			}
		}
	}
}

/**
 * Retrieve list of active courses
 *
 * @param string $semester
 * @return array
 */
 function determine_courses($semester) {
 	//Retrieve course list from file system (each course has its own folder).
	$path = "/var/local/submitty/courses/{$semester}/";
	$courses = scandir($path);
	if ($courses === false) {
		log_it("Submitty Auto Account Creation: Cannot parse {$path}, CANNOT MAKE ACCOUNTS");
		exit(1);
	}
	//remove ".", "..", and hidden file entries from courses list and return.
	return array_filter($courses, function($elem) {return preg_match("~^[^\.]+~", $elem);});
}

/**
 * Retrieve user list from a course database
 *
 * @param string $semester
 * @param string $course
 * @return array
 */
function get_user_list_from_course_db($semester, $course) {
	$db_user = DB_LOGIN;
	$db_pass = DB_PASSWD;
	$db_host = DB_HOST;
	$db_name = "submitty_{$semester}_{$course}";
	$db_conn = pg_connect("host={$db_host} dbname={$db_name} user={$db_user} password={$db_pass}");
	if ($db_conn === false) {
		log_it("Submitty Auto Account Creation: Cannot connect to DB {$db_name}, skipping...");
		return array();
	}
	$db_query = pg_query($db_conn, "SELECT user_id FROM users;");
	if ($db_query === false) {
        log_it("Submitty Auto Account Creation: Cannot read user list for {$course}, skipping...");
		return array();
	}
	$user_list = pg_fetch_all_columns($db_conn, 0);
	pg_close($db_conn);
	return $user_list;
}

/**
 * Log message to email and text files
 *
 * @param string $msg
 */
function log_it($msg) {
    $msg = date('m/d/y H:i:s : ', time()) . $msg . PHP_EOL;
    error_log(msg, 1, ERROR_E_MAIL);
   	error_log(msg, 3, ERROR_LOG_FILE);
}

/* EOF ====================================================================== */
?>
