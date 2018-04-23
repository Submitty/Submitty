#!/usr/bin/env python3
# -*- coding: utf-8 -*-

"""
:file:     db_backup.py
:language: python3
:author:   Peter Bailie (Systems Programmer, Dept. of Computer Science, RPI)
:date:     April 20 2018

This script will take backup dumps of each individual Submitty course
database.  This should be set up by a sysadmin to be run on the Submitty
server as a cron job by root.  Recommend that this is run nightly.

The semester code can be specified as a command line argument.  This allows
database dumps of previous semesters or of unique semester codes.  If no
command line arguments are given, the semester code will be determined by
the current month and year.  e.g. April 2018 would correspond to the
Spring 2018 semester code "s18".

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

# CONFIGURATION
DB_HOST    = 'submitty.cs.myuniversity.edu'
DB_USER    = 'hsdbu'
DB_PASS    = 'DB.p4ssw0rd'  # CHANGE THIS!  DO NOT USE 'DB.p4ssw0rd'
DUMP_PATH  = '/var/local/submitty-dumps'
EXPIRATION = 7

def delete_obsolete_dumps(working_path, expiration_stamp):
	"""
	Recurse through folders/files and delete any obsolete dump file

	:param working_path:     File or path to recurse through
	:param expiration_stamp: Oldest date to keep a dump file.
	:type working_path:      string
	:type expiration_path:   string
	"""

	# Filter out '.', '..', and any "hidden" files/directories.
	# Prepend full path to all directory list elements
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

def main():
	""" Main """

	# ROOT required
	if os.getuid() != 0:
		raise SystemExit('Root required. Please contact your sysadmin for assistance.')

	# DETERMINE CURRENT DATE
	# (today.year % 100) determines the two digit year.  e.g. '2017' -> '17'
	today       = datetime.date.today()
	year        = str(today.year % 100)
	today_stamp = '{:0>2}{:0>2}{:0>2}'.format(year, today.month, today.day)

	# DETERMINE SEMESTER CODE
	if len(sys.argv) > 1:
		# Semester code was dictated by command line argument
		semester = sys.argv[1]
	else:
		# Semester code is dictated by current month/year.
		# Jan - May = (s)pring, Jun - July = su(m)mer, Aug - Dec = (f)all
		# if month <= 5: ... elif month >=8: ... else: ...
		semester = 's' + year if today.month <= 5 else ('f' + year if today.month >= 8 else 'm' + year)

	# GET ACTIVE COURSES FROM 'MASTER' DB
	try:
		sql = "select course from courses where semester='{}'".format(semester)
		# psql postgresql://user:password@host/dbname?sslmode=prefer -c "COPY (SQL code) TO STDOUT"
		process = "psql postgresql://{}:{}@{}/submitty?sslmode=prefer -c \"COPY ({}) TO STDOUT\"".format(DB_USER, DB_PASS, DB_HOST, sql)
		course_list = list(subprocess.check_output(process, shell=True).decode('utf-8').split(os.linesep))[:-1]
	except subprocess.CalledProcessError:
		raise SystemExit("Communication error with Submitty 'master' DB")

	if len(course_list) < 1:
		print("No registered courses found for semester '{}'.".format(semester))
		sys.exit(0)

	# BUILD LISTS AND PATH
	db_list     = list()
	dump_list   = list()

	for course in course_list:

		# Build lists used as pg_dump arguments
		db_list.append('submitty_{}_{}'.format(semester, course))
		dump_list.append('{}_{}_{}.dbdump'.format(today_stamp, semester, course))

		# Make sure backup folder exists for each course
		dump_path = '{}/{}/{}/'.format(DUMP_PATH, semester, course)
		try:
			os.makedirs(dump_path, mode=0o700, exist_ok=True)
			os.chown(dump_path, uid=0, gid=0)
		except OSError as e:
			if not os.path.isdir(dump_path):
				raise SystemExit("Failed to prepare DB dump path '{}'{}OS error: '{}'".format(e.filename, os.linesep, e.strerror))

	# DUMP
	for i in range(len(course_list)):
		try:
			# pg_dump postgresql://user:password@host/dbname?sslmode=prefer > /var/local/submitty-dump/semester/course/dump_file.dbdump
			process = 'pg_dump postgresql://{}:{}@{}/{}?sslmode=prefer > {}/{}/{}/{}'.format(DB_USER, DB_PASS, DB_HOST, db_list[i], DUMP_PATH, semester, course_list[i], dump_list[i])
			return_code = subprocess.check_call(process, shell=True)
		except subprocess.CalledProcessError as e:
			print("Error while dumping {}".format(db_list[i]))
			print(e.output.decode('utf-8'))

	# DETERMINE EXPIRATION DATE (to delete obsolete dump files)
	# (do this BEFORE recursion so it is not calculated recursively n times)
	expiration       = datetime.date.fromordinal(today.toordinal() - EXPIRATION)
	expiration_stamp = '{:0>2}{:0>2}{:0>2}'.format(expiration.year % 100, expiration.month, expiration.day)

	# RECURSIVELY CULL OBSOLETE DUMPS
	delete_obsolete_dumps(DUMP_PATH, expiration_stamp)

if __name__ == "__main__":
	main()
