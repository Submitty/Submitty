#!/usr/bin/python

import json
import sys
import os


#####################################
# CHECK ARGUMENTS
if (len(sys.argv)) != 12 : 
    print ("ERROR!  WRONG NUMBER OF ARGS TO "+str(sys.argv[0]))
    sys.exit(1)

#####################################
# LOAD THE PREVIOUS HISTORY
json_file = str(sys.argv[1])
if os.path.isfile(json_file):
    with open (json_file,'r') as infile:
        obj = json.load(infile)
else :
    obj = []

#####################################
# CREATE THE NEWEST INFO BLOB
blob = {}
blob["assignment_deadline"] = str(sys.argv[2])
blob["submission_time"]     = str(sys.argv[3])
seconds_late                = int(sys.argv[4])
if (seconds_late > 0) :
    minutes_late = int( (seconds_late+60-1)       / 60         )
    hours_late   = int( (seconds_late+60*60-1)    / (60*60)    )
    days_late    = int( (seconds_late+60*60*24-1) / (60*60*24) )
    blob["days_late_(before_extensions)"] = days_late
blob["queue_time"]         = str(sys.argv[5])
if (str(sys.argv[6]) == "BATCH") :
    blob["batch_regrade"]  = True
blob["grading_began"]      = str(sys.argv[7])
blob["wait_time"]          = int(sys.argv[8])
blob["grading_finished"]   = str(sys.argv[9])
blob["grade_time"]         = int(sys.argv[10])
autograde_array = str.split(str(sys.argv[11]))
if (autograde_array[0] == "Automatic") :
    blob["autograde_total"]   = int(autograde_array[3])


#####################################
#  ADD IT TO THE HISTORY 
obj.append(blob);
with open (json_file,'w') as outfile:
    json.dump(obj,outfile,sort_keys=True,indent=4, separators=(',', ': '))
