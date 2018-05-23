"""
Writes out submission datetime details (when it was submitted, how long it was in grading
process, etc) to a history.json file which is a list of all grading attempts for a
particular submission (including initial grading of it and all regrades).
"""

import json
import os
import collections


def just_write_grade_history(json_file,assignment_deadline,submission_time,
                             seconds_late,queue_time,batch_regrade,grading_began,
                             wait_time,grading_finished,grade_time,autograde_total):

    #####################################
    # LOAD THE PREVIOUS HISTORY
    if os.path.isfile(json_file):
        with open(json_file, 'r') as infile:
            obj = json.load(infile, object_pairs_hook=collections.OrderedDict)
    else:
        obj = []

    #####################################
    # CREATE THE NEWEST INFO BLOB
    blob = collections.OrderedDict()
    blob["assignment_deadline"] = assignment_deadline
    blob["submission_time"] = submission_time
    seconds_late = seconds_late
    if seconds_late > 0:
        minutes_late = int((seconds_late+60-1) / 60)
        hours_late = int((seconds_late+60*60-1) / (60*60))
        days_late = int((seconds_late+60*60*24-1) / (60*60*24))
        blob["days_late_before_extensions"] = days_late
    blob["queue_time"] = queue_time
    blob["batch_regrade"] = True if batch_regrade == "BATCH" else False
    blob["grading_began"] = grading_began
    blob["wait_time"] = wait_time
    blob["grading_finished"] = grading_finished
    blob["grade_time"] = grade_time
    blob["autograde_result"] = autograde_total
    autograde_array = str.split(autograde_total)
    if len(autograde_array) > 0 and autograde_array[0] == "Automatic":
        blob["autograde_total"] = int(autograde_array[3])
        if len(autograde_array) == 6:
            blob["autograde_max_possible"] = int(autograde_array[5])


    #####################################
    #  ADD IT TO THE HISTORY
    obj.append(blob)
    with open(json_file, 'w') as outfile:
        json.dump(obj, outfile, indent=4, separators=(',', ': '))
