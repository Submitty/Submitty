Submitty Student Auto Feed PHP Scripts Readme, August 31 2017

These are code examples for any University to use as a basis to have student
enrollment data inserted or updated into any or every course's database.  Users
table data backup and recovery example code is also provided.

Requires at least PHP 5.6 with pgsql, iconv, and ssh2 extensions.

Instructions can be found at http://submitty.org/sysadmin/student_auto_feed

THIS SOFTWARE IS PROVIDED AS IS AND HAS NO GUARANTEE THAT IT IS SAFE OR
COMPATIBLE WITH YOUR UNIVERSITY'S INFORMATION SYSTEMS.  THIS IS ONLY A CODE
EXAMPLE FOR YOUR UNIVERSITY'S SYSTEM'S PROGRAMMER TO PROVIDE AN
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

The semester may be manually specified on as a command line argument "-s",
otherwise if this is omitted, the semester will be auto-determined by the
server's calendar month and year.

For example:

./submitty_student_auto_feed.php -s s18

Will run the auto feed for the Spring 2018 semester.


accounts.php
This is a command line script that will auto-create PAM authentication accounts
for all Submitty users.  THIS IS NOT NEEDED WITH DATABASE AUTHENTICATION.

The semester may be manually specified on as a command line argument "-s",
otherwise if this is omitted, the semester will be auto-determined by the
server's calendar month and year.

For example:

./accounts.php -s s18

Will run the accounts script for the Spring 2018 semester.

accounts.php is also intended to be run as a cron job, but the requirements are
more stringent.

* Must be run on the Submitty server as root.  Consult a sysadmin for help.
* This is intended to be run as a cron job.  However, because professors can
  manually add users, this script needs to be run more frequently than the
  student auto feed script.
* Recommendation: if this script is run every hour by cronjob, professors can
  advise students who are manually added that they "will have access to Submitty
  within an hour."

EOF
