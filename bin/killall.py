#!/usr/bin/env python3

"""
Script that will kill all processes owned by the user who runs this script.
"""

import os
import pwd
import psutil

current_pid = os.getpid()
for proc in psutil.process_iter():
    try:
        pinfo = proc.as_dict(attrs=['name', 'pid', 'username'])
        if pinfo['pid'] != current_pid and pinfo['username'] == pwd.getpwuid(os.getuid())[0]:
            proc.kill()
    except psutil.NoSuchProcess:
        pass
