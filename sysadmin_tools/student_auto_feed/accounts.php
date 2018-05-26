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
 * You may specify the term on the command line with "-t".
 * "-g" can be used to guess the term by the server's calendar month and year.
 * For example:
 *
 * ./accounts.php -t s18
 *
 * Will create PAM auth accounts for the Spring 2018 semester.
 *
 * @author Peter Bailie, Systems Programmer (RPI dept of computer science)
 */

error_reporting(0);
ini_set('display_errors', 0);

//List of courses that also need SVN accounts as serialized array.
//Serializing the array allows it to be defined as a constant.
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

//University campus's timezone.
date_default_timezone_set('America/New_York');

//Start process
main();
exit(0);

/** Main process */
function main() {
	//IMPORTANT: This script needs to be run as root!
	if (posix_getuid() !== 0) {
		exit("This script must be run as root." . PHP_EOL);
	}

	//Check for semester among CLI arguments.
	$semester = cli_args::parse_args();
	if ($semester === false) {
	    exit(1);
	}

	$courses = determine_courses($semester);

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
	$user_list = pg_fetch_all_columns($db_query, 0);
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
    error_log($msg, 1, ERROR_E_MAIL);
   	error_log($msg, 3, ERROR_LOG_FILE);
}

/** @static class to parse command line arguments */
class cli_args {

    /** @var array holds all CLI argument flags and their values */
	private static $args;
    /** @var string usage help message */
	private static $help_usage      = "Usage: accounts.php [-h | --help] (-t [term code] | -g)" . PHP_EOL;
    /** @var string short description help message */
	private static $help_short_desc = "Read student enrollment from Submitty DB and create accounts for PAM auth." . PHP_EOL;
    /** @var string argument list help message */
	private static $help_args_list  = <<<HELP
Arguments
-h --help       Show this help message.
-t [term code]  Term code associated with student enrollment.
-g              Guess the term code based on calendar month and year.

NOTE: -t and -g are mutally exclusive.  One is required.

HELP;

	/**
	 * Parse command line arguments
	 *
	 * Called with 'cli_args::parse_args()'
	 *
	 * @access public
	 * @return mixed term code as string or boolean false when no term code is present.
	 */
	public static function parse_args() {

		self::$args = getopt('hgt:', array('help'));

		switch(true) {
		case array_key_exists('h', self::$args):
		case array_key_exists('help', self::$args):
			self::print_help();
			return false;
		case array_key_exists('g', self::$args):
			if (array_key_exists('t', self::$args)) {
				//-t and -g are mutually exclusive
				print "-g and -t cannot be used together." . PHP_EOL;
				return false;
			} else {
				//Guess current term
				//(s)pring is month <= 5, (f)all is month >= 8, s(u)mmer are months 6 and 7.
				//if ($month <= 5) {...} else if ($month >= 8) {...} else {...}
				$month = intval(date("m", time()));
				$year  = date("y", time());
				return ($month <= 5) ? "s{$year}" : (($month >= 8) ? "f{$year}" : "u{$year}");
			}
		case array_key_exists('t', self::$args):
			return self::$args['t'];
		default:
			print self::$help_usage . PHP_EOL;
			return false;
		}
	}

	/**
	 * Print extended help to console
	 *
	 * @access private
	 */
	private static function print_help() {

		//Usage
		print self::$help_usage . PHP_EOL;
		//Short description
		print self::$help_short_desc . PHP_EOL;
		//Arguments list
		print self::$help_args_list . PHP_EOL;
	}
}

/* EOF ====================================================================== */
?>
