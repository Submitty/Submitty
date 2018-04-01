#!/usr/bin/env php
<?php

/* HEADING ---------------------------------------------------------------------
 *
 * accounts.php
 * By Peter Bailie, Systems Programmer (RPI dept of computer science)
 *
 * This script is intended to be run from the CLI as a scheduled cron job, and
 * should not be executed as part of a website.
 *
 * This script will read all user IDs of all active Submitty courses and create
 * auth and svn access accounts on the Submitty server.
 *
 * -------------------------------------------------------------------------- */

error_reporting(0);
ini_set('display_errors', 0);

//list of courses that also need SVN accounts as serialized array.
//NOTE: Serializing the array allows the list to be defined as a constant.
define('SVN_LIST', serialize( array (
'cs1000',
'cs2000',
'cs3000',
'cs4000',
)));

//Database access
define('DB_LOGIN',  'hsdbu');
define('DB_PASSWD', 'hsdbu_pa55w0rd');
define('DB_HOST',   '192.168.56.101');

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
/*$default_zone = "America/New_York";
$timezone = file_get_contents("/etc/timezone");
if (!strlen($timezone)) {
    $timezone = $default_zone;
}
date_default_timezone_set($timezone);

/* EXAMPLE CRONTAB -------------------------------------------------------------
 *
 * This will run the script every hour at the half-hour (e.g. 8:30, 9:30, etc).

30 * * * * /var/local/submitty/bin/accounts.php

 * -------------------------------------------------------------------------- */

/* MAIN ===================================================================== */
//IMPORTANT: This script needs to be run as root!
if (posix_getuid() !== 0) {
	exit("This script must be run as root." . PHP_EOL);
}

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

exit(0);
/* END MAIN ================================================================= */

function determine_courses($semester) {
//IN:  Parameter has the current semester code (e.g. "f16" for Fall 2016)
//OUT: Array of courses used in Submitty.  Determined from data file structure.
//PURPOSE: A list of active courses is needed so that user lists can be read
//         from current class databases.

	$path = "/var/local/submitty/courses/{$semester}/";
	$courses = scandir($path);

	if ($courses === false) {
		log_it("Submitty Auto Account Creation: Cannot parse {$path}, CANNOT MAKE ACCOUNTS");
		exit(1);
	}

	//remove "." and ".." entries
	foreach ($courses as $index => $course) {
		if ($course[0] === '.') {
			unset($courses[$index]);
		}
	}

	return $courses;
}

function get_user_list_from_course_db($semester, $course) {
//IN:  The current course code with semester code (needed to access course DB)
//OUT: An array containing the user list read from the course's database
//PURPOSE:  Read all user_ids from the user list to create auth/svn accounts.

	$db_user = DB_LOGIN;
	$db_pass = DB_PASSWD;
	$db_host = DB_HOST;
	$db_name = "submitty_{$semester}_{$course}";

	$user_list = array();

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

	$row = pg_fetch_row($db_query);
	while($row !== false) {
		$user_list[] = $row[0];
		$row = pg_fetch_row($db_query);
	}

	return $user_list;
}

function log_it($msg) {
//IN:  Message to write to log file
//OUT: No return, although log file is updated
//PURPOSE: Log messages to email and text files.

    $msg = date('m/d/y H:i:s : ', time()) . $msg . PHP_EOL;

    error_log(msg, 1, ERROR_E_MAIL);
   	error_log(msg, 3, ERROR_LOG_FILE);
}

/* EOF ====================================================================== */
?>
