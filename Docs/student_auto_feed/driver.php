#!/usr/bin/env php
<?php

/* HEADING ---------------------------------------------------------------------
 *
 * Submitty Student Information Auto Feed -- driver.php
 * By Peter Bailie, Systems Programmer (RPI dept of computer science)
 *
 * Requires minimum PHP version 5.4 with pgsql and iconv extensions.  This
 * driver script and required classes are intended to be run from the CLI as a
 * scheduled cron job.  Invoke this driver from command line to run the users
 * data backup and auto feed.
 *
 * This is designed so that the university's registrar is sending a data dump
 * of student enrollment for all courses used in Submitty, perhaps even a whole
 * department's courses, in a single CSV file.
 *
 * Attempts have been made to generalize this code, but different Universities
 * have different information systems, some of which cannot be accounted for.
 *
 * There is no log module, but pertinant activity/error messages are written to
 * STDERR, which could be redirected to a text file.
 *
 * THIS SOFTWARE IS PROVIDED AS IS AND HAS NO GUARANTEE THAT IT IS SAFE OR
 * COMPATIBLE WITH YOUR UNIVERSITY'S INFORMATION SYSTEMS.  THIS IS ONLY A CODE
 * EXAMPLE FOR YOUR UNIVERSITY'S SYSYTEM'S PROGRAMMER TO PROVIDE AN
 * IMPLEMENTATION.  IT MAY REQUIRE SOME ADDITIONAL MODIFICATION TO SAFELY WORK
 * WITH YOUR UNIVERSITY'S AND/OR DEPARTMENT'S INFORMATION SYSTEMS.
 *
 * -------------------------------------------------------------------------- */

/* DRIVER =================================================================== */

require "config.php";
//require "submitty_users_data_backup.php";
require "submitty_student_auto_feed.php";

//Comment out line 'submitty_users_table_backup()' to disable user data backup.
//new submitty_users_table_backup();
new submitty_student_auto_feed();

exit(0);

?>
