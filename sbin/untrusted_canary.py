#!/usr/bin/env python3

"""
Utility that scans the process list and checks if there are any processes
currently running by an untrusted user that does not have an associated
grading process. This will print out all untrusted users that has this.

Killing the processes has to be done manually (this is only a canary)
and easiest way is:

su -c 'kill -TERM -1' <untrusted##>

Any user found will be printed on sys.stderr.
"""
import sys
import psutil


def main():
    """main function"""

    print("untrusted_canary.py start")
    undead_count = 0

    pid_list = psutil.pids()
    pid_list.reverse()

    untrusted_users = []
    commands = []

    for pid in pid_list:
        try:
            proc = psutil.Process(pid)
            if 'untrusted' in proc.username():
                print("untrusted process: ", pid, " ", proc.username())
                if proc.username() not in untrusted_users:
                    untrusted_users.append(proc.username())
            elif proc.username() == 'hwcron':
                if '/usr/local/submitty/sbin/submitty_autograding_shipper.sh' in proc.cmdline():
                    print("grade_students instance: ", proc.cmdline())
                    commands.append(proc.cmdline())
        except psutil.NoSuchProcess:
            pass

    for untrusted in untrusted_users:
        found = False
        for command in commands:
            if untrusted in command:
                found = True
                break

        if found is False:
            print('Undead processes belonging to {}.'.format(untrusted), file=sys.stderr)
            undead_count += 1

    if undead_count > 0:
        print("WARNING:  ", undead_count, " undead processes")

    print("untrusted_canary.y finish")


if __name__ == "__main__":
    main()
