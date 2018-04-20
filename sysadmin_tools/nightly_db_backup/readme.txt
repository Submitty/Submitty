Nightly Database Backup Python Script Readme, April 20 2018

THIS SOFTWARE IS PROVIDED AS IS AND HAS NO GUARANTEE THAT IT IS SAFE OR
COMPATIBLE WITH YOUR UNIVERSITY'S INFORMATION SYSTEMS.  THIS IS ONLY A CODE
EXAMPLE FOR YOUR UNIVERSITY'S SYSTEM'S PROGRAMMER TO PROVIDE AN
IMPLEMENTATION.  IT MAY REQUIRE SOME ADDITIONAL MODIFICATION TO SAFELY WORK
WITH YOUR UNIVERSITY'S AND/OR DEPARTMENT'S INFORMATION SYSTEMS.

This script will read a course list, corresponding to a specific semester, from
the 'master' Submitty database.  With a course list, the script will use
Postgresql's "pg_dump" tool to retrieve a SQL dump of each course's Submitty
database.  The script also has cleanup functionality to automatically remove
older dumps.

The semester code can be specified as a command line argument.  e.g.

./db_backup.py f17

will dump all courses registered in the fall 2017 semester.  This is useful
to dump course databases of previous semesters, or to dump course databases
that have a non-standard semester code.  If no command line argument is given,
the semester code will be automatically determined by the current month and
year.  e.g. April of 2018 will be the semester code "s18".

Each dump has a date stamp in its name following the format of "YYMMD",
followed by the term, then the course code.  e.g. '180420_s18_cs100.dbdump'
is a dump taken on April 20, 2018 of the Spring 2018 term for course CS-100.

Submitty databases can be restored from a dump using the pg_restore tool.
q.v. https://www.postgresql.org/docs/9.5/static/app-pgrestore.html

This is intended to be run as a cronjob by 'root' (since file permissions may
be set) on the same server machine as the Submitty system.  Running this script
on another information server other than Submitty has not been tested.

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

EXPIRATION: How many days of dumps to keep.  Older dumps are removed after
            creating the current day's dump.  This only deletes dumps of
            the semester being processed.  Other semester dumps will not be
            removed.
