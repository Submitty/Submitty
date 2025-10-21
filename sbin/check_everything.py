#!/usr/bin/env python3

"""
This script verifies that the Submitty installation and course data
directories are configured and permissioned correctly.

This script is a work-in-progress.  Additional checks will be added or
revised as needed.
"""

import json
import os
import pwd
import stat
import grp
import sys

CONFIG_PATH = os.path.join(os.path.dirname(os.path.realpath(__file__)), '..', 'config')

with open(os.path.join(CONFIG_PATH, 'submitty.json')) as open_file:
    JSON = json.load(open_file)
# Will be filled in by INSTALL_SUBMITTY.sh
SUBMITTY_INSTALL_DIR = JSON['submitty_install_dir']
SUBMITTY_DATA_DIR = JSON['submitty_data_dir']

with open(os.path.join(CONFIG_PATH, 'submitty_users.json')) as open_file:
    JSON = json.load(open_file)
PHP_USER = JSON['php_user']
DAEMON_USER = JSON['daemon_user']

COURSE_BUILDERS_GROUP = JSON['course_builders_group']


###########################################################################
def CheckItemBits (my_path, is_dir, my_owner, my_group, my_bits, must_exist=True):
    if not os.path.exists(my_path):
        if must_exist:
            print("ERROR! "+my_path+" does not exist\n", file=sys.stderr)
            return False
        else:
            return True
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
        if not PHP_USER in c_g.gr_mem:
            print("ERROR! "+PHP_USER+" should be group "+my_group+"\n", file=sys.stderr)
            ret_val = False
        if not DAEMON_USER in c_g.gr_mem:
            print("ERROR! "+DAEMON_USER+" should be group "+my_group+"\n", file=sys.stderr)
            ret_val = False
    except KeyError:
        print("ERROR! group "+my_group+" does not exist\n", file=sys.stderr)
        ret_val = False
    return ret_val


###########################################################################
def main():

    global_success = True


    # CHECK THE INSTALLATION DIRECTORY
    global_success &= CheckItemBits(SUBMITTY_INSTALL_DIR,True,"root","submitty_course_builders",0o751)
    global_success &= CheckItemBits(SUBMITTY_INSTALL_DIR+"/bin/untrusted_execute",False,"root",DAEMON_USER,0o550)


    # CHECK THE DATA DIRECTORY
    global_success &= CheckItemBits(SUBMITTY_DATA_DIR,True,"root","submitty_course_builders",0o751)
    global_success &= CheckItemBits(SUBMITTY_DATA_DIR+"/courses",True,"root","submitty_course_builders",0o751)


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
            global_success &= CheckItemBits(course_path+"/config/config.json",False,PHP_USER,c_group,0o660)
            global_success &= CheckItemBits(course_path+"/config/build",True,c_instructor,c_group,0o770)
            global_success &= CheckItemBits(course_path+"/config/complete_config",True,c_instructor,c_group,0o770,must_exist=False)
            global_success &= CheckItemBits(course_path+"/config/form",True,c_instructor,c_group,0o770)

            global_success &= CheckItemBits(course_path+"/bin",True,c_instructor,c_group,0o770)
            global_success &= CheckItemBits(course_path+"/provided_code",True,c_instructor,c_group,0o770)
            global_success &= CheckItemBits(course_path+"/instructor_solution",True,c_instructor,c_group,0o770)
            global_success &= CheckItemBits(course_path+"/instructor_solution_executable",True,c_instructor,c_group,0o770)
            global_success &= CheckItemBits(course_path+"/test_input",True,c_instructor,c_group,0o770)
            global_success &= CheckItemBits(course_path+"/test_output",True,c_instructor,c_group,0o770)
            global_success &= CheckItemBits(course_path+"/custom_validation_code",True,c_instructor,c_group,0o770)
            global_success &= CheckItemBits(course_path+"/generated_output",True,c_instructor,c_group,0o770)

            global_success &= CheckItemBits(course_path+"/submissions",True,PHP_USER,c_group,0o750)
            global_success &= CheckItemBits(course_path+"/config_upload",True,PHP_USER,c_group,0o750)
            global_success &= CheckItemBits(course_path+"/results",True,DAEMON_USER,c_group,0o750)
            global_success &= CheckItemBits(course_path+"/checkout",True,DAEMON_USER,c_group,0o750)

            global_success &= CheckItemBits(course_path+"/reports",True,c_instructor,c_group,0o770)
            global_success &= CheckItemBits(course_path+"/reports/summary_html",True,c_instructor,c_group,0o770)
            global_success &= CheckItemBits(course_path+"/reports/all_grades",True,PHP_USER,c_group,0o770)


    if not global_success:
        raise SystemExit("One or more errors need to be addressed in the configuration and/or " +
                         "permissions of the Submitty installation or course data directories.")
    else:
        print ("Submitty installation and course data directory permissions look good.")


###########################################################################
if __name__ == "__main__":
    main()
