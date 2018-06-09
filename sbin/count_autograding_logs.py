#!/usr/bin/env python3
"""
This script will summarize count, wait, & grading times per hour

usage example:
python3 /usr/local/submitty/GIT_CHECKOUT/Submitty/sbin/count_autograding_logs.py /var/local/submitty/logs/autograding/
"""

import sys
import os
from submitty_utils import dateutils, glob

#import path


def my_stats(fname,hour,count,wait_count,total_wait,cs1_grade_count,cs1_total_grade,ds_grade_count,ds_total_grade):
    avg_wait=0
    if wait_count > 0:
        avg_wait = total_wait / wait_count
    cs1_avg_grade=0
    if cs1_grade_count > 0:
        cs1_avg_grade = cs1_total_grade / cs1_grade_count
    ds_avg_grade=0
    if ds_grade_count > 0:
        ds_avg_grade = ds_total_grade / ds_grade_count

    #if wait_count < 20:
    #    return
    #if avg_wait < 3:
    #    return

    # long grading time for ds
    #if wait_count < 200:
    #    return
    if ds_grade_count < 30:
        return
    if ds_avg_grade < 10:
        return

    if (#"201709" not in fname and
            "201710" not in fname
            #and
        #"201711" not in fname and
        #"201712" not in fname
    ):
        return

    other=count-wait_count
    if (other > 50):
        return
    
    print('{} | hour {:2d} | total {:4d} other {:4d} | '.format(fname,hour,count,other),
          'wait {:4d} {:6.2f} | '.format(wait_count,avg_wait),
          'cs1_grade {:4d} {:6.2f} |'.format(cs1_grade_count,cs1_avg_grade),
          'ds_grade {:4d} {:6.2f}'.format(ds_grade_count,ds_avg_grade))

    
# read the autograding log, swapping out the username with a hashed
# version.  the hash should be the same for that course & term, but
# should not match in other terms or courses.
def anon_log(in_filename,out_filename):
    count=0
    last_hour=0

    wait_count=0
    total_wait=0

    cs1_grade_count=0
    ds_grade_count=0
    cs1_total_grade=0
    ds_total_grade=0

    with open(in_filename,'r') as infile:
        with open (out_filename,'w') as outfile:
            for line in infile:
                line = line.strip()
                tokens = line.split('|')
                if len(tokens) == 6:
                    # pre f17
                    timestamp = tokens[0]
                    process = tokens[1]
                    batch = tokens[2]
                    untrusted = "           "
                    which = tokens[3].strip()
                    waitgrade = tokens[4]
                    result =tokens[5]

                    things=which.split('__')
                    if len(things) != 5:
                        # discard unparseable things (only errors)
                        continue
                    semester = things[0]
                    course = things[1]
                    assignment = things[2]
                    user = things[3]
                    version = things[4]
                    
                elif len(tokens) == 7:
                    # f17 or later
                    timestamp = tokens[0]
                    process = tokens[1]
                    batch = tokens[2]
                    untrusted = tokens[3]                
                    which=tokens[4].strip()
                    waitgrade =tokens[5]
                    result =tokens[6]
                    
                    things=which.split('/')
                    if len(things) != 6:
                        # discard unparseable things (only errors)
                        continue
                    semester = things[0]
                    course = things[1]
                    assignment = things[3]
                    user = things[4]
                    version = things[5]

                else:
                    # discard lines with bad format (usually errors)
                    continue

                if batch.strip()=="BATCH":
                    continue
                
                cs1=course=="csci1100"
                ds=course=="csci1200"
                cs1ords=cs1 or ds
                
                #print("which ",waitgrade)
                info=waitgrade.split()
                if len(info)==0:
                    continue
                val=float(info[1])
                if info[0]=="wait:":
                    count+=1
                
                if info[0]=="wait:" and cs1ords and val<600:
                    total_wait+=val
                    wait_count+=1
                if info[0]=="grade:" and cs1ords and val<600:
                    if cs1:
                        cs1_total_grade+=float(val)
                        cs1_grade_count+=1
                    if ds:
                        ds_total_grade+=float(val)
                        ds_grade_count+=1
                    
                when = dateutils.read_submitty_date(timestamp)
                if when.hour!=last_hour and (wait_count+cs1_grade_count+ds_grade_count>0):
                    my_stats(in_filename,last_hour,count,wait_count,total_wait,cs1_grade_count,cs1_total_grade,ds_grade_count,ds_total_grade)
                    last_hour=when.hour
                    wait_count=0
                    total_wait=0
                    cs1_grade_count=0
                    cs1_total_grade=0
                    ds_grade_count=0
                    ds_total_grade=0
                    count=0
                    
        if (wait_count+cs1_grade_count+ds_grade_count>0):
            my_stats(in_filename,last_hour,count,wait_count,total_wait,cs1_grade_count,cs1_total_grade,ds_grade_count,ds_total_grade)
                
def anon_dir(indir):
    for file in sorted(os.listdir(indir)):
        #print("processing... "+file)
        anon_log(os.path.join(indir,file),"/home/cutler/foo.txt")

        
if len(sys.argv) != 2:
    print ("ERROR! 1 arguments required: log_directory")
    exit();

anon_dir(sys.argv[1])
