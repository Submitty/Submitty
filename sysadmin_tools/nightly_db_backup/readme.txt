Nightly Database Backup Python Script Readme

THIS SOFTWARE IS PROVIDED AS IS AND HAS NO GUARANTEE THAT IT IS SAFE OR
COMPATIBLE WITH YOUR UNIVERSITY'S INFORMATION SYSTEMS.  THIS IS ONLY A CODE
EXAMPLE FOR YOUR UNIVERSITY'S SYSTEM'S PROGRAMMER TO PROVIDE AN
IMPLEMENTATION.  IT MAY REQUIRE SOME ADDITIONAL MODIFICATION TO SAFELY WORK
WITH YOUR UNIVERSITY'S AND/OR DEPARTMENT'S INFORMATION SYSTEMS.

This script will read a course list, corresponding to a specific semester, from
the 'master' Submitty database.  With a course list, the script will use
Postgresql's "pg_dump" tool to retrieve a SQL dump of the submitty 'master'
database and each registered course's Submitty database of a specific semester.
The script also has cleanup functionality to automatically remove older dumps.

--------------------------------------------------------------------------------

The semester code can be specified as a command line argument as option "-s".

For example:

./db_backup.py -s f17

will dump the submitty 'master' database and all courses registered in the fall
2017 semester.  This option is useful to dump course databases of previous
semesters, or to dump course databases that have a non-standard semester code.

Alternatively, the "-sauto" option will have the semester code automatically
determined by the current month and year of the server's date.   e.g. April of
2018 will be the semester code "s18".

Either "-s" or "-sauto" is required.

--------------------------------------------------------------------------------

Each dump has a date stamp in its name following the format of "YYMMD",
followed by the semester code, then the course code.

e.g. '180423_s18_cs100.dbdump' is a dump taken on April 23, 2018 of the Spring
2018 semester for course CS-100.

Older dumps can be automatically purged with the command line option "-e".

For example:

./db_backup.py -e 7

will purge any dumps with a stamp seven days or older.  Only dumps of the
semester being processed will be purged.

The default expiration value is 0 (no expiration -- no files are purged) should
this argument be ommitted.

--------------------------------------------------------------------------------

Both command line options may be used.

For example:

./db_backup.py -s f17 -e 7

will backup current databases for the Fall 2017 semester and purge old database
dumps that are seven days or older of the Fall 2017 semester (all other semester
dumps remain untouched).

--------------------------------------------------------------------------------

Submitty databases can be restored from a dump using the pg_restore tool.
q.v. https://www.postgresql.org/docs/9.5/static/app-pgrestore.html

This is script intended to be run as a cronjob by 'root' on the same server
machine as the Submitty system.  Running this script on another server other
than Submitty has not been tested.

-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*

WARNING:  Database dumps can contain student information that is protected by
FERPA (20 U.S.C. § 1232g).  It is recommended that database dumps are treated
as highly sensitive data with very prohibitive access permissions.

-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*

Please configure options near the top of the code.

DB_HOST: Hostname of the Submitty databases.  You may use 'localhost' if
         Postgresql is on the same machine as the Submitty system.

DB_USER: The username that interacts with Submitty course databases.  Typically
         'hsdbu'

DB_PASS: The password for Submitty's database account (e.g. account 'hsdbu').
         *** Do NOT use the placeholder value of 'DB.p4ssw0rd' ***

DUMP_PATH: The folder path to store the database dumps.  Course folders will
           be created from this path, and the dumps stored in their respective
           course folders, grouped by semester.
