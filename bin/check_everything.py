#!/usr/bin/env python3

"""
This script verifies that the Submitty installation and course data
directories are configured and permissioned correctly.

This script is a work-in-progress.  Additional checks will be added or
revised as needed.
"""

import os
import pwd
import stat
import grp
import sys


# Will be filled in by INSTALL_SUBMITTY.sh
SUBMITTY_INSTALL_DIR="__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__"
SUBMITTY_DATA_DIR="__INSTALL__FILLIN__SUBMITTY_DATA_DIR__"

HWPHP_USER="__INSTALL__FILLIN__HWPHP_USER__"
HWCRON_USER="__INSTALL__FILLIN__HWCRON_USER__"

COURSE_BUILDERS_GROUP="__INSTALL__FILLIN__COURSE_BUILDERS_GROUP__"

###########################################################################
def CheckItemBits (my_path, is_dir, my_owner, my_group, my_bits):
    if not os.path.exists(my_path):
        print("ERROR! "+my_path+" does not exist\n", file=sys.stderr)
        return False
    ret_val = True
    try:
        pwd.getpwnam(my_owner)
    except KeyError:
        print("ERROR! user "+my_owner+" does not exist\n", file=sys.stderr)
        ret_val = False
    try:
        grp.getgrnam(my_group)
    except KeyError:
        print("ERROR! group "+my_group+" does not exist\n", file=sys.stderr)
        ret_val = False
    if is_dir and not os.path.isdir(my_path):
        print("ERROR! "+my_path+" should be a directory!\n", file=sys.stderr)
        ret_val = False
    elif not is_dir and os.path.isdir(my_path):
        print("ERROR! "+my_path+" should not be a directory!\n", file=sys.stderr)
        ret_val = False
    if pwd.getpwuid(os.stat(my_path).st_uid).pw_name != my_owner:
        print("ERROR! "+my_path+" should be owned by "+my_owner+"\n", file=sys.stderr)
        ret_val = False
    if grp.getgrgid(os.stat(my_path).st_gid).gr_name != my_group:
        print("ERROR! "+my_path+" should be group "+my_group+"\n", file=sys.stderr)
        ret_val = False
    bits = os.stat(my_path)[stat.ST_MODE]
    bits &= 0o777
    if bits != my_bits:
        print("ERROR! "+my_path+" permission is "+oct(bits)+" should be "+oct(my_bits)+"\n", file=sys.stderr)
        ret_val = False
    return ret_val


###########################################################################
def CheckCourseInstructorAndGroup(my_instructor, my_group):
    ret_val = True

    cb_g = grp.getgrnam(COURSE_BUILDERS_GROUP)
    try:
        c_g = grp.getgrnam(my_group)
        try:
            pwd.getpwnam(my_instructor)
            if not my_instructor in c_g.gr_mem:
                print("ERROR! "+my_instructor+" should be group "+my_group+"\n", file=sys.stderr)
                ret_val = False
            if not my_instructor in cb_g.gr_mem:
                print("ERROR! "+my_instructor+" should be group "+COURSE_BUILDERS_GROUP+"\n", file=sys.stderr)
                ret_val = False
        except KeyError:
            print("ERROR! user "+my_instructor+" does not exist\n", file=sys.stderr)
            ret_val = False
        if not HWPHP_USER in c_g.gr_mem:
            print("ERROR! hwphp should be group "+my_group+"\n", file=sys.stderr)
            ret_val = False
        if not HWCRON_USER in c_g.gr_mem:
            print("ERROR! hwcron should be group "+my_group+"\n", file=sys.stderr)
            ret_val = False
    except KeyError:
        print("ERROR! group "+my_group+" does not exist\n", file=sys.stderr)
        ret_val = False
    return ret_val


###########################################################################


global_success = True


# CHECK THE INSTALLATION DIRECTORY
global_success &= CheckItemBits(SUBMITTY_INSTALL_DIR,True,"root","course_builders",0o751)
global_success &= CheckItemBits(SUBMITTY_INSTALL_DIR+"/bin/untrusted_execute",False,"root","hwcron",0o550)


# CHECK THE DATA DIRECTORY
global_success &= CheckItemBits(SUBMITTY_DATA_DIR,True,"root","course_builders",0o751)
global_success &= CheckItemBits(SUBMITTY_DATA_DIR+"/courses",True,"root","course_builders",0o751)


# CHECK EACH COURSE
for semester in os.listdir(SUBMITTY_DATA_DIR+"/courses"):
    semester_path=SUBMITTY_DATA_DIR+"/courses/"+semester
    if not os.path.isdir(semester_path):
        continue
    for course in os.listdir(semester_path):
        course_path=semester_path+"/"+course
        if not os.path.isdir(course_path):
            continue
        c_instructor=pwd.getpwuid(os.stat(course_path).st_uid).pw_name
        c_group=grp.getgrgid(os.stat(course_path).st_gid).gr_name
        global_success &= CheckCourseInstructorAndGroup(c_instructor,c_group)
        print ("check course=", course, " inst=", c_instructor, " group=",c_group)
        global_success &= CheckItemBits(course_path,True,c_instructor,c_group,0o770)

        global_success &= CheckItemBits(course_path+"/build",True,c_instructor,c_group,0o770)
        global_success &= CheckItemBits(course_path+"/ASSIGNMENTS.txt",False,c_instructor,c_group,0o660)

        global_success &= CheckItemBits(course_path+"/config",True,c_instructor,c_group,0o770)
        global_success &= CheckItemBits(course_path+"/config/config.ini",False,"hwphp",c_group,0o660)
        global_success &= CheckItemBits(course_path+"/config/build",True,c_instructor,c_group,0o770)
        global_success &= CheckItemBits(course_path+"/config/complete_config",True,c_instructor,c_group,0o770)
        global_success &= CheckItemBits(course_path+"/config/form",True,c_instructor,c_group,0o770)

        global_success &= CheckItemBits(course_path+"/bin",True,c_instructor,c_group,0o770)
        global_success &= CheckItemBits(course_path+"/test_input",True,c_instructor,c_group,0o770)
        global_success &= CheckItemBits(course_path+"/test_output",True,c_instructor,c_group,0o770)
        global_success &= CheckItemBits(course_path+"/test_code",True,c_instructor,c_group,0o770)

        global_success &= CheckItemBits(course_path+"/submissions",True,"hwphp",c_group,0o750)
        global_success &= CheckItemBits(course_path+"/config_upload",True,"hwphp",c_group,0o750)
        global_success &= CheckItemBits(course_path+"/results",True,"hwcron",c_group,0o750)
        global_success &= CheckItemBits(course_path+"/checkout",True,"hwcron",c_group,0o750)

        global_success &= CheckItemBits(course_path+"/reports",True,c_instructor,c_group,0o770)
        global_success &= CheckItemBits(course_path+"/reports/summary_html",True,c_instructor,c_group,0o770)
        global_success &= CheckItemBits(course_path+"/reports/all_grades",True,"hwphp",c_group,0o770)


###########################################################################

if not global_success:
    raise SystemExit("One or more errors need to be addressed in the configuration and/or " +
                     "permissions of the Submitty installation or course data directories.")
else:
    print ("Submitty installation and course data directory permissions look good.")
