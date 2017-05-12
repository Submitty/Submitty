#!/usr/bin/env python

import os
import json
import sys

############################
# COMMAND LINE ARGUMENTS
if (len(sys.argv) != 3):
    print ("\nERROR! Must provide command line argument\n  " 
           + sys.argv[0] + 
           " <gradeable submission directory> <remote id file>\n")
    exit()

indir = sys.argv[1]
outfile = sys.argv[2]


############################
# OPEN THE OUTPUT FILE
with open(outfile,'w') as remote_ids:

    # LOOP OVER ALL OF THE USERS
    for username in os.listdir(indir):
        userdir= indir+ '/' + username
        uas=userdir + '/user_assignment_settings.json'
        with open(uas) as json_data:
            d = json.load(json_data)
            active = d['active_version']
            
            # SKIP CANCELLED SUBMISSION
            if (active < 1) :
                continue
                
            # GRAB THE ICLICKER FROM THE SUBMISSION
            clickerfile=userdir+'/'+str(active)+'/textbox_0.txt'
            with open(clickerfile) as f:
                iclicker=f.read()
                
                # WRITE TO EXPECTED FORMAT (matches iclicker.com format)
                remote_ids.write('#{0},"{1}"\n'.format(iclicker,username))
