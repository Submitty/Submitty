#!/usr/bin/env python3

import os
import pwd
import stat
from os import stat
from pwd import getpwuid
from grp import getgrgid
from stat import *

SUBMITTY_INSTALL_DIR="__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__"
SUBMITTY_DATA_DIR="__INSTALL__FILLIN__SUBMITTY_DATA_DIR__"


print ("check_everything.py start")


###########################################################################
def helper (my_path, my_owner, my_group, my_bits) : 
    print ("check "+my_path)
    #if not os.path.isdir(my_path):
    #    raise SystemExit("ERROR!")
    if not os.path.exists(my_path):
        raise SystemExit("ERROR! "+my_path+" does not exist")
    if getpwuid(stat(my_path).st_uid).pw_name != my_owner:
        raise SystemExit("ERROR! "+my_path+" should be owned by "+my_owner)
    if getgrgid(stat(my_path).st_gid).gr_name != my_group:
        raise SystemExit("ERROR! "+my_path+" should be group "+my_group)
    bits = stat("/usr/local/submitty/bin/untrusted_execute")[ST_MODE]&0o777
    if bits != my_bits:
        raise SystemExit("ERROR! "+my_path+" permission is "+oct(bits)+" should be "+oct(my_bits))


helper(SUBMITTY_INSTALL_DIR,"root","course_builders",0o550)
helper(SUBMITTY_INSTALL_DIR+"/bin/untrusted_execute","root","hwcron",0o550)

helper(SUBMITTY_DATA_DIR,"root","course_builders",0o550)

helper(SUBMITTY_DATA_DIR+"/courses","root","course_builders",0o550)

for semester in os.listdir(SUBMITTY_DATA_DIR+"/courses"):
    if not os.path.isdir(SUBMITTY_DATA_DIR+"/courses/"+semester):
        continue
    for course in os.listdir(SUBMITTY_DATA_DIR+"/courses/"+semester):
        course_path=SUBMITTY_DATA_DIR+"/courses/"+semester+"/"+course
        if not os.path.isdir(course_path):
            continue
        c_instructor=getpwuid(stat(course_path).st_uid).pw_name
        c_group=getgrgid(stat(course_path).st_gid).gr_name 
        print ("a course ", course, " inst=", c_instructor, " gr=",c_group)


        helper(course_path,c_instructor,c_group,0o550)
        helper(course_path+"/submissions","hwphp",c_group,0o750)



print ("check_everything.py finish")

