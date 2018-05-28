#!/usr/bin/env python3

import os
import sys
import json
from os import path
import subprocess
import paramiko
import tempfile
import socket
# from autograder import grade_items_logging

CONFIG_PATH = path.join(path.dirname(path.realpath(__file__)), '..', 'config')
SUBMITTY_CONFIG_PATH = path.join(CONFIG_PATH, 'submitty.json')
AUTOGRADING_WORKERS_PATH = path.join(CONFIG_PATH, 'autograding_workers.json')

# ==================================================================================
# Tells a foreign autograding worker to update its code (WIP) and reinstall.
def update_worker_code(name, autograding_worker_to_ship):
    try:
        user = autograding_worker_to_ship[name]['username']
        host = autograding_worker_to_ship[name]['address']
    except Exception as e:
        print("ERROR: autograding_workers.json entry for {0} is malformatted. {1}".format(e, name))
        grade_items_logging.log_message(JOB_ID, message="ERROR: autograding_workers.json entry for {0} is malformatted. {1}".format(e, name))
        return

    #if we are updating the current machine, we can just move the new json to the appropriate spot (no ssh needed)
    if host == "localhost":
        return True
    else:
        try:
            ssh = paramiko.SSHClient()
            ssh.get_host_keys()
            ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
            ssh.connect(hostname = host, username = user)
        except Exception as e:
            grade_items_logging.log_message(JOB_ID, message="ERROR: could not ssh to {0}@{1} due to following error: {2}".format(user, host,str(e)))
            print("ERROR: could not ssh to {0}@{1} due to following error: {2}".format(user, host,str(e)))
            return
        try:
            command = "sudo {0}".format(os.path.join(SUBMITTY_INSTALL_DIR, "sbin", "update_and_install_worker.py"))
            (stdin, stdout, stderr) = ssh.exec_command(command)
            status = int(stdout.channel.recv_exit_status())
            if status == 0:
                grade_items_logging.log_message(JOB_ID, message="Success! Good update!".format(name))
                print("Success! Good update!")
                success = True
            else:
                grade_items_logging.log_message(JOB_ID, message="Failure, bad update on {0} :( Status code {1}".format(name, status))
                print("Failure, bad update on {0} :( Status code {1}".format(name, status))
                success = False
        except Exception as e:
            grade_items_logging.log_message(JOB_ID, message="ERROR: could not update {0} due to error {1}: ".format(host, str(e)))
            print("ERROR: could not update {0} due to error {1}: ".format(host, str(e)))
            success = False
        finally:
            ssh.close()
            return success

if __name__ == "__main__":

  # verify the hwcron user is running this script
  if not int(os.getuid()) == int(HWCRON_UID):
      raise SystemExit("ERROR: the grade_item.py script must be run by the hwcron user")

  with open(SUBMITTY_CONFIG_PATH) as infile:
    submitty_config = json.load(infile)

  with open(AUTOGRADING_WORKERS_PATH) as infile:
    autograding_workers = json.load(infile)

  submitty_repository = submitty_config['submitty_repository']

  for worker, stats in workers.keys():
      user = stats['username']
      host = stats['address']

      local_directory = submitty_repository
      remote_host = '{0}@{1}'.format(user, host)
      foreign_directory = submitty_repository


      #rsynch the file
      command = "rsync -azP {0}/ {1}:{2}".format(local_directory, remote_host, foreign_directory)
      #rsynch the files across.
      print(command)
      # os.system(command)
      

