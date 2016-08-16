#!/usr/bin/python

import json
import sys
import os
import collections


# USAGE
# make_assignments_txt_file.py   <path to forms directory>   <path to ASSIGNMENTS.txt file>


#####################################
# CHECK ARGUMENTS
if (len(sys.argv)) != 3 :
    print ("ERROR!  WRONG NUMBER OF ARGUMENTS!");
    sys.exit(1)


#####################################
# OPEN ALL FILES IN THE FORMS DIRECTORIES
with open (sys.argv[2],'w') as outfile:
    for filename in os.listdir(sys.argv[1]):
        json_filename = os.path.join (sys.argv[1],filename)
        if os.path.isfile(json_filename) :
            with open (json_filename,'r') as infile:
                obj = json.load(infile)
        else: 
            sys.exit(1)

        # ONLY ELECTRONIC GRADEABLES HAVE A CONFIG PATH
        if ("config_path" in obj) :
            id = obj["gradeable_id"]
            config_path = obj["config_path"]
            dirs = sys.argv[1].split("/")
            semester=dirs[len(dirs)-4]
            course=dirs[len(dirs)-3]
            outfile.write("build_homework  "+config_path+"  "+semester+"  "+course+"  "+id+"\n")
