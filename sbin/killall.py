#!/usr/bin/env python3

"""
Script that will kill all processes owned by the user who runs this script.
"""

import os
import pwd
import psutil
import sys


def main():
    current_pid = os.getpid()
    count = 0

    for proc in psutil.process_iter():
        try:
            pinfo = proc.as_dict(attrs=['name', 'pid', 'username'])
            if pinfo['username'] == pwd.getpwuid(os.getuid())[0]:
                print("a process to kill (except if its this script) proc={} count={}".format(proc, count))
                if pinfo['pid'] != current_pid:
                    count += 1
                    proc.kill()
        except psutil.NoSuchProcess:
            pass

    if count > 0:
        print("ERROR: killall.py had to kill ",count," process(es)")
        sys.exit(count)  # non-zero exit code means that many things had to be killed


if __name__ == '__main__':
    main()
