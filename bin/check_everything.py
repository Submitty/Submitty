#!/usr/bin/env python3

import os
import pwd
import stat
from os import stat
from pwd import getpwuid
from grp import getgrgid
from stat import *
from subprocess import call

SUBMITTY_INSTALL_DIR="__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__"
SUBMITTY_DATA_DIR="__INSTALL__FILLIN__SUBMITTY_DATA_DIR__"


print ("check_everything.py start")


###########################################################################
def helper (my_path, my_owner, my_group, my_bits) : 
    print ("check "+my_path)
    if not os.path.exists(my_path):
        raise SystemExit("ERROR! "+my_path+" does not exist")
    if getpwuid(stat(my_path).st_uid).pw_name != my_owner:
        raise SystemExit("ERROR! "+my_path+" should be owned by "+my_owner)
    if getgrgid(stat(my_path).st_gid).gr_name != my_group:
        raise SystemExit("ERROR! "+my_path+" should be group "+my_group)
    bits = stat(my_path)[ST_MODE]
    bits &= 0o777
    if bits != my_bits:
        raise SystemExit("ERROR! "+my_path+" permission is "+oct(bits)+" should be "+oct(my_bits))


###########################################################################


# CHECK THE INSTALLATION DIRECTORY
helper(SUBMITTY_INSTALL_DIR,"root","course_builders",0o751)
helper(SUBMITTY_INSTALL_DIR+"/bin/untrusted_execute","root","hwcron",0o550)


# CHECK THE DATA DIRECTORY
helper(SUBMITTY_DATA_DIR,"root","course_builders",0o751)
helper(SUBMITTY_DATA_DIR+"/courses","root","course_builders",0o751)


# CHECK EACH COURSE
for semester in os.listdir(SUBMITTY_DATA_DIR+"/courses"):
    semester_path=SUBMITTY_DATA_DIR+"/courses/"+semester
    if not os.path.isdir(semester_path):
        continue
    for course in os.listdir(semester_path):
        course_path=semester_path+"/"+course
        if not os.path.isdir(course_path):
            continue
        c_instructor=getpwuid(stat(course_path).st_uid).pw_name
        c_group=getgrgid(stat(course_path).st_gid).gr_name 
        print ("check course=", course, " inst=", c_instructor, " group=",c_group)
        helper(course_path,c_instructor,c_group,0o770)
        helper(course_path+"/ASSIGNMENTS.txt",c_instructor,c_group,0o660)
        helper(course_path+"/test_input",c_instructor,c_group,0o770)
        helper(course_path+"/test_output",c_instructor,c_group,0o770)
        helper(course_path+"/test_code",c_instructor,c_group,0o770)
        helper(course_path+"/submissions","hwphp",c_group,0o750)
        helper(course_path+"/results","hwcron",c_group,0o750)


###########################################################################

print ("check_everything.py finish")

