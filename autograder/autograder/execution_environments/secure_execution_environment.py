import json
import os
import shutil
import subprocess
import stat
import time
import traceback
import socket
from pwd import getpwnam
import glob

from submitty_utils import dateutils
from .. import autograding_utils, CONFIG_PATH

class SecureExecutionEnvironment():
  def __init__(self, job_id, untrusted_user, testcase_directory, is_vcs, is_batch_job, complete_config_obj, 
               testcase_info, autograding_directory, log_path, stack_trace_log_path, is_test_environment):
    self.job_id = job_id
    self.is_batch = is_batch_job
    self.untrusted_user = untrusted_user
    self.is_vcs = is_vcs
    self.log_path = log_path
    self.stack_trace_log_path = stack_trace_log_path
    self.name = testcase_directory
    self.patterns = complete_config_obj['autograding']
    self.pre_commands = testcase_info.get('pre_commands', list())
    self.is_test_environment = is_test_environment

    self.tmp = autograding_directory
    self.tmp_work = os.path.join(autograding_directory, 'TMP_WORK')
    self.tmp_autograding = os.path.join(autograding_directory, 'TMP_AUTOGRADING')
    self.tmp_submission = os.path.join(autograding_directory, 'TMP_SUBMISSION')
    self.tmp_logs = os.path.join(autograding_directory,"TMP_SUBMISSION","tmp_logs")
    self.tmp_results = os.path.join(autograding_directory,"TMP_RESULTS")
    self.checkout_path = os.path.join(self.tmp_submission, "checkout")
    self.checkout_subdirectory = complete_config_obj["autograding"].get("use_checkout_subdirectory","")
    self.directory = os.path.join(self.tmp_work, testcase_directory)

    if is_test_environment == False:
      from .. import CONFIG_PATH
      with open(os.path.join(CONFIG_PATH, 'submitty.json')) as open_file:
        OPEN_JSON = json.load(open_file)
      self.SUBMITTY_INSTALL_DIR = OPEN_JSON['submitty_install_dir']

  def _run_pre_commands(self):
    for pre_command in self.pre_commands:
      command = pre_command['command']
      source_testcase   = pre_command["testcase"]
      source_directory  = pre_command['source']
      destination = pre_command['destination']

      if command == 'cp':
        try:
          autograding_utils.pre_command_copy_file(source_testcase, source_directory, self.directory, 
                                                  destination, self.job_id, self.tmp_logs,
                                                  self.log_path, self.stack_trace_log_path)
        except Exception as e:
          self.log_message("Encountered an error while processing pre-command. See traces entry for more details.")
          self.log_stack_trace(traceback.format_exc())
      else:
        self.log_message("Encountered an error while processing pre-command. See traces entry for more details.")
        print("Invalid pre-command '{0}'".format(command))

  def _setup_single_directory_for_compilation(self, directory):
    provided_code_path = os.path.join(self.tmp_autograding, 'provided_code')
    bin_path = os.path.join(self.tmp_autograding, 'bin')
    submission_path = os.path.join(self.tmp_submission, 'submission')

    os.makedirs(directory)

    autograding_utils.pattern_copy("submission_to_compilation", self.patterns['submission_to_compilation'], submission_path, directory, self.tmp_logs)

    if self.is_vcs:
      checkout_subdir_path = os.path.join(self.tmp_submission, 'checkout', self.checkout_subdirectory)
      autograding_utils.pattern_copy("checkout_to_compilation",self.patterns['checkout_to_compilation'],checkout_subdir_path, directory,self.tmp_logs)
    
    # copy any instructor provided code files to tmp compilation directory
    autograding_utils.copy_contents_into(self.job_id, provided_code_path, directory, self.tmp_logs, self.log_path, self.stack_trace_log_path)
    # copy compile.out to the current directory
    shutil.copy (os.path.join(bin_path,"compile.out"),os.path.join(directory,"my_compile.out"))
    
    autograding_utils.add_permissions(os.path.join(directory,"my_compile.out"), stat.S_IXUSR | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)
    autograding_utils.add_all_permissions(directory)

  def lockdown_directory_after_execution(self):
    if self.is_test_environment == False:
        autograding_utils.untrusted_grant_rwx_access(self.SUBMITTY_INSTALL_DIR, self.untrusted_user, self.directory)
    autograding_utils.add_all_permissions(self.directory)
    autograding_utils.lock_down_folder_permissions(self.directory)

  def _setup_single_directory_for_execution(self, directory, testcase_dependencies):
    # Make the testcase folder
    os.makedirs(directory)

    # Patterns
    submission_path = os.path.join(self.tmp_submission, 'submission')
    checkout_path = os.path.join(submission_path, "checkout")

    autograding_utils.pattern_copy("submission_to_runner",self.patterns["submission_to_runner"], 
                            submission_path, directory, self.tmp_logs)

    if self.is_vcs:
        autograding_utils.pattern_copy("checkout_to_runner",self.patterns["checkout_to_runner"], 
                            checkout_path, directory, self.tmp_logs)

    # TODO: This should be removed as soon as we move to all pre_commands.
    for c in testcase_dependencies:
        if c.type == 'Compilation':
            autograding_utils.pattern_copy("compilation_to_runner", self.patterns['compilation_to_runner'], 
                         c.secure_environment.directory, directory, self.tmp_logs)

    # copy input files
    test_input_path = os.path.join(self.tmp_work, 'test_input')
    autograding_utils.copy_contents_into(self.job_id, test_input_path, directory, self.tmp_logs, self.log_path, self.stack_trace_log_path)

    # copy runner.out to the current directory
    bin_runner = os.path.join(self.tmp_autograding, "bin","run.out")
    my_runner  = os.path.join(directory, "my_runner.out")
    
    shutil.copy(bin_runner, my_runner)

    autograding_utils.add_permissions(my_runner, stat.S_IXUSR | stat.S_IXGRP |stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)

    # Add the correct permissions to the target folder.
    if self.is_test_environment == False:
      autograding_utils.untrusted_grant_rwx_access(self.SUBMITTY_INSTALL_DIR, self.untrusted_user, self.directory)

    autograding_utils.add_all_permissions(directory)

  def setup_for_compilation_testcase(self):
    os.chdir(self.tmp_work)
    self._setup_single_directory_for_compilation(self.directory)
    # Run any necessary pre_commands
    self._run_pre_commands()

  def setup_for_execution_testcase(self, testcase_dependencies):
    os.chdir(self.tmp_work)
    self._setup_single_directory_for_execution(self.directory, testcase_dependencies)
    self._run_pre_commands()

  def setup_for_testcase_archival(self):
    os.makedirs(os.path.join(self.tmp_results,"details"), exist_ok=True)
    os.makedirs(os.path.join(self.tmp_results,"results_public"), exist_ok=True)
    os.chdir(self.tmp_work)
    
    details_dir = os.path.join(self.tmp_results, "details", self.name)
    public_dir = os.path.join(self.tmp_results,"results_public", self.name)

    os.mkdir(details_dir)
    os.mkdir(public_dir)

  def archive_results(self, overall_log):
    raise NotImplementedError

  # Should start with a chdir and end with a chdir
  def execute(self, untrusted_user, executable, arguments, logfile, cwd):
    raise NotImplementedError

  def verify_execution_status(self):

    is_production = not self.is_test_environment

    config_exists = os.path.isdir(CONFIG_PATH)

    # If we are production but cannot access the config directory, throw an error
    if config_exists == False and is_production:
      raise Exception("ERROR: cannot find the submitty configuration directory.")

    # If we are in a test environment, there should NOT be a config directory
    if config_exists == True and self.is_test_environment:
      raise Exception("ERROR: This script does not appear to truly be running in a test environment")

    if config_exists and is_production:
      with open(os.path.join(CONFIG_PATH, 'submitty_users.json')) as open_file:
          OPEN_JSON = json.load(open_file)
      DAEMON_UID = OPEN_JSON['daemon_uid']

      # If we are in production but we are not the daemon user, throw an error
      if int(os.getuid()) != int(DAEMON_UID):
        raise Exception("ERROR: grade_item should be run by submitty_daemon in a production environment")

  def log_message(self, message):
    autograding_utils.log_message(self.log_path,
                                job_id = self.job_id,
                                is_batch = self.is_batch,
                                which_untrusted = self.untrusted_user,
                                message = message)

  def log_stack_trace(self, trace):
    autograding_utils.log_stack_trace(self.stack_trace_log_path,
                                      job_id = self.job_id,
                                      is_batch = self.is_batch,
                                      which_untrusted = self.untrusted_user,
                                      trace = trace)
