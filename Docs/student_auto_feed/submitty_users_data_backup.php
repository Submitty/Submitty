<?php

/* HEADING ---------------------------------------------------------------------
 *
 * config.php script used by submitty_student_auto_feed
 * By Peter Bailie, Systems Programmer (RPI dept of computer science)
 *
 * Requires minimum PHP version 5.4 with pgsql and iconv extensions.
 *
 * This class will read the users table of all courses in config.php and create
 * backup CSV data.  It should only be run when -- and before -- the users table
 * is upserted with new data (per submitty_student_auto_feed.php).
 *
 * Attempts have been made to generalize this code, but different Universities
 * have different information systems, some of which cannot be accounted for.
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
 * (2) instantiate this class to backup users data for all courses listed in
 *     config.php
 *
 * q.v. driver.php
 *
 * -------------------------------------------------------------------------- */

class submitty_users_table_backup {

	private static $today;
	private static $course_list;
	private static $db;
	private static $fh;

	public function __construct() {

		//Important: Make sure we are running from CLI
		if (PHP_SAPI != "cli") {
			die("This is a command line tool.");
		}

		self::$today             = date("ymd", time());
		self::$course_list       = array_map('strtolower', unserialize(COURSE_LIST));

		//Execute processes as soon as object is instantiated.
		foreach(self::$course_list as $course) {

			//Halts when a method returns FALSE (indicates a failure of some kind).
			switch (false) {
			case $this->check_backup_folder($course):
				break;
			case $this->backup_users_to_file($course):
				break;
			case $this->remove_obsolete_backup($course):
				break;
			}
		}
	}

	private function check_backup_folder($course) {
	//IN:  $course whose user data will be backed up.
	//OUT: TRUE when process completes successfully.
	//     FALSE otherwise.
	//PURPOSE: Ensure that a backup folder specifically for $course exists.

		$folder = SUBMITTY_AUTO_FEED_BACKUP . "{$course}/";

		//Make sure backup folder exists.
		if (!file_exists($folder) || !is_dir($folder)) {
			if (!mkdir($folder, 0770, true)) {
				fwrite(STDERR, "Failed to create {$folder}.  No rotation/backup done." . PHP_EOL);
				return false;
			}
		}

		return true;
	}

	private function remove_obsolete_backup($course) {
	//IN:  $course of which obsolete backups will be deleted.
	//OUT: TRUE when process completes successfully.
	//     FALSE otherwise.
	//PURPOSE: Only a certain number of user table backups are kept.  Obsolete
	//         files are automatically removed.

		$folder = SUBMITTY_AUTO_FEED_BACKUP . "{$course}/";

		//Determine oldest date of records to keep.  NOTE: 1 day = 86400 secs.
		$oldest_date = date('ymd', time() - 86400 * DATA_BACKUP_RECORDS_KEPT);

		//Get list of all files (array_slice is to remove '.' and '..' from list)
		$file_list = array_slice(scandir($folder, SCANDIR_SORT_ASCENDING), 2);
		if ($file_list === false) {
			fwrite(STDERR, "Could not read files in {$folder}.  No obsolete files removed." . PHP_EOL);
			return false;
		}

		//Remove any/all older backup files
		foreach($file_list as $file_name) {
			$file_date = substr($file_name, 0, 8);
			if ($file_date < $oldest_date) {
				if (!unlink($folder . $file_name)) {
					fwrite(STDERR, "Could not remove obsolete backup {$course}/{$file_name}." . PHP_EOL);
				}
			}
		}

		return true;
	}

	private function backup_users_to_file($course) {
	//IN:  $course whose users table (and requisite foreign key data) will be
	//     backed up to CSV.
	//OUT: TRUE when process is completed.
	//     FALSE should a problem be encountered (problems are logged)
	//PURPOSE: Collect the appropriate data and write them to a file.
	//NOTE:    Data encryption should be available, if enabled.

		$host     = DB_HOST;
		$user     = DB_LOGIN;
		$password = DB_PASSWORD;
		$month    = intval(substr(self::$today, 2, 2));
		$year     = substr(self::$today, 0, 2);
		//if ($month <= 5) {...} else if ($month >= 8) {...} else {...}
		$semester = ($month <= 5) ? "s{$year}" : (($month >= 8) ? "f{$year}" : "m{$year}");
		$backup_data = array();

		//e.g. backup file for CS-100 on March 15, 2017 might be...
		//     "auto_feed/backups/cs-100/20170315.backup"
		$file_name = SUBMITTY_AUTO_FEED_BACKUP . "{$course}/" . self::$today . ".backup";

		//Cleanup a stale database connection.  There shouldn't be any, but just in case...
		if (is_resource(self::$db) && get_resource_type(self::$db) === 'pgsql link') {
			pg_close(self::$db);
		}

		//Open DB connection.
		self::$db = pg_connect("host={$host} user={$user} password={$password} dbname=submitty_{$semester}_{$course}");
		if (self::$db === false) {
			fwrite(STDERR, "Failed to connect to DB submitty_{$semester}_{$course}.  Skipping course..." . PHP_EOL);
			return false;
		}

		//Read from users table
		$users_query = pg_query(self::$db, "SELECT * FROM users");

		//Read from Sections Rotating table (foreign key constraint on users table)
		$rotating_query = pg_query(self::$db, "SELECT sections_rotating_id FROM sections_rotating");

		//Read from Sections Registration table (foreign key constraint on users table)
		$registration_query = pg_query(self::$db, "SELECT sections_registration_id FROM sections_registration");

		//Make sure queries returned a data resource.
		if ($users_query === false || $rotating_query === false || $registration_query === false) {
			fwrite(STDERR, "DB query failed on submitty_{$semester}_{$course}.  Skipping course..." . PHP_EOL);
			pg_close(self::$db);
			return false;
		}

		//Make sure there is some users data to backup.
		if (pg_num_rows($users_query) > 0) {

			//Collect data.  $db_data is assembled in this order:
			//    headers for users table
			//    All rows from users table
			//    String "[Foreign Key Constraints Data]" (header for next two rows)
			//    All rows of column sections_registration_id condensed to a single row.
			//        OR empty row when there is no data.
			//    All rows of column sections_rotating_id condensed to a single row.
			//        OR empty row when there is no data.

			$db_data = array_merge(array(array_keys(pg_fetch_assoc($users_query, 0))),
								   pg_fetch_all($users_query),
								   array(array("[Foreign Key Constraints Data]")),
								   (pg_fetch_all($registration_query) !== false) ?
									   array(array_column(pg_fetch_all($registration_query), 'sections_registration_id')) :
									   array(array()),
								   (pg_fetch_all($rotating_query) !== false) ?
									   array(array_column(pg_fetch_all($rotating_query), 'sections_rotating_id')) :
									   array(array()));

			//Some additional data massaging.
			foreach ($db_data as &$row) {

				//convert PHP NULL to NULL char for preservation.  (PHP NULL is
				//not the same as NULL char and cannot be specifically written
				//to file)
				foreach($row as &$col) {
					if (is_null($col)) {
						$col = chr(0);
					}
				} unset($col);

				//Combine columns into CSV string (tab delimited) with "\n" EOL.
				$row = implode(chr(9), $row) . chr(10);
			} unset($row);

			//We don't need an open DB connection anymore.
			pg_close(self::$db);

			//Do data encryption (if enabled).
			if (ENABLE_BACKUP_ENCRYPTION) {
				$this->encrypt_backup_data($file_data);
			}

			//Write data to file and check return status.
			if (file_put_contents($file_name, $db_data, LOCK_EX) === false) {
				fwrite(STDERR, "WARNING: Could not write {$file_name}." . PHP_EOL);
				return false;
			}

			//Indicate success.
			return true;
		}

		//No backup done.
		fwrite(STDERR, "Users table returned 0 rows.  No data to backup." . PHP_EOL);
		return false;
	}

	private function encrypt_backup_data(&$data) {
	//IN:  $data to be encrypted, ** passed by reference **
	//OUT: TRUE when $data is encrypted.  FALSE otherwise.
	//PURPOSE:  Student data is protected by FERPA. This encryption method is
	//          to provide an additional layer of information protection.
	//IMPORTANT:  $cipher and $key_length values are preset to industry
	//            recommended values.

		$key_file   = ENCRYPTION_KEY_FILE;
		$cipher     = 'aes-128-cbc';
		$key_length = 16;

		fwrite(STDERR, "Encryption of user table backup data requested." . PHP_EOL);

		//Read and prepare the encryption key
		$key_file_bytes = file_get_contents($key_file);

		if ($key_file_bytes === false) {
			//Key file missing -- generate a new one and log action.
			fwrite(STDERR, "Encryption key file missing." . PHP_EOL);
			$key_file_bytes = $this->get_urandom_bytes($length);

			if ($key_file_bytes === false) {
				fwrite(STDERR, "Failed to generate new encryption key.  Encryption aborted." . PHP_EOL);
				return false;
			}

			if (file_put_contents($key_file, $key_file_bytes, LOCK_EX) === false) {
				$fwrite(STDERR, "Failed to generate a new key file.  Encryption aborted." . PHP_EOL);
				return false;
			}

			fwrite(STDERR, "Successfully generated a new key and file.  Encryption proceeding." . PHP_EOL);
		}

		//Generate initialization vector
		$iv = $this->get_urandom_bytes(openssl_cipher_iv_length($cipher));

		if ($iv === false) {
			fwrite(STDERR, "Failed to generate initialization vector.  Encryption aborted." . PHP_EOL);
			return false;
		}

		//Encrypt backup data
		$tmp = $iv . openssl_encrypt($data, $cipher, $key_file_bytes, OPENSSL_RAW_DATA, $iv);
		if ($tmp === false) {
			fwrite(STDERR, "OpenSSL reports failure when encrypting backup data.  Encryption aborted." . PHP_EOL);
			return false;
		}

		//Indicate success.
		$data = $tmp;
		fwrite(STDERR, "Encryption complete." . PHP_EOL);
		return true;
	}

	private function get_urandom_bytes($length = 16) {
	//IN:  Parameter indicating number of random bytes requested.
	//OUT: Pseudo random binary string intended for secure encryption.
	//     Or FALSE when string cannot be generated.
	//PURPOSE: Secure pseudo random number generation requires a lot of entropy
	//         that cannot be provided by "user-space" PRNGs.  rand() and
	//         mt_rand() are not appropriate and some experts have written that
	//         openssl_random_psuedo_bytes() is also not sufficient.  Mcrypt
	//         library is deprecated in PHP 7.1.  Currently, code compatibility
	//         with PHP 5.5 is required (as per Ubuntu 14.04 LTS).  Therefore,
	//         this function will use Linux kernel device /dev/urandom.

		$bytes = file_get_contents('/dev/urandom', false, null, 0, $length);

		if ($bytes === false) {
			fwrite(STDERR, "/dev/urandom requested but inaccessible." . PHP_EOL);
			return false;
		}

		return $bytes;
	}
} //END User_Table_Backup_Class
// EOF
?>
