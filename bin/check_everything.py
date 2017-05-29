#!/usr/bin/env python3

import os
import pwd
from os import stat
from pwd import getpwuid
from grp import getgrgid

SUBMITTY_INSTALL_DIR="__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__"
SUBMITTY_DATA_DIR="__INSTALL__FILLIN__SUBMITTY_DATA_DIR__"

print ("check_everything.py start")

if not os.path.isdir(SUBMITTY_INSTALL_DIR):
    raise SystemExit("ERROR!")
if getpwuid(stat(SUBMITTY_INSTALL_DIR).st_uid).pw_name != "root":
    raise SystemExit("ERROR! "+SUBMITTY_INSTALL_DIR+" should be owned by root")
if getgrgid(stat(SUBMITTY_INSTALL_DIR).st_gid).gr_name != "course_builders":
    raise SystemExit("ERROR! "+SUBMITTY_INSTALL_DIR+" should be group course_builders")

print ("check_everything.py finish")

