#!/usr/bin/env python3
"""

A utility to quickly reset all workers and shippers in a system.

This script must be run as root, and it restarts all workers and
shippers in the correct order.

"""

import time
import subprocess
import os
from os import path
import sys

SYSTEMCTL_WRAPPER_SCRIPT = path.join(path.dirname(path.realpath(__file__)),
                                     'shipper_utils', 'systemctl_wrapper.py')

if __name__ == '__main__':

    if not int(os.getuid()) == 0:
        print("ERROR: If running locally, this script must be run using sudo")
        sys.exit(1)

    delay_in_seconds = 5
    print('Stopping the local shipper daemon...')
    subprocess.call(["python3", SYSTEMCTL_WRAPPER_SCRIPT, "stop",
                    "--daemon", "shipper", "--target", "primary"])

    print('Stopping all local worker daemon...')
    subprocess.call(["python3", SYSTEMCTL_WRAPPER_SCRIPT, "stop",
                    "--daemon", "worker", "--target", "primary"])

    print('Stopping all remote worker daemons...')
    cmd = 'python3 {0} stop --daemon worker --target perform_on_all_workers'
    cmd = cmd.format(SYSTEMCTL_WRAPPER_SCRIPT)

    subprocess.call(["su", "-", "submitty_daemon", "-c",  cmd])

    print('Delaying {0} seconds to allow the system to stabilize...'
          .format(delay_in_seconds))
    time.sleep(delay_in_seconds)

    print('Starting the local shipper daemon...')
    subprocess.call(["python3", SYSTEMCTL_WRAPPER_SCRIPT, "start", "--daemon",
                    "shipper", "--target", "primary"])

    print('Starting the local worker daemon...')
    subprocess.call(["python3", SYSTEMCTL_WRAPPER_SCRIPT, "start", "--daemon",
                    "worker", "--target", "primary"])

    print('Starting all worker daemons...')
    cmd = 'python3 {0} start --daemon worker --target perform_on_all_workers'
    cmd = cmd.format(SYSTEMCTL_WRAPPER_SCRIPT)

    subprocess.call(["su", "-", "submitty_daemon", "-c", cmd])

    print('Finished!')
