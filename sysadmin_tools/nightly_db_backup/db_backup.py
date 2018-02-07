#!/usr/bin/env python3
# -*- coding: utf-8 -*-

"""
:file:     db_backup.py
:language: python3
:author:   Peter Bailie (Systems Programmer, Dept. of Computer Science, RPI)
:date:     May 4 2017

This script will take backup dumps of each individual Submitty course
database.  This should be set up by a sysadmin to be run on the Submitty
server as a cron job by root.  Recommend that this is run nightly.

WARNING: Backup data contains sensitive information protected by FERPA, and
as such should have very strict access permissions.

Change values under CONFIGURATION to match access properties of your
university's Submitty database and file system.  Change EXPIRATION to match
how many days of dumps to keep.  Older dumps are automatically deleted.
"""

import datetime
import os
import re
import subprocess
import sys

# CONFIGURATION ----------------------------------------------------------------
DB_HOST    = 'submitty.cs.myuniversity.edu'
DB_USER    = 'hsdbu'
DB_PASS    = 'DB.p4ssw0rd'  # CHANGE THIS!  DO NOT USE 'DB.p4ssw0rd'
DUMP_PATH  = '/var/local/submitty-dumps'
EXPIRATION = 7

# RECURSIVE FUNCTION -----------------------------------------------------------
def delete_obsolete_dumps(working_path, expiration_stamp):
	"""
	Recurse through folders/files and delete any obsolete dump file

	:param working_path:     File or path to recurse through
	:param expiration_stamp: Oldest date to keep a dump file.
	:type working_path:      string
	:type expiration_path:   string
	"""

	# Filter out '.', '..', and any "hidden" file/folder.
	# prepend full path to all directory list elements
	regex = re.compile('^(?!\.)')
	files_list = filter(regex.match, [working_path + '/{}'.format(x) for x in os.listdir(working_path)])
	re.purge()

	for file in files_list:
		if os.path.isdir(file):
			# If the file is a folder, recurse
			delete_obsolete_dumps(file, expiration_stamp)
		else:
			# If the file's date stamp is older than the EXPIRATION stamp, delete it.
			# File's date stamp was concat'ed into the full path at [-26:-20]
			if file[-26:-20] < expiration_stamp:
				os.remove(file)

# MAIN 
if __name__ == "__main__":

	# ROOT required
	if os.getuid() != 0:
		raise SystemExit('Root required. Please contact your sysadmin for assistance.')

	# DETERMINE CURRENT DATE
	# (today.year % 100) determines the two digit year.  e.g. '2017' -> '17'
	today       = datetime.date.today()
	year        = str(today.year % 100)
	today_stamp = '{:0>2}{:0>2}{:0>2}'.format(year, today.month, today.day)

	# DETERMINE CURRENT SEMESTER (based on current date)
	# if month <= 5: ... elif month >=8: ... else: ...
	semester = 's' + year if today.month <= 5 else ('f' + year if today.month >= 8 else 'm' + year)

	# GET FOLDER LIST (determines active courses)
	courses_dir = '/var/local/submitty/courses/' + semester

	# Force lowercase and filter out all entries starting with '.'
	regex = re.compile('^(?!\.)')
	folder_list = filter(regex.match, [x.lower() for x in os.listdir(courses_dir)])
	re.purge()

	# BUILD LISTS AND PATH
	db_list     = []
	dump_list   = []
	course_list = []

	for course in folder_list:

		# Build lists used as pg_dump arguments
		db_list.append('submitty_{}_{}'.format(semester, course))
		dump_list.append('{}_{}_{}.dbdump'.format(today_stamp, semester, course))
		course_list.append(course)

		# Make sure backup folder exists for each course
		dump_path = '{}/{}'.format(DUMP_PATH, course)
		try:
			os.mkdir(dump_path, 0o700)
			os.chown(dump_path, 0, 0)
		except OSError:
			if not os.path.isdir(dump_path):
				raise

	# DUMP
	for i in range(len(course_list)):
		# e.g. "pg_dump postgresql://user:password@host/dbname > /var/local/submitty-dump/course/dump_file.dbdump"
		process = 'pg_dump postgresql://{}:{}@{}/{} > {}/{}/{}'.format(DB_USER, DB_PASS, DB_HOST, db_list[i], DUMP_PATH, course_list[i], dump_list[i])
		return_code = subprocess.call(process, shell=True)

		# If pg_dump doesn't return 0, display pg_dump return code and related course being dumped.
		if return_code != 0:
			print ('{}: pg_dump exited with error {}.'.format(course_list[i], return_code), file=sys.stderr)

	# DETERMINE EXPIRATION DATE (to delete obsolete dump files)
	# (do this BEFORE recursion so it is not calculated recursively n times)
	expiration       = datetime.date.fromordinal(today.toordinal() - EXPIRATION)
	expiration_stamp = '{:0>2}{:0>2}{:0>2}'.format(expiration.year % 100, expiration.month, expiration.day)

	# RECURSIVELY CULL OBSOLETE DUMPS
	delete_obsolete_dumps(DUMP_PATH, expiration_stamp)
