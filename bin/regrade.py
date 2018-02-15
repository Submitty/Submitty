#!/usr/bin/env python3

"""
Expected directory structure:
<BASE_PATH>/courses/<SEMESTER>/<COURSES>/submissions/<HW>/<WHO>/<VERSION#>

This script will find all submissions that match the provided
pattern and add them to the grading queue.

USAGE:
    regrade.py  <one or more (absolute or relative) PATTERN PATH>
    regrade.py  <one or more (absolute or relative) PATTERN PATH>  --interactive
"""

import argparse
import json
import os
from submitty_utils import glob

SUBMITTY_DATA_DIR = "__INSTALL__FILLIN__SUBMITTY_DATA_DIR__"


def arg_parse():
    parser = argparse.ArgumentParser(description="Re-adds any submission folders found in the given path and adds"
                                                 "them to a queue (default batch) for regrading")
    parser.add_argument("path", nargs=argparse.REMAINDER, metavar="PATH", help="Path (absolute or relative) to submissions to regrade")
    parser.add_argument("--interactive", dest="interactive", action='store_const', const=True, default=False,
                        help="What queue (INTERACTIVE or BATCH) to use for the regrading. Default "
                        "is batch.")

    parser.add_argument("--replay", dest="times", nargs=2, type=str, 
                        help="Specify start time for replay?  example format: '2018-02-14 00:13:17.000 -0500'")
    
    parser.add_argument("--no_input", dest="no_input", action='store_const', const=True, default=False,
                        help="Do not wait for confirmation input, even if many things are being added to the queue.")
    return parser.parse_args()




def replay(starttime,endtime):

    print ("start: ",starttime.year," ", starttime.month," ",starttime.day,"FOO")
    print ("end: ",endtime.year," ", endtime.month," ",endtime.day,"FOO")
    
    if not (starttime.year == endtime.year and
            starttime.month == endtime.month and
            starttime.day == endtime.day):
        print ("ERROR!  invalid replay range ",starttime,"->",endtime, " (must be same day)")
        exit()
            
    if not ((starttime.hour == endtime.hour and
             starttime.minute < endtime.minute) or
            (starttime.hour < endtime.hour)):
        print ("ERROR!  invalid replay range ",starttime,"->",endtime, " (invalid times)")
        exit()


    #file = "/var/local/submitty/logs/autograding_logs/"+year+"0"+month+
    file = '/var/local/submitty/logs/autograding/{:d}{:02d}{:02d}.txt'.format(starttime.year,starttime.month,starttime.day)
    print ("FILE",file)

    with open(file,'r') as lines:
        for line in lines:
            #print ("LINE",line)
            things = line.split('|')
            my_time = dateutils.read_submitty_date(things[0])
            if (my_time.hour < starttime.hour or
                my_time.hour > endtime.hour or
                (my_time.hour == starttime.hour and my_time.minute < starttime.minute) or
                (my_time.hour == endtime.hour and my_time.minute > endtime.minute)
                ):
                continue
            if (things[2] == "BATCH"):
                continue
            my_job = things[4].strip()
            if my_job == "":
                continue

            what = my_job.split('/')
            if not what[1]=="sample":
                continue
            
            print("USE '"+my_job+"'",line)

            item = {"semester": what[0], "course": what[1], "gradeable": what[3],
                    "user": what[4], "team": "", "who": what[4], "is_team": False, "version": what[5]}
            
            file_name = "__".join([item['semester'], item['course'], item['gradeable'], item['who'], item['version']])
            file_name = os.path.join(SUBMITTY_DATA_DIR, "to_be_graded_interactive", file_name)
            with open(file_name, "w") as open_file:
                json.dump(item, open_file)
                os.system("chmod o+rw {}".format(file_name))
                
    
            

def main():
    args = arg_parse()
    data_dir = os.path.join(SUBMITTY_DATA_DIR, "courses")
    data_dirs = data_dir.split(os.sep)
    grade_queue = []

    if not args.times is None:
        print ("do that ", args.times[0])
        print ("do that ", args.times[1])

        starttime = dateutils.read_submitty_date(args.times[0])
        endtime = dateutils.read_submitty_date(args.times[1])

        replay(starttime,endtime)
        exit()

    print ("PATH",args.path)
        
    if len(args.path) == 0:
        print ("ERROR! Must specify at least one path")
        exit()


    print ("PATH ", args.path)
    
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

        print ("TEST", dirs, "T", dirs[0:len(data_dirs)], " THING ", data_dirs)
        
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
        pattern = os.path.join(data_dir,pattern_semester,pattern_course,"submissions",pattern_gradeable,pattern_who,pattern_version)
        print("pattern: ",pattern)

        # Find all matching submissions
        for d in glob.glob(pattern):
            if os.path.isdir(d):
                print("match: ",d)
                my_dirs = d.split(os.sep)
                if len(my_dirs) != len(data_dirs)+6:
                    raise SystemExit("ERROR: directory length not as expected")
                my_semester=my_dirs[len(data_dirs)]
                my_course=my_dirs[len(data_dirs)+1]
                my_gradeable=my_dirs[len(data_dirs)+3]
                my_who=my_dirs[len(data_dirs)+4]
                my_version=my_dirs[len(data_dirs)+5]
                my_path=os.path.join(data_dir,my_semester,my_course,"submissions",my_gradeable,my_who,my_version)
                if my_path != d:
                    raise SystemExit("ERROR: path reconstruction failed")
                # add them to the queue

                if '_' not in my_who:
                    my_user = my_who
                    my_team = ""
                    my_is_team = False
                else:
                    my_user = ""
                    my_team = my_who
                    my_is_team = True

                grade_queue.append({"semester": my_semester, "course": my_course, "gradeable": my_gradeable,
                                    "user": my_user, "team": my_team, "who": my_who, "is_team": my_is_team, "version": my_version})

    # Check before adding a very large number of systems to the queue
    if len(grade_queue) > 50 and not args.no_input:
        inp = input("Found {:d} matching submissions. Add to queue? [y/n]".format(len(grade_queue)))
        if inp.lower() not in ["yes", "y"]:
            raise SystemExit("Aborting...")

    which_queue="batch"
    if args.interactive:
        which_queue="interactive"

    for item in grade_queue:
        file_name = "__".join([item['semester'], item['course'], item['gradeable'], item['who'], item['version']])
        file_name = os.path.join(SUBMITTY_DATA_DIR, "to_be_graded_"+which_queue, file_name)
        with open(file_name, "w") as open_file:
            json.dump(item, open_file)
        os.system("chmod o+rw {}".format(file_name))

    print("Added {:d} to the {} queue for regrading.".format(len(grade_queue), which_queue.upper()))


if __name__ == "__main__":
    main()
