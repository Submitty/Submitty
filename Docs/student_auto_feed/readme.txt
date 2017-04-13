Submitty Student Auto Feed PHP Scripts Readme, April 13 2017

These are code examples for any University to use as a basis to have student
enrollment data inserted or updated into any or every course's database.  Users
table data backup and recovery example code is also provided.

Requires at least PHP 5.4 with pgsql and iconv extensions.

THIS SOFTWARE IS PROVIDED AS IS AND HAS NO GUARANTEE THAT IT IS SAFE OR
COMPATIBLE WITH YOUR UNIVERSITY'S INFORMATION SYSTEMS.  THIS IS ONLY A CODE
EXAMPLE FOR YOUR UNIVERSITY'S SYSYTEM'S PROGRAMMER TO PROVIDE AN
IMPLEMENTATION.  IT MAY REQUIRE SOME ADDITIONAL MODIFICATION TO SAFELY WORK
WITH YOUR UNIVERSITY'S AND/OR DEPARTMENT'S INFORMATION SYSTEMS.


AUTO INSERT/UPDATE *************************************************************

driver.php
This is executed from the command line to run the users table data backup and
insert/update classes.


config.php
A series of define statements that is used to configure the auto feed, backup,
and restore tools.  Code comments will help explain usage.  This code file must
be "required" before the others, in order for the tools work.


submitty_student_auto_feed.php
Invoked by driver.php.  This is a code class to read a student enrollment data
form in CSV format and "upsert" (update/insert) student enrollment for all
registered courses in Submitty.

This code assumes that all student enrollments for all courses are in a single
CSV file.  Extra courses can exist in the data (such as a department wide CSV),
and any enrollments for courses not registered in Submitty are ignored.

Conceptually, a University's registrar and/or data warehouse will provide a
regular data dump, uploaded somewhere as a CSV file.  Then with the automatic
uploads scheduled, a sysadmin should setup a cron job to regularly trigger this
script to run sometime after the data dump is uploaded.

This code does not need to be run specifically on the Submitty server, but it
will need access to the Submitty course databases and the CSV data dump file.


accounts.php
This is a command line script that will auto-create user authentication accounts
for all Submitty users.  It should be noted that Submitty authentication is not
tied to the databases' users table.  Instead, authentication requires local
Linux user accounts, which can also work with other campus authentication
mechanisms like PAM and Kerberos.  Therefore, submitty_student_auto_feed.php
will not create authentication access for new students upserted into any course
database.

accounts.php is also intended to be run as a cron job, but the requirements are
more stringent.

* Must be run on the Submitty server as root.  Consult a sysadmin for help.
* This is intended to be run as a cron job.  However, because professors can
  manually add users, this script needs to be run more frequently than the
  student auto feed script.
* Recommendation: if this script is run every hour by cronjob, professors can
  advise students who are manually added that they "will have access to Submitty
  within an hour."


BACKUP/RECOVERY ****************************************************************

submitty_student_auto_feed.php has data validation checks to help preserve the
integrity of the courses' database users table from a bad feed.  Should a feed
of bad data manage to get past validation and corrupt any/all users table, the
following tools may be able to assist with quick recovery.


submitty_users_data_backup.php
Invoked by driver.php and requires config.php.  If used, this has to be run
BEFORE submitty_student_auto_feed.php.  The number of days of backups needs to
be defined in config.php (default: 7).  As users data contains student data
protected by FERPA, an optional AES encryption feature is also provided
(disabled by default).  IMPORTANT:  This script will generate an encryption key
(using /dev/urandom) when a key is not found.  It is vital that this key is
given very strict access permissions.  If the key is ever leaked, all encrypted
backups become vulnerable.


restore_backup.php
A command line script to (optionally) backup the users table data of all
Submitty courses.  This needs to be run BEFORE submitty_student_auto_feed.php.
The number of days of backups needs to be defined (recommended: 7).  As users
data contains data protected by FERPA, an optional AES encryption feature is
also provided.  IMPORTANT:  This script will generate an encryption key (using
/dev/urandom) when a key is not found.  It is vital that this key is given the
utmost access protection.  If the key is ever leaked, your encrypted backups
are vulnerable.


restore_backup.php
This utility will revert the users table data of any specific course to a
backup of a specific date.  This script can also decrypt any encrypted backups.
Decryption is done entirely in RAM -- there are no temp files made during
decryption.


EOF
