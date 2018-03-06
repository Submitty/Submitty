#!/usr/bin/env python3
"""
This script will anonymize a directory of autograding logs

usage example:
python3 /usr/local/submitty/GIT_CHECKOUT_Submitty/bin/anonymize_autograding_logs.py /var/local/submitty/logs/autograding/ ~/anon_logs/ something_random

The final argument is a random seed/offset string to prevent simple de-anonymization.

"""

import random
import sys
import os


# make a 6 lowercase letter 'hash' of the input (the course+semester+assignment+user+offset)
def random_string(seed):
    random.seed(seed)
    answer= ""
    for i in range(0,6):
        answer = answer+chr(random.randint(1,26)+96)
    return answer


# read the autograding log, swapping out the username with a hashed
# version.  the hash should be the same for that course & term, but
# should not match in other terms or courses.
def anon_log(in_filename,out_filename,offset):
    with open(in_filename,'r') as infile:
        with open (out_filename,'w') as outfile:
            for line in infile:
                line = line.strip()
                tokens = line.split('|')
                if len(tokens) == 6:
                    # pre f17
                    timestamp = tokens[0]
                    job_id = tokens[1]
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
                    job_id = tokens[1]
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

                hash = random_string(semester+course+user+offset)

                anon_which = semester+"/"+course+"/submissions/"+assignment+"/"+hash+"/"+version
                outfile.write('{0}|{1}|{2}|{3}| {4:76}|{5}|{6}\n'
                              .format(timestamp,job_id,batch,untrusted,anon_which,waitgrade,result))
                

def anon_dir(indir,outdir,offset):
    if indir==outdir:
        print("ERROR! directories cannot match!")
        exit();
    for file in os.listdir(indir):
        print("processing... "+file)
        anon_log(indir+"/"+file,outdir+"/"+file,offset)

        
if len(sys.argv) != 4:
    print ("ERROR! 3 arguments required: log_directory output_directory offset")
    exit();

anon_dir(sys.argv[1],sys.argv[2],sys.argv[3])
