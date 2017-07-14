#!/usr/bin/env python3

"""
Writes out submission datetime details (when it was submitted, how long it was in grading
process, etc) to a history.json file which is a list of all grading attempts for a
particular submission (including initial grading of it and all regrades).

Details of using the script can be gotten through ./write_grade_history.py --help
"""

import argparse
import json
import os
import collections


parser = argparse.ArgumentParser(description="Writes out grading details to the "
                                             "history.json file which is ordered by when "
                                             "the grade was done (with newest grading being last "
                                             "in the list)")
parser.add_argument("json_file", type=str, help="location of the history.json file")
parser.add_argument("assignment_deadline", type=str, help="deadline for the gradeable submission")
parser.add_argument("submission_time", type=str, help="time that submission happened by student")
parser.add_argument("seconds_late", type=int, help="how many seconds late was the submission? "
                                                   "This is difference between submission_time "
                                                   "and assignment_deadline (with minimum value "
                                                   "of 0")
parser.add_argument("queue_time", type=str, help="when did the file enter the queue")
parser.add_argument("batch_regrade", type=str, help="was this a regrade of homework or not")
parser.add_argument("grading_began", type=str, help="what time did the grading begin on submission")
parser.add_argument("wait_time", type=int, help="how long did submission wait before it started "
                                                "to be graded")
parser.add_argument("grading_finished", type=str, help="when did grading finish running for "
                                                       "submission")
parser.add_argument("grade_time", type=int, help="how long was spent between when grading started "
                                                 "and when it finished")
parser.add_argument("autograde_total", type=str, help="String that should be of the format "
                                                      "'Automatic grading total: # /  #')' which "
                                                      "we can split to get the first # as the "
                                                      "autograding total. If it's not of that "
                                                      "format, we don't write out an "
                                                      "autograde_total")
args = parser.parse_args()

#####################################
# LOAD THE PREVIOUS HISTORY
json_file = args.json_file
if os.path.isfile(json_file):
    with open(json_file, 'r') as infile:
        obj = json.load(infile, object_pairs_hook=collections.OrderedDict)
else:
    obj = []

#####################################
# CREATE THE NEWEST INFO BLOB
blob = collections.OrderedDict()
blob["assignment_deadline"] = args.assignment_deadline
blob["submission_time"] = args.submission_time
seconds_late = args.seconds_late
if seconds_late > 0:
    minutes_late = int((seconds_late+60-1) / 60)
    hours_late = int((seconds_late+60*60-1) / (60*60))
    days_late = int((seconds_late+60*60*24-1) / (60*60*24))
    blob["days_late_before_extensions"] = days_late
blob["queue_time"] = args.queue_time
blob["batch_regrade"] = True if args.batch_regrade == "BATCH" else False
blob["grading_began"] = args.grading_began
blob["wait_time"] = args.wait_time
blob["grading_finished"] = args.grading_finished
blob["grade_time"] = args.grade_time
blob["autograde_result"] = args.autograde_total
autograde_array = str.split(args.autograde_total)
if len(autograde_array) > 0 and autograde_array[0] == "Automatic":
    blob["autograde_total"] = int(autograde_array[3])
    if len(autograde_array) == 6:
        blob["autograde_max_possible"] = int(autograde_array[5])


#####################################
#  ADD IT TO THE HISTORY 
obj.append(blob)
with open(json_file, 'w') as outfile:
    json.dump(obj, outfile, indent=4, separators=(',', ': '))
