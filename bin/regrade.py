#!/usr/bin/env python3

"""
Expected directory structure:
<BASE_PATH>/courses/<SEMESTER>/<COURSES>/submissions/<HW>/<WHO>/<VERSION#>

This script will find all submissions that match the provided
pattern and add them to the grading queue.

USAGE:
    regrade.py  <one or more (absolute or relative) PATTERN PATH>
"""

import argparse
import json
import os
from pathlib import Path
import time
import datetime
import pause

from submitty_utils import dateutils

CONFIG_PATH = os.path.join(os.path.dirname(os.path.realpath(__file__)), '..', 'config')
with open(os.path.join(CONFIG_PATH, 'submitty.json')) as open_file:
    OPEN_JSON = json.load(open_file)
SUBMITTY_DATA_DIR = OPEN_JSON['submitty_data_dir']


def arg_parse():
    parser = argparse.ArgumentParser(description="Re-adds any submission folders found in the given path and adds"
                                                 "them to a queue (default batch) for regrading")
    parser.add_argument("path", nargs=argparse.REMAINDER, metavar="PATH",
                        help="Path (absolute or relative) to submissions to regrade")
    parser.add_argument("--replay", dest="times", nargs=2, type=str, 
                        help="Specify start time for replay?  example format: '2018-02-14 00:13:17.000 -0500'")
    parser.add_argument("--no_input", dest="no_input", action='store_const', const=True, default=False,
                        help="Do not wait for confirmation input, even if many things are being added to the queue.")
    parser.add_argument("--active_only", dest="active_only", action='store_const', const=True, default=False,
                        help="Only regrade versions that are currently tagged as the active version.")
    return parser.parse_args()


# check to see if the assignment version in this directory is the
# currently active version
def is_active_version(directory):
    my_dirs = directory.split(os.sep)
    this_version = my_dirs[-1]
    my_dirs.pop()
    f = os.path.join("/",*my_dirs,"user_assignment_settings.json")
    with open(f,'r') as settings_file:
        settings = json.load(settings_file)
        active_version = str(settings["active_version"])
    return this_version == active_version


# For the specified interval, walks over the log file and creates
# queue files for these submissions.
def replay(starttime,endtime):
    replay_starttime=datetime.datetime.now()
    print (replay_starttime,"replay start: ",starttime)

    # error checking
    if not (starttime.year == endtime.year and
            starttime.month == endtime.month and
            starttime.day == endtime.day):
        print ("ERROR!  invalid replay range ",starttime,"->",endtime, " (must be same day)")
        exit()
    if starttime >= endtime:
        print ("ERROR!  invalid replay range ",starttime,"->",endtime, " (invalid times)")
        exit()

    # file the correct file
    file = '/var/local/submitty/logs/autograding/{:d}{:02d}{:02d}.txt'.format(starttime.year,starttime.month,starttime.day)
    with open(file,'r') as lines:
        for line in lines:
            things = line.split('|')
            original_time = dateutils.read_submitty_date(things[0])
            # skip items outside of this time range
            if (original_time < starttime or
                original_time > endtime):
                continue
            # skip batch items
            if (things[2].strip() == "BATCH"):
                continue
            # only process the "wait" time (when we started grading the item)
            iswait=things[5].strip()[0:5]
            if (iswait != "wait:"):
                continue
            waittime=float(things[5].split()[1])
            # grab the job name
            my_job = things[4].strip()
            if my_job == "":
                continue
            what = my_job.split('/')
            # for now, only interested in Data Structures and Computer Science 1
            if not (what[1]=="csci1200" or what[1]=="csci1100"):
                continue
            # calculate when this job should be relaunched
            time_multipler=1.0
            pause_time=replay_starttime+(time_multiplier*(original_time-starttime))
            pause.until(pause_time)
            queue_time = dateutils.write_submitty_date()
            print(datetime.datetime.now(),"      REPLAY: ",original_time," ",my_job)
            # FIXME : This will need to be adjust for team assignments
            # and assignments with special required capabilities!
            item = {"term": what[0],
                    "course": what[1],
                    "gradeable": what[3],
                    "user": what[4],
                    "team": "",
                    "who": what[4],
                    "is_team": False,
                    "version": what[5],
                    "required_capabilities": "default",
                    "queue_time": queue_time,
                    "regrade": True,
                    "max_possible_grading_time" : -1 }
            file_name = "__".join([item['term'], item['course'], item['gradeable'], item['who'], item['version']])
            file_name = os.path.join(SUBMITTY_DATA_DIR, "to_be_graded_queue", file_name)
            with open(file_name, "w") as open_file:
                json.dump(item, open_file, sort_keys=True, indent=4)
                os.system("chmod o+rw {}".format(file_name))  
    print (datetime.datetime.now(),"replay end: ",endtime)


def main():
    args = arg_parse()
    data_dir = os.path.join(SUBMITTY_DATA_DIR, "courses")
    data_dirs = data_dir.split(os.sep)
    grade_queue = []
    if not args.times is None:
        starttime = dateutils.read_submitty_date(args.times[0])
        endtime = dateutils.read_submitty_date(args.times[1])
        replay(starttime,endtime)
        exit()
    if len(args.path) == 0:
        print ("ERROR! Must specify at least one path")
        exit()
    for input_path in args.path:
        print ('input path',input_path)
        # handle relative path
        if input_path == '.':
            input_path = os.getcwd()
        if input_path[0] != '/':
            input_path = os.getcwd() + '/' + input_path
        # remove trailing slash (if any)
        input_path = input_path.rstrip('/')
        # split the path into directories
        dirs = input_path.split(os.sep)

        # must be in the known submitty base data directory
        if dirs[0:len(data_dirs)] != data_dirs:
            print("ERROR: BAD REGRADE SUBMISSIONS PATH",input_path)
            raise SystemExit("You need to point to a directory within {}".format(data_dir))

        # Extract directories from provided pattern path (path may be incomplete)
        pattern_semester="*"
        if len(dirs) > len(data_dirs):
            pattern_semester=dirs[len(data_dirs)]
        pattern_course="*"
        if len(dirs) > len(data_dirs)+1:
            pattern_course=dirs[len(data_dirs)+1]
        if len(dirs) > len(data_dirs)+2:
            if (dirs[len(data_dirs)+2] != "submissions"):
                raise SystemExit("You must specify the submissions directory within the course")
        pattern_gradeable="*"
        if len(dirs) > len(data_dirs)+3:
            pattern_gradeable=dirs[len(data_dirs)+3]
        pattern_who="*"
        if len(dirs) > len(data_dirs)+4:
            pattern_who=dirs[len(data_dirs)+4]
        pattern_version="*"
        if len(dirs) > len(data_dirs)+5:
            pattern_version=dirs[len(data_dirs)+5]

        # full pattern may include wildcards!
        pattern = os.path.join(pattern_semester,pattern_course,"submissions",pattern_gradeable,pattern_who,pattern_version)

        print("pattern: ",pattern)

        # Find all matching submissions
        for d in Path(data_dir).glob(pattern):
            d = str(d)
            if os.path.isdir(d):
                my_dirs = d.split(os.sep)
                if len(my_dirs) != len(data_dirs)+6:
                    raise SystemExit("ERROR: directory length not as expected")
                # if requested, only regrade the currently active versions
                if args.active_only and not is_active_version(d):
                    continue
                print("match: ",d)
                my_semester=my_dirs[len(data_dirs)]
                my_course=my_dirs[len(data_dirs)+1]
                my_gradeable=my_dirs[len(data_dirs)+3]
                gradeable_config = os.path.join(data_dir,my_semester,my_course,"config/build/"+"build_"+my_gradeable+".json")
                with open(gradeable_config, 'r') as build_configuration:
                    datastore = json.load(build_configuration)
                    required_capabilities = datastore.get('required_capabilities', 'default')
                    max_grading_time = datastore.get('max_possible_grading_time', -1)

                #get the current time
                queue_time = dateutils.write_submitty_date()
                my_who=my_dirs[len(data_dirs)+4]
                my_version=my_dirs[len(data_dirs)+5]
                my_path=os.path.join(data_dir,my_semester,my_course,"submissions",my_gradeable,my_who,my_version)
                if my_path != d:
                    raise SystemExit("ERROR: path reconstruction failed")
                # add them to the queue

                # FIXME: This will be incorrect if the username includes an underscore
                if '_' not in my_who:
                    my_user = my_who
                    my_team = ""
                    my_is_team = False
                else:
                    my_user = ""
                    my_team = my_who
                    my_is_team = True

                # Note: If the initial checkout failed, or if
                # autograding failed to create a results subdirectory
                # or create a history file, regrading will also fail.
                history_file=os.path.join(data_dir, my_semester, my_course,
                                          "results", my_gradeable, my_who,
                                          my_version, "history.json")
                is_vcs_checkout = False
                if os.path.exists(history_file):
                    with open(history_file) as hf:
                        obj = json.load(hf)
                        if len(obj) > 0 and 'revision' in obj[0]:
                            is_vcs_checkout = True
                            revision = obj[0]['revision']

                obj = {"term": my_semester,
                       "course": my_course,
                       "gradeable": my_gradeable,
                       "user": my_user,
                       "team": my_team,
                       "who": my_who,
                       "is_team": my_is_team,
                       "version": my_version,
                       "vcs_checkout": is_vcs_checkout,
                       "required_capabilities" : required_capabilities,
                       "queue_time":queue_time,
                       "regrade":True,
                       "max_possible_grading_time" : max_grading_time}

                if is_vcs_checkout:
                    obj['revision'] = revision

                grade_queue.append(obj)

    # Check before adding a very large number of systems to the queue
    if len(grade_queue) > 50 and not args.no_input:
        inp = input("Found {:d} matching submissions. Add to queue? [y/n]".format(len(grade_queue)))
        if inp.lower() not in ["yes", "y"]:
            raise SystemExit("Aborting...")

    for item in grade_queue:
        file_name = "__".join([item['term'], item['course'], item['gradeable'], item['who'], item['version']])
        file_name = os.path.join(SUBMITTY_DATA_DIR, "to_be_graded_queue", file_name)
        with open(file_name, "w") as open_file:
            json.dump(item, open_file, sort_keys=True, indent=4)
        os.system("chmod o+rw {}".format(file_name))

    print("Added {:d} to the queue for regrading.".format(len(grade_queue)))


if __name__ == "__main__":
    main()
