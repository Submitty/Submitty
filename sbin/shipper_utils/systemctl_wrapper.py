#!/usr/bin/env python3

import argparse
import os
from os import path
import sys
import json
import paramiko
from submitty_utils import ssh_proxy_jump
import socket


CONFIG_PATH = path.join(path.dirname(path.realpath(__file__)), '..', '..','config')
SUBMITTY_CONFIG_PATH = path.join(CONFIG_PATH, 'submitty.json')
AUTOGRADING_WORKERS_PATH = path.join(CONFIG_PATH, 'autograding_workers.json')

with open(os.path.join(CONFIG_PATH, 'submitty_users.json')) as open_file:
    OPEN_JSON = json.load(open_file)
DAEMON_UID = OPEN_JSON['daemon_uid']
DAEMON_USER = OPEN_JSON['daemon_user']

with open(os.path.join(SUBMITTY_CONFIG_PATH)) as open_file:
    SUBMITT_CONFIG = json.load(open_file)
INSTALL_DIR = SUBMITT_CONFIG['submitty_install_dir']

if os.path.isfile(AUTOGRADING_WORKERS_PATH) :
  with open(AUTOGRADING_WORKERS_PATH, 'r') as open_file:
    WORKERS = json.load(open_file)
else:
  WORKERS = None

EXIT_CODES = {
  'inactive'  : 0,
  'active'    : 1,
  'failure'   : 2,
  'bad_arguments' : 3,
  'io_error': 4
}

# valid commands that can be passed to this script. If you add more, update
#    the verify function.
VALID_MODES = ['start', 'restart', 'stop', 'status']

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
    print("{0}Received an argument error. This could be an issue with this script.".format(prefix))
  elif status_code == 4:
    print("Connection to machine {0} timed out. Skipping this machine...".format(machine))
  else:
    print("{0}Received unknown status code {1} when attempting to {2} the \
      {3} daemon".format(prefix, status_code, mode, daemon))

# A wrapper for perform_systemctl_command_on_worker that iterates over all workers.
def perform_systemctl_command_on_all_workers(daemon, mode):
  # Right now, this script returns the greatest (worst) status it receives from a worker.
  greatest_status = 0

  for target in WORKERS.keys():
    # Don't run on local machines
    if target == 'primary' or WORKERS[target]['address'] == 'localhost':
        continue
    if WORKERS[target]['enabled'] == False:
        print (f"skip {target}")
        continue
    print (f"perform {daemon} {mode} on worker machine {target}")
    status = perform_systemctl_command_on_worker(daemon, mode, target)
    print_status_message(status, mode, daemon, target)
    if status == 4:
      continue
    verify_systemctl_status_code(status, mode, daemon, target, disable_on_failure=True)
    greatest_status = max(greatest_status, status)
  return greatest_status


# This function performs a systemctl command (mode) on a given daemon on a given machine
def perform_systemctl_command_on_worker(daemon, mode, target):
  if not target in WORKERS:
    print("There is no machine with the key {0}".format(target))
    sys.exit(EXIT_CODES['bad_arguments'])

  if WORKERS[target]['enabled'] == False:
    print("Skipping {0} of {1} because worker machine {2} is disabled".format(mode, daemon, target))
    return EXIT_CODES['inactive']

  user = WORKERS[target]['username']
  host = WORKERS[target]['address']

  if host == 'localhost' or user == '':
    print("Please don't specify machine id if you wish to run locally.")
    sys.exit(EXIT_CODES['bad_arguments'])

  script_directory = os.path.join(INSTALL_DIR, 'sbin', 'shipper_utils', 'systemctl_wrapper.py')
  command = "sudo {0} {1} --daemon {2}".format(script_directory, mode, daemon)
  try:
      (target_connection,
       intermediate_connection) = ssh_proxy_jump.ssh_connection_allowing_proxy_jump(user,host)
  except (socket.timeout, paramiko.ssh_exception.NoValidConnectionsError) as ioe:
      print("ERROR: could not ssh to {0}@{1} due to a network error: {2}".format(user, host,str(ioe)))
      return EXIT_CODES['io_error']
  except Exception as e:
      print("ERROR: could not ssh to {0}@{1} due to following error: {2}".format(user, host,str(e)))
      return EXIT_CODES['failure']
  try:
      (stdin, stdout, stderr) = target_connection.exec_command(command, timeout=5)
      status = int(stdout.channel.recv_exit_status())
  except Exception as e:
      print("ERROR: Command did not properly execute: ".format(host, str(e)))
      status = EXIT_CODES['failure']
  finally:
      target_connection.close()
      if intermediate_connection:
          intermediate_connection.close()
      return status

def disable_machine(target):
  return
  if WORKERS == None:
    print('Cannot disable as autograding_workers.json does not exist.')
    return
  print('Disabling', target)
  WORKERS[target]['enabled'] = False

  with open(AUTOGRADING_WORKERS_PATH, 'w') as json_file:
    json.dump(WORKERS, json_file, indent=4)

def verify_systemctl_status_code(status, mode, daemon, target, disable_on_failure=False):
  if not mode in VALID_MODES:
    print("ERROR: Invalid mode")
    return False

  correct = True
  if (mode == 'start' or mode == 'restart') and status != EXIT_CODES['active']:
    correct = False
  elif mode == 'stop' and status != EXIT_CODES['inactive']:
    correct = False

  if correct == False:
    print("ERROR: Could not {0} the {1} daemon on {2}".format(mode, daemon, target))
    if disable_on_failure:
      disable_machine(target)

  return correct

def parse_arguments():
  #parse arguments
  parser = argparse.ArgumentParser(description='A wrapper for the various systemctl functions. \
    This script must be run as the submitty supervisor.',
    epilog="""ERROR CODES:
0 = After performing the specified action, the requested daemon is dead
1 = After performing the specified action, the requested daemon is alive
2 = Failure performing operation
3 = invalid mode, daemon, or target""")
  parser.add_argument("--daemon", metavar="DAEMON", type=str.lower, help="Optional. Valid options are worker and shipper. \
    If unspecified, worker is assumed.", choices=['worker', 'shipper'])
  parser.add_argument("--target", metavar="TARGET", type=str, help="Optional. Give the id of a machine in the \
    autograding_workers json and this script will perform the desired function on that machine. If perform_on_all_workers is\
    specified, the command will be processed on all worker machines. If not specified, the status of this machine is checked.")
  parser.add_argument("mode", metavar="MODE", type=str.lower, help="Valid modes are status, start, restart, and stop", 
    choices=VALID_MODES)

  return parser.parse_args()

def main(daemon, target, mode):
  if daemon == None:
    daemon = 'worker'

  # determine whether we are working on a local or foreign machine
  local = False if (target != None and target.lower() != 'primary') else True

  command = 'sudo systemctl {0} submitty_autograding_{1}'.format(mode, daemon)
  status_command = 'sudo systemctl is-active submitty_autograding_{0}'.format(daemon)

  if local:
    # If running locally, verify we are running using sudo.
    if not int(os.getuid()) == 0:
      print("ERROR: If running locally, this script must be run using sudo")
      sys.exit(EXIT_CODES['failure'])
    #we always run the status command, regardless of what was passed in. If a command other than
    #  status was passed, run it first, then check status.
    if not mode == 'status':
      os.system(command)
    # the is-active command just returns either the word 'active' or 'inactive'
    text = os.popen(status_command).read().strip()
    status = EXIT_CODES['active'] if (text == 'active') else EXIT_CODES['inactive']
    # verifies that after performing the 'mode' command, the resulting status is correct.
    verify_systemctl_status_code(status, mode, daemon, target, disable_on_failure=False)
    # local is a keyword which causes print_status_message to print no machine prefix.
    print_status_message(status, mode, daemon, 'local')
  else:
    if WORKERS == None:
      print("ERROR: the autograding_workers.json was not found on your machine. Please make sure you are installed\
              as a primary machine.")
      system.exit(EXIT_CODES['failure'])
    # if we are checking the status of a daemon on another machine, make sure we are running
    #    as DAEMON_USER, who has the ssh keys.
    if not int(os.getuid()) == DAEMON_UID:
      print("ERROR: if running on another machine, this script must be run as DAEMON_USER, "+DAEMON_USER)
      sys.exit(EXIT_CODES['failure'])
    # perform_on_all_workers is a keyword that causes us to run the command on every worker machine.
    #   This is helpful for performing start all or stop all commands.
    if target.lower() == "perform_on_all_workers":
      status = perform_systemctl_command_on_all_workers(daemon, mode)
    else:
      status = perform_systemctl_command_on_worker(daemon, mode, target)
      # verifies that after performing the 'mode' command, the resulting status is correct.
      verify_systemctl_status_code(status, mode, daemon, target, disable_on_failure=True)
      print_status_message(status, mode, daemon, target)


  sys.exit(status)

if __name__ == "__main__":
  args = parse_arguments()
  main(args.daemon, args.target, args.mode)
