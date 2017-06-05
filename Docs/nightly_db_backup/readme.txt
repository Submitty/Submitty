Nightly Database Backup Python Script Readme, May 4 2017

This is intended to be run as a cronjob by 'root' (since file permissions may
be set) on the same server machine as the Submitty system.  Running this script
on another information server other than Submitty has not been tested.

This will read a course list from /var/local/submitty/courses/<current term>
where each course has its own folder.  With a course list, the script will use
Postgresql's "pg_dump" tool to retrieve a SQL dump of every course's Submitty
database.  The script also has cleanup functionality to automatically remove
older dumps.

Each dump has a date stamp in its name following the format of "YYMMD",
followed by the term, then the course code.  e.g. '170504_s17_cs100.dbdump'
is a dump taken on May 4, 2017 of the Spring 2017 term for course CS-100.

Submitty databases can be restored from a dump using the pg_restore tool.
q.v. https://www.postgresql.org/docs/9.5/static/app-pgrestore.html

-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*

WARNING:  Database dumps can contain student information that is protected by
FERPA (20 U.S.C. § 1232g).  It is recommended that database dumps are treated
as highly sensitive data with very prohibitive file access permissions.

-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*

Please configure options near the top of the code.

DB_HOST: Hostname of the Submitty databases.  You can use localhost if
         PostgreSQL is on the same machine as the Submitty system.

DB_USER: The username that interacts with Submitty course databases.  Typically
         'hsdbu'

DB_PASS: The password for Submitty's database account (e.g. account 'hsdbu').
         *** Do NOT use the placeholder value of 'DB.p4ssw0rd' ***

DUMP_PATH: The folder path to store the database dumps.  Course folders will
           be created from this path, and the dumps stored in their respective
           course folders.

EXPIRATION: How many days of dumps to keep.  Older dumps are removed after
            creating the current day's dump.
