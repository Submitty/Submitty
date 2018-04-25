#!/usr/bin/env python3
# -*- coding: utf-8 -*-

"""
:file:     db_backup.py
:language: python3
:author:   Peter Bailie (Systems Programmer, Dept. of Computer Science, RPI)
:date:     April 24 2018

This script will take backup dumps of each individual Submitty course
database.  This should be set up by a sysadmin to be run on the Submitty
server as a cron job by root.  Recommend that this is run nightly.

The semester code can be specified as a command line argument "-s".  This allows
database dumps of previous semesters or of unique semester codes.  If this
argument is ommitted, the semester code will be determined by the current month
and year.  e.g. April 2018 would correspond to the Spring 2018 semester code
"s18".

Dumpfile expiration can be specified as a command line argument "-e".  This
indicates the number of days of dumps to keep.  Older dumps will be purged.
Only old dumps of the semester being processed will be purged.  Argument value
must be an unsigned integer 0 - 999 or an error will be issued.  "No expiration"
(no files are purged regardless of age) is indicated by a value of 0, or when
this argument is ommitted.

WARNING: Backup data contains sensitive information protected by FERPA, and
as such should have very strict access permissions.

Change values under CONFIGURATION to match access properties of your
university's Submitty database and file system.
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

def delete_obsolete_dumps(working_path, expiration_stamp):
	"""
	Recurse through folders/files and delete any obsolete dump file

	:param working_path:     path to recurse through
	:param expiration_stamp: date to begin purging old dump files
	:type working_path:      string
	:type expiration_stamp:  string
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
			# File date was concat'ed into the file's name.  Use regex to isolate date from full path.
			# e.g. "/var/local/submitty-dumps/s18/cs1000/180424_s18_cs1000.dbdump"
			#      The date substring can be located with high confidence by looking for:
			#        - final token of the full path (the actual file name)
			#        - file name consists of three tokens delimited by '_' chars
			#		   - first token is exactly 6 digits, the date stamp.
			#          - second token is the semester code, at least one 'word' char
			#          - third token is the course code, at least one 'word' char
			#          - filename always ends in ".dbdump"
			#        - then take substring [0:6] to get "180424".
			match = re.search('(\d{6}_\w+_\w+\.dbdump)$', file)
			if match is not None:
				file_date_stamp = match.group(0)[0:6]
				if file_date_stamp <= expiration_stamp:
					os.remove(file)

def main():
	""" Main """

	# ROOT REQUIRED
	if os.getuid() != 0:
		raise SystemExit('Root required. Please contact your sysadmin for assistance.')

	# COMMAND LINE ARGUMENT DEFAULTS
	# Get current date -- needed throughout the script, but also to determine default semester code.
	# (today.year % 100) determines the two digit year.  e.g. '2017' -> '17'
	today       = datetime.date.today()
	year        = str(today.year % 100)
	today_stamp = '{:0>2}{:0>2}{:0>2}'.format(year, today.month, today.day)

	# Default semester code
	# Jan - May = (s)pring, Jun - July = su(m)mer, Aug - Dec = (f)all
	# if month <= 5: ... elif month >=8: ... else: ...
	semester = 's' + year if today.month <= 5 else ('f' + year if today.month >= 8 else 'm' + year)

	# default expiration is "no expiration" (value is 0) -- no files are purged.
	# values greater than 0 indicate how many days of dumps to keep.
	expiration = 0

	# READ COMMAND LINE ARGUMENTS
	# Overwrites default values when specified
	if "-s" in sys.argv:
		try:
			i = sys.argv.index("-s")
			semester = sys.argv[i+1]
		except IndexError:
			raise SystemExit("No value supplied for argument '-s'")

	if "-e" in sys.argv:
		i = sys.argv.index("-e")

		try:
			if len(sys.argv[1+1]) > 3 or not str.isdigit(sys.argv[i+1]):
				raise SystemExit("Expiration must be an integer 0 - 999")

			expiration = int(sys.argv[i+1])
		except IndexError:
			raise SystemExit("No value supplied for argument '-e'")

	# GET ACTIVE COURSES FROM 'MASTER' DB
	try:
		sql = "select course from courses where semester='{}'".format(semester)
		# psql postgresql://user:password@host/dbname?sslmode=prefer -c "COPY (SQL code) TO STDOUT"
		process = "psql postgresql://{}:{}@{}/submitty?sslmode=prefer -c \"COPY ({}) TO STDOUT\"".format(DB_USER, DB_PASS, DB_HOST, sql)
		result = list(subprocess.check_output(process, shell=True).decode('utf-8').split(os.linesep))[:-1]
	except subprocess.CalledProcessError:
		raise SystemExit("Communication error with Submitty 'master' DB")

	if len(result) < 1:
		raise SystemExit("No registered courses found for semester '{}'.".format(semester))

	# BUILD LIST OF DBs TO BACKUP
	# Initial entry is the submitty 'master' database
	# All other entries are submitty course databases
	course_list = ['submitty'] + result

	# MAKE/VERIFY BACKUP FOLDERS FOR EACH DB
	for course in course_list:
		dump_path = '{}/{}/{}/'.format(DUMP_PATH, semester, course)
		try:
			os.makedirs(dump_path, mode=0o700, exist_ok=True)
			os.chown(dump_path, uid=0, gid=0)
		except OSError as e:
			if not os.path.isdir(dump_path):
				raise SystemExit("Failed to prepare DB dump path '{}'{}OS error: '{}'".format(e.filename, os.linesep, e.strerror))

	# BUILD DB LISTS
	# Initial entry is the submitty 'master' database
	# All other entries are submitty course databases
	db_list   = ['submitty']
	dump_list = ['{}_{}_submitty.dbdump'.format(today_stamp, semester)]

	for course in course_list[1:]:
		db_list.append('submitty_{}_{}'.format(semester, course))
		dump_list.append('{}_{}_{}.dbdump'.format(today_stamp, semester, course))

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
	if expiration > 0:
		expiration_date  = datetime.date.fromordinal(today.toordinal() - expiration)
		expiration_stamp = '{:0>2}{:0>2}{:0>2}'.format(expiration_date.year % 100, expiration_date.month, expiration_date.day)
		working_path = "{}/{}".format(DUMP_PATH, semester)

		# RECURSIVELY CULL OBSOLETE DUMPS
		delete_obsolete_dumps(working_path, expiration_stamp)

if __name__ == "__main__":
	main()
