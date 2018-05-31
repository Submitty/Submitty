#!/usr/bin/env python3

import os
from os import path
import sys
import json
import paramiko
import subprocess
# from autograder import grade_items_logging

CONFIG_PATH = path.join(path.dirname(path.realpath(__file__)), '..', '..','config')
SUBMITTY_CONFIG_PATH = path.join(CONFIG_PATH, 'submitty.json')
AUTOGRADING_WORKERS_PATH = path.join(CONFIG_PATH, 'autograding_workers.json')
with open(os.path.join(CONFIG_PATH, 'submitty_users.json')) as open_file:
    OPEN_JSON = json.load(open_file)
HWCRON_UID = OPEN_JSON['hwcron_uid']

with open(os.path.join(SUBMITTY_CONFIG_PATH)) as open_file:
    SUBMITTY_CONFIG = json.load(open_file)
SUBMITTY_INSTALL_DIR = SUBMITTY_CONFIG['submitty_install_dir']

SYSTEMCTL_WRAPPER_SCRIPT = os.path.join(SUBMITTY_INSTALL_DIR, 'sbin', 'shipper_utils','systemctl_wrapper.py')


# ==================================================================================
# Tells a foreign autograding worker to reinstall.
def install_worker(user, host):
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
            print("ERROR: could not ssh to {0}@{1} due to following error: {2}".format(user, host,str(e)))
            return False
        try:
            command = "sudo {0}".format(os.path.join(SUBMITTY_INSTALL_DIR, ".setup", "INSTALL_SUBMITTY.sh"))
            (stdin, stdout, stderr) = ssh.exec_command(command)
            status = int(stdout.channel.recv_exit_status())
            if status == 0:
                success = True
            else:
                print("Failure, bad update on {0}@{1}".format(user, host))
                success = False
        except Exception as e:
            print("ERROR: could not update {0} due to error {1}: ".format(host, str(e)))
            success = False
        finally:
            ssh.close()
            return success

def run_systemctl_command(machine, command):
  command = [SYSTEMCTL_WRAPPER_SCRIPT, command, '--target', machine]
  process = subprocess.Popen(command)
  process.communicate()
  exit_code = process.wait()
  return exit_code

if __name__ == "__main__":

  # verify the hwcron user is running this script
  if not int(os.getuid()) == int(HWCRON_UID):
      raise SystemExit("ERROR: the grade_item.py script must be run by the hwcron user")

  with open(SUBMITTY_CONFIG_PATH) as infile:
      submitty_config = json.load(infile)

  with open(AUTOGRADING_WORKERS_PATH) as infile:
      autograding_workers = json.load(infile)

  submitty_repository = submitty_config['submitty_repository']

  for worker, stats in autograding_workers.items():
      user = stats['username']
      host = stats['address']

      if worker == 'primary' or host == 'localhost':
          continue

      exit_code = run_systemctl_command(worker, 'status')
      if exit_code == 1:
        print("ERROR: {0}'s worker daemon was active when before rsynching began. Attempting to turn off.".format(worker))
        exit_code = run_systemctl_command(worker, 'stop')
        if exit_code != 0:
          print("Could not turn off {0}'s daemon. Please allow rsynching to continue and then attempt another install.".format(worker))

      local_directory = submitty_repository
      remote_host = '{0}@{1}'.format(user, host)
      foreign_directory = submitty_repository


      # rsynch the file
      print("performing rsynch to {0}...".format(worker))
      command = "rsync -a --no-o --no-g --exclude=.git {0}/ {1}:{2}".format(local_directory, remote_host, foreign_directory)
      os.system(command)

      success = install_worker(user, host)
      if success == True:
        print("Installed Submitty on {0}".format(worker))
        print("Rebooting {0}...".format(worker))
        exit_code = run_systemctl_command(worker, 'start')
      else:
        print("Failed to update {0}. This likely indicates an error when installing submitty on the worker. Please attempt an install locally on the worker and inspect for errors.".format(worker))
        exit_code = run_systemctl_command(worker, 'stop')
      print()
