import json
import os
import shutil
import subprocess
import stat
import time
import traceback
from pwd import getpwnam
import glob

from submitty_utils import dateutils
from . import grade_item, grade_items_logging, CONFIG_PATH

with open(os.path.join(CONFIG_PATH, 'submitty.json')) as open_file:
    OPEN_JSON = json.load(open_file)
    SUBMITTY_INSTALL_DIR = OPEN_JSON['submitty_install_dir']
    SUBMITTY_DATA_DIR = OPEN_JSON['submitty_data_dir']

with open(os.path.join(CONFIG_PATH, 'submitty_users.json')) as open_file:
    OPEN_JSON = json.load(open_file)
    DAEMON_UID = OPEN_JSON['daemon_uid']

class Testcase():
  def __init__(logfile, queue_obj, testcase_info, testcase_number, parent_directory, use_container, untrusted_user):
    self.logfile = logfile
    self.is_batch_job = queue_obj['regrade']
    self.job_id = queue_obj['job_id']
    self.testcase_number = testcase_number
    self.parent_directory = parent_directory
    self.testcase_directory = os.path.join(parent_directory, "test{:02}".format(testcase_number))
    self.type = testcase_info.get('type', 'Execution')
    self.my_untrusted = self.untrusted_user

    if use_container:
      self.my_secure_environment = ContainerNetwork(self.parent_directory, self.testcase_directory, self.log, testcase_info)
    else:
      self.my_secure_environment = JailedSandbox(self.parent_directory, self.testcase_directory, self.log)

class SecureExecutionEnvironment():

  def __init__(parent_directory, directory, log_function):
    self.parent_directory = parent_directory
    self.directory = directory
    self.log_function = log_function

  def log(message):
    self.log_function(message)

  # Assumes important folders have already been made.
  def _run_pre_commands():
    #The paths to the important folders.
    tmp_work_test_input = os.path.join(self.parent_directory, "test_input")
    tmp_work_submission = os.path.join(self.parent_directory, "submitted_files")
    tmp_work_compiled = os.path.join(self.parent_directory, "compiled_files")
    tmp_work_checkout = os.path.join(self.parent_directory, "checkout")
    my_runner = os.path.join(self.parent_directory,"my_runner.out")

    #######################################################################################
    #
    # PRE-COMMANDS
    #
    ######################################################################################

    pre_commands = testcase.get('pre_commands', [])

    for pre_command in pre_commands:
      command = pre_command['command']
      source_testcase   = pre_command["testcase"]
      source_directory  = pre_command['source']
      destination = pre_command['destination']

      if command == 'cp':
        try:
          pre_command_copy_file(tmp_work, source_testcase, source_directory, target_folder, destination,job_id,tmp_logs)
        except Exception as e:
          traceback.print_exc()
          grade_items_logging.log_message(job_id, message="Encountered an error while processing pre-command. See traces entry for more details.")
      else:
        grade_items_logging.log_message(job_id, message="Encountered an error while processing pre-command. See traces entry for more details.")
        print("Invalid pre-command '{0}'".format(command))

  def setup():
    pass

  def execute(untrusted_user, executable, arguments, logfile):
    raise NotImplementedError 

  def teardown():
    raise NotImplementedError

  def lock_down_permissions():
    raise NotImplementedError

class JailedSandbox(SecureExecutionEnvironment):
  def __init__(parent_directory, directory, log_function):
     super().__init__(parent_directory, directory, log_function)

  def setup():
    os.makedirs(testcase_folder)

    #The paths to the important folders.
    tmp_work_test_input = os.path.join(tmp_work, "test_input")
    tmp_work_submission = os.path.join(tmp_work, "submitted_files")
    tmp_work_compiled = os.path.join(tmp_work, "compiled_files")
    tmp_work_checkout = os.path.join(tmp_work, "checkout")
    my_runner = os.path.join(tmp_work,"my_runner.out")

    #######################################################################################
    #
    # PRE-COMMANDS
    #
    ######################################################################################

    pre_commands = testcase.get('pre_commands', [])

    for pre_command in pre_commands:
      command = pre_command['command']
      source_testcase   = pre_command["testcase"]
      source_directory  = pre_command['source']
      destination = pre_command['destination']

      if command == 'cp':
        try:
          pre_command_copy_file(tmp_work, source_testcase, source_directory, target_folder, destination,job_id,tmp_logs)
        except Exception as e:
          traceback.print_exc()
          grade_items_logging.log_message(job_id, message="Encountered an error while processing pre-command. See traces entry for more details.")
      else:
        grade_items_logging.log_message(job_id, message="Encountered an error while processing pre-command. See traces entry for more details.")
        print("Invalid pre-command '{0}'".format(command))









  def log(message):
    print(messae, file=self.logfile)
