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

	//Get user list and create local accounts.
	$user_list = get_user_list_from_master_db($semester);
	foreach($user_list as $user) {
		//We don't care if user already exists as adduser will skip over any account that already exists.
		system ("/usr/sbin/adduser --quiet --home /tmp --gecos 'RCS auth account' --no-create-home --disabled-password --shell /usr/sbin/nologin {$user} > /dev/null 2>&1");
	}
}

/**
 * Retrieve user list from "master" database
 *
 * @param string $semester
 * @return array
 */
function get_user_list_from_master_db($semester) {
	$db_user = DB_LOGIN;
	$db_pass = DB_PASSWD;
	$db_host = DB_HOST;
	$user_list = array();

	$db_conn = pg_connect("host={$db_host} dbname=submitty user={$db_user} password={$db_pass}");
	if ($db_conn === false) {
		log_it("Submitty Auto Account Creation: Cannot connect to master DB.");
		return array();
	}

	$db_query = pg_query($db_conn, "SELECT user_id FROM users;");
	if ($db_query === false) {
        log_it("Submitty Auto Account Creation: Cannot read user list frommaster DB");
		return array();
	}

//	$row = pg_fetch_row($db_query);
//	while($row !== false) {
//		$user_list[] = $row[0];
//		$row = pg_fetch_row($db_query);
//	}
//
//	return $user_list;

	return pg_fetch_all_columns($db_query, 0);
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
