Submitty Student Auto Feed PHP Scripts Readme, August 31 2017

These are code examples for any University to use as a basis to have student
enrollment data inserted or updated into any or every course's database.  Users
table data backup and recovery example code is also provided.

Requires at least PHP 5.4 with pgsql, iconv, and ssh2 extensions.

THIS SOFTWARE IS PROVIDED AS IS AND HAS NO GUARANTEE THAT IT IS SAFE OR
COMPATIBLE WITH YOUR UNIVERSITY'S INFORMATION SYSTEMS.  THIS IS ONLY A CODE
EXAMPLE FOR YOUR UNIVERSITY'S SYSYTEM'S PROGRAMMER TO PROVIDE AN
IMPLEMENTATION.  IT MAY REQUIRE SOME ADDITIONAL MODIFICATION TO SAFELY WORK
WITH YOUR UNIVERSITY'S AND/OR DEPARTMENT'S INFORMATION SYSTEMS.


AUTO INSERT/UPDATE *************************************************************

config.php
A series of define statements that is used to configure the auto feed script.
Code comments will help explain usage.


submitty_student_auto_feed.php
A command line executable script that is a code class to read a student
enrollment data form in CSV format and "upsert" (update/insert) student
enrollment for all registered courses in Submitty.

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
for all Submitty users.  Authentication requires local Linux user accounts,
which can also work with other campus authentication mechanisms like PAM and
Kerberos.  Therefore, submitty_student_auto_feed.php will not create
authentication access for new students upserted into any course database.

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

********************************************************************************
  THESE TOOLS WERE WRITTEN FOR AN EARLIER VERSION OF SUBMITTY AND HAVE NOT YET
       BEEN UPDATED TO BE COMPATIBLE WITH MORE RECENT DATABASE CHANGES.
                 USE OF THESE TOOLS IS CURRENTLY NOT ADVISED.
********************************************************************************

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
This utility will revert the users table data of any specific course to a
backup of a specific date.  This script can also decrypt any encrypted backups.
Decryption is done entirely in RAM -- there are no temp files made during
decryption.


EOF
