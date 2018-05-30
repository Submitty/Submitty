#!/usr/bin/env python3

import argparse
import os
from os import path
import sys
import json
import paramiko

CONFIG_PATH = path.join(path.dirname(path.realpath(__file__)), '..', '..', '..','config')
SUBMITTY_CONFIG_PATH = path.join(CONFIG_PATH, 'submitty.json')
AUTOGRADING_WORKERS_PATH = path.join(CONFIG_PATH, 'autograding_workers.json')
with open(os.path.join(CONFIG_PATH, 'submitty_users.json')) as open_file:
    OPEN_JSON = json.load(open_file)
HWCRON_UID = OPEN_JSON['hwcron_uid']

with open(os.path.join(SUBMITTY_CONFIG_PATH)) as open_file:
    SUBMITT_CONFIG = json.load(open_file)
INSTALL_DIR = SUBMITT_CONFIG['submitty_install_dir']

#EXIT CODES:
# 0 = After performing the specified action, the requested daemon is dead
# 1 = After performing the specified action, the requested daemon is alive
# 2 = failure in performing operation
# 3 = invalid mode, daemon, or machine_id 

# A simple method for printing the status code in english.
def print_status_message(status_code, mode, daemon, machine):
  prefix = '' if machine == 'local' else "{0}: ".format(machine)
  if status_code == 0:
    print("{0}{1} daemon is inactive".format(prefix, daemon))
  elif status_code == 1:
    print("{0}{1} daemon is active".format(prefix, daemon))
  elif status_code == 2:
    print("{0}Failure performing the {1} operation".format(prefix, mode))
  elif status_code == 3:
    print("{0}Recieved an argument error. This could be an issue with this script.".format(prefix))
  else:
    print("{0}Recieved unknown status code {1} when attempting to {2} the \
      {3} daemon".format(prefix, status_code, mode, daemon))

# A wrapper for perform_systemctl_command_on_worker that iterates over all workers.
def perform_systemctl_command_on_all_workers(daemon, mode):
  # Right now, this script returns the greatesr (worst) status it recieves from a worker.
  greatest_status = 0
  with open(AUTOGRADING_WORKERS_PATH, 'r') as infile:
    workers = json.load(infile)

  for machine_id in workers.keys():
    # Don't run on local machines
    if machine_id == 'primary' or workers[machine_id]['address'] == 'localhost':
      continue
    status = perform_systemctl_command_on_worker(daemon, mode, machine_id)
    print_status_message(status, mode, daemon, machine_id)
    greatest_status = max(greatest_status, status)
  return greatest_status

# This function performs a systemctl command (mode) on a given daemon on a given machine
def perform_systemctl_command_on_worker(daemon, mode, machine_id):
  with open(AUTOGRADING_WORKERS_PATH, 'r') as infile:
    workers = json.load(infile)

  if not machine_id in workers:
    print("There is no machine with the key {0}".format(machine_id))
    sys.exit(3)

  user = workers[machine_id]['username']
  host = workers[machine_id]['address']

  if host == 'localhost' or user == '':
    print("Please don't specify machine id if you wish to run locally.")
    sys.exit(3)

  script_directory = os.path.join(INSTALL_DIR, 'sbin', 'worker_shipper_utils', 'shipper_utils', 'daemon_utils.py')
  command = "sudo {0} {1} --daemon {2}".format(script_directory, mode, daemon)
  try:
      ssh = paramiko.SSHClient()
      ssh.get_host_keys()
      ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
      ssh.connect(hostname = host, username = user)
  except Exception as e:
      print("ERROR: could not ssh to {0}@{1} due to following error: {2}".format(user, host,str(e)))
      return 2
  try:
      (stdin, stdout, stderr) = ssh.exec_command(command)
      status = int(stdout.channel.recv_exit_status())
  except Exception as e:
      print("ERROR: Command did not properly execute: ".format(host, str(e)))
      status = 2
  finally:
      ssh.close()
      return status


if __name__ == "__main__":
  #parse arguments
  parser = argparse.ArgumentParser(description='A wrapper for the various systemctl functions. \
    This script must be run as the submitty supervisor.',
    epilog='ERROR CODES: 0 = After performing the specified action, the requested daemon is dead, 1 = After performing the \
    specified action, the requested daemon is alive, 2 = Failure performing operation, 3 = invalid mode, daemon, or machine_id')
  parser.add_argument("--daemon", metavar="DAEMON", type=str, help="Optional. Valid options are worker and shipper. \
    If unspecified, worker is assumed.")
  parser.add_argument("--machine_id", metavar="MACHINE", type=str, help="Optional. Give the id of a machine in the \
    autograding_workers json and this script will perform the desired function on that machine. If perform_on_all_workers is\
    specified, the command will be processed on all worker machines. If not specified, the status of this machine is checked.")
  parser.add_argument("mode", metavar="MODE", type=str, help="Valid modes are status, start, restart, and stop")

  args = parser.parse_args()

  daemon = 'worker'

  # daemon is an optional argument which must be either shipper or worker. If it is anything else, throw an error.
  if args.daemon != None:
    if args.daemon.lower() == 'shipper':
      daemon = 'shipper'
    elif args.daemon.lower() != 'worker':
      print("ERROR: Bad daemon. Expected worker or shipper")
      sys.exit(3)

  # determine whether we are working on a local or foreign machine
  local = False if (args.machine_id != None and args.machine_id.lower() != 'primary') else True
  mode = args.mode.lower()

  # make sure a valid command is being passed to the daemon.
  if not mode in ['status', 'start', 'restart', 'stop']:
    print("ERROR: Bad mode. Expected status, start, restart, or stop")
    sys.exit(3)

  command = 'sudo systemctl {0} submitty_autograding_{1}'.format(mode, daemon)
  status_command = 'sudo systemctl is-active submitty_autograding_{0}'.format(daemon)
  
  if local:
    # If running locally, verify we are running usin sudo.
    if not int(os.getuid()) == 0:
      print("ERROR: If running locally, this script must be run using sudo")
      sys.exit(2)
    #we always run the status command, regardless of what was passed in. If a command other than
    #  status was passed, run it first, then check status.
    if not mode == 'status':
      os.system(command)
    # the is-active command just returns either the word 'active' or 'inactive'
    text = os.popen(status_command).read().strip()
    status = 1 if (text == 'active') else 0
    # local is a keyword which causes print_status_message to print no machine prefix.
    print_status_message(status, mode, daemon, 'local')
  else:
    # if we are checking the status of a daeomon on another machine, make sure we are running
    #    as hwcron, who has the ssh keys.
    if not int(os.getuid()) == HWCRON_UID:
      print("ERROR: if running on another machine, this script must be run as hwcron")
      sys.exit(2)
    # perform_on_all_workers is a keyword that causes us to run the command on every worker machine.
    #   This is helpful for performing start all or stop all commands.
    if args.machine_id.lower() == "perform_on_all_workers":
      status = perform_systemctl_command_on_all_workers(daemon, mode)
    else:
      status = perform_systemctl_command_on_worker(daemon, mode, args.machine_id)
      print_status_message(status, mode, daemon, args.machine_id)
  sys.exit(status)


  
    