#!/usr/bin/env python3
"""

A utility to quickly reset all workers and shippers in a system.

This script must be run as root, and it restarts all workers and
shippers in the correct order.

"""

import json
import os
from os import path
from submitty_utils import ssh_proxy_jump
import subprocess
import sys
import time

SYSTEMCTL_WRAPPER_SCRIPT = path.join(path.dirname(path.realpath(__file__)),
                                     'shipper_utils', 'systemctl_wrapper.py')
WORKER_CONFIG = path.join(path.dirname(path.realpath(__file__)), "..",
                          "config", "autograding_workers.json")


def retrieve_log(worker: str) -> None:
    with open(WORKER_CONFIG, 'r') as worker_cfg:
        worker_cfg = json.load(worker_cfg)

    worker_url = worker_cfg[worker]["address"]
    worker_usr = worker_cfg[worker]["username"]

    try:
        (target_conn, interm_conn) =                                        \
            ssh_proxy_jump.ssh_connection_allowing_proxy_jump(worker_usr, worker_url)
    except Exception as e:
        print(f"Failed to connect to {worker} at {worker_usr}@{worker_url} due to {str(e)}")
        sys.exit(1)

    cmd = "tail -n 5 /var/local/submitty/logs/autograding/$(date +%Y%m%d).txt"

    try:
        (_stdin, stdout, _stderr) = target_conn.exec_command(cmd)
        print(f"=== Printing logs from {worker} ==========")
        print(stdout.read().decode('ascii'))
    except Exception as e:
        print(f"Failed to run {cmd} at {worker_usr}@{worker_url} ({worker}) due to {str(e)}")
    finally:
        target_conn.close()
        if interm_conn:
            interm_conn.close()


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

    print('Delaying {0} seconds to allow the system to stablize...'
          .format(delay_in_seconds))
    time.sleep(delay_in_seconds)

    print("Verifying all worker daemons...")
    cmd = "python3 {0} status --daemon worker --target perform_on_all_workers"
    cmd = cmd.format(SYSTEMCTL_WRAPPER_SCRIPT)

    result = subprocess.run(["su", '-', "submitty_daemon", "-c", cmd],
                            stdout=subprocess.PIPE, universal_newlines=True)
    print(result.stdout)
    if "is inactive" in result.stdout:
        err_workers = [worker.split(':')[0]
                       for worker in filter(lambda w: ':' in w, result.stdout.split('\n'))]

        print(f"Some workers failed to restart: {','.join(err_workers)}")

        for err_worker in err_workers:
            subprocess.run(["su", "submitty_daemon", "-c",
                           f"python3 -c 'import restart_shipper_and_all_workers as r;    \
                                         r.retrieve_log(\"{err_worker}\")'"],
                           cwd=path.dirname(path.realpath(__file__)),
                           stdout=sys.stdout, stderr=sys.stderr)

    print('Finished!')
