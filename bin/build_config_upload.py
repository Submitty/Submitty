#!/usr/bin/env python
#
# This script is run by a cron job as the hwcron user
#
# Regularly checks a queue to rebuild assignment configurations for recently modified gradeables.
#

import os
import pwd
import time
import subprocess
import glob
import json


# ------------------------------------------------------------------------
# this script is intended to be run only from the cron job of user hwcron
username = pwd.getpwuid( os.getuid() ).pw_name
if (username != "hwcron") :
	print "ERROR!  This script must be run by hwcron"
	exit()


# ------------------------------------------------------------------------
def buildOne(data) :
	semester = data["semester"]
	course = data["course"]
	gradeable = data["gradeable"]

	build_script = "/var/local/submitty/courses/" + semester + "/" + course + "/BUILD_" + course + ".sh"
	build_output = "/var/local/submitty/courses/" + semester + "/" + course + "/build_script_output.txt"

	f = open(build_output,'w')
	subprocess.call([ build_script, gradeable ],stdout=f,stderr=f)


# ------------------------------------------------------------------------
def buildAll() :
    for filename in glob.glob("/var/local/submitty/to_be_built/*.json") :
        with open(filename) as data_file:
			print ("going to process: " + filename)
			data = json.load(data_file)
			# after loading the contents of the file, remove it first
			os.remove(filename)
			# then build it, because build is slow (and we might have a race condition)
			buildOne(data)
			print ("finished with " + filename)


# ------------------------------------------------------------------------
# MAIN LOOP

# this script should only run for 5 minutes, then another process running
# this script will take over
start = time.time()
count=0
while True:
    count+=1
    now = time.time()
    formattedtime = time.strftime('%X %x %Z')
    print "%s build_config_upload.py loop %d" % ( formattedtime, count )

    buildAll()

    # stop if its been more than 5 minutes
    if (now-start) >= 5 * 60:
        print "exiting for time"
        exit()

    # sleep for 5 seconds
    time.sleep (5)

# ------------------------------------------------------------------------
