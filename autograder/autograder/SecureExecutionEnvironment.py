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
from . import autograding_utils, CONFIG_PATH

class SecureExecutionEnvironment():
  def __init__(self, testcase_directory, complete_config_obj, pre_commands, testcase_obj, autograding_directory, is_test_environment):
    self.directory = testcase_directory
    self.patterns = complete_config_obj['autograding']
    self.my_testcase = testcase_obj
    self.pre_commands = pre_commands

    self.tmp = autograding_directory
    self.tmp_work = os.path.join(autograding_directory, 'TMP_WORK')
    self.tmp_autograding = os.path.join(autograding_directory, 'TMP_AUTOGRADING')
    self.tmp_submission = os.path.join(autograding_directory, 'TMP_SUBMISSION')
    self.tmp_logs = os.path.join(autograding_directory,"TMP_SUBMISSION","tmp_logs")
    self.checkout_path = os.path.join(self.tmp_submission, "checkout")
    self.checkout_subdirectory = complete_config_obj["autograding"].get("use_checkout_subdirectory","")
    self.is_test_environment = is_test_environment

  def _run_pre_commands(self):
    for pre_command in self.pre_commands:
      command = pre_command['command']
      source_testcase   = pre_command["testcase"]
      source_directory  = pre_command['source']
      destination = pre_command['destination']

      if command == 'cp':
        try:
          pre_command_copy_file(source_testcase, source_directory, self.directory, 
                                destination, self.my_testcase.job_id, self.tmp_logs,
                                self.my_testcase.log_path, self.my_testcase.stack_trace_log_path)
        except Exception as e:
          self.my_testcase.log(message="Encountered an error while processing pre-command. See traces entry for more details.")
          self.my_testcase.log_stack_trace(traceback.format_exc())
      else:
        self.my_testcase.log(message="Encountered an error while processing pre-command. See traces entry for more details.")
        print("Invalid pre-command '{0}'".format(command))

  def _setup_single_directory_for_compilation(self, directory):
    provided_code_path = os.path.join(self.tmp_autograding, 'provided_code')
    bin_path = os.path.join(self.tmp_autograding, 'bin')
    submission_path = os.path.join(self.tmp_submission, 'submission')

    os.makedirs(self.directory)
    autograding_utils.pattern_copy("submission_to_compilation", self.patterns['submission_to_compilation'], submission_path, self.directory, self.tmp_logs)

    if self.my_testcase.is_vcs:
      checkout_subdir_path = os.path.join(self.tmp_submission, 'checkout', self.checkout_subdirectory)
      autograding_utils.pattern_copy("checkout_to_compilation",self.patterns['checkout_to_compilation'],checkout_subdir_path,self.directory,self.tmp_logs)
    
    # copy any instructor provided code files to tmp compilation directory
    autograding_utils.copy_contents_into(self.my_testcase.job_id,provided_code_path,self.directory,self.tmp_logs, self.my_testcase.log_path, self.my_testcase.stack_trace_log_path)
    # copy compile.out to the current directory
    shutil.copy (os.path.join(bin_path,"compile.out"),os.path.join(self.directory,"my_compile.out"))
    
    autograding_utils.add_permissions(os.path.join(self.directory,"my_compile.out"), stat.S_IXUSR | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)
    autograding_utils.add_permissions_recursive(self.directory,
                              stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                              stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                              stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)

  def _setup_single_directory_for_execution(self, directory):
    # Make the testcase folder
    os.makedirs(self.directory)

    # Patterns
    submission_path = os.path.join(self.tmp_submission, 'submission')
    checkout_path = os.path.join(submission_path, "checkout")

    autograding_utils.pattern_copy("submission_to_runner",self.patterns["submission_to_runner"], 
                            submission_path, self.directory, self.tmp_logs)

    if self.my_testcase.is_vcs:
        autograding_utils.pattern_copy("checkout_to_runner",self.patterns["checkout_to_runner"], 
                            checkout_path, self.directory, self.tmp_logs)

    for c in self.my_testcase.testcase_dependencies:
        if c.is_compilation():
            autograding_utils.pattern_copy("compilation_to_runner", self.patterns['compilation_to_runner'], 
                         c.secure_environment.directory, self.directory, self.tmp_logs)

    # copy input files
    test_input_path = os.path.join(self.tmp_autograding, 'test_input_path')
    autograding_utils.copy_contents_into(self.my_testcase.job_id, test_input_path, self.directory, self.tmp_logs, self.my_testcase.log_path, self.my_testcase.stack_trace_log_path)

    # copy runner.out to the current directory
    bin_runner = os.path.join(self.tmp_autograding, "bin","run.out")
    my_runner  = os.path.join(self.directory, "my_runner.out")
    
    shutil.copy(bin_runner, my_runner)

    autograding_utils.add_permissions(my_runner, stat.S_IXUSR | stat.S_IXGRP |stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)

    # Add the correct permissions to the target folder.
    autograding_utils.add_permissions_recursive(self.directory,
                              stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                              stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                              stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)


  def _setup_single_directory_for_validation(self):
    if not self.is_test_environment:
      autograding_utils.add_permissions_recursive(self.tmp_work,
                                stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                                stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                                stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH) 

    submission_path = os.path.join(self.tmp_submission, 'submission')

    # copy results files from compilation...
    autograding_utils.pattern_copy("submission_to_validation", self.patterns['submission_to_validation'],
                 submission_path, self.directory, self.tmp_logs)
    if self.my_testcase.is_vcs:
        checkout_subdir_path = os.path.join(self.tmp_submission, 'checkout', self.checkout_subdirectory)
        autograding_utils.pattern_copy("checkout_to_validation",self.patterns['submission_to_validation'],checkout_subdir_path,tmp_work,tmp_logs)
    
    autograding_utils.pattern_copy("compilation_to_validation", self.patterns['compilation_to_validation'], 
                 self.tmp_compilation, self.directory, self.tmp_logs)

    # copy output files
    test_output_path = os.path.join(self.tmp_autograding, 'test_output')
    autograding_utils.copy_contents_into(self.my_testcase.job_id, test_output_path, self.directory, self.tmp_logs, self.my_testcase.log_path, self.my_testcase.stack_trace_log_path)

    # copy any instructor instructor_solution code into the tmp work directory
    instructor_solution = os.path.join(self.tmp_autograding, 'instructor_solution')
    autograding_utils.copy_contents_into(self.my_testcase.job_id, instructor_solution, self.directory, self.tmp_logs, self.my_testcase.log_path, self.my_testcase.stack_trace_log_path)

    # copy any instructor custom validation code into the tmp work directory
    custom_validation_code_path = os.path.join(self.tmp_autograding, 'custom_validation_code')
    autograding_utils.copy_contents_into(self.my_testcase.job_id, custom_validation_code_path, self.directory, self.tmp_logs, self.my_testcase.log_path, self.my_testcase.stack_trace_log_path)

    

    # copy the runner
    bin_runner = os.path.join(self.tmp_autograding, "bin","validate.out")
    my_runner  = os.path.join(self.directory, "my_validator.out")
    
    shutil.copy(bin_runner, my_runner)

    if not self.is_test_environment:
      autograding_utils.add_permissions_recursive(self.tmp_work,
                              stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                              stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                              stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)
      autograding_utils.add_permissions(my_runner, stat.S_IXUSR | stat.S_IXGRP |stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)

  def setup_for_compilation_testcase(self):
    os.chdir(self.tmp_work)
    self._setup_single_directory_for_compilation(self.directory)
    # Run any necessary pre_commands
    self._run_pre_commands()

  def setup_for_execution_testcase(self):
    os.chdir(self.tmp_work)
    self._setup_single_directory_for_execution(self.directory)
    self._run_pre_commands()

  def setup_for_validation_testcase(self):
    os.chdir(self.tmp_work)
    self._setup_single_directory_for_validation(self.directory)

  def setup_for_testcase_archival(self):
    os.makedirs(os.path.join(self.tmp_results,"details"), exist_ok=True)
    os.makedirs(os.path.join(self.tmp_results,"results_public"), exist_ok=True)
    os.chdir(self.tmp)

  def archive_results(self, overall_log):
    raise NotImplementedError

  # Should start with a chdir and end with a chdir
  def execute(self, untrusted_user, executable, arguments, logfile):
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

      print("If we are in production but we are not the daemon user, throw an error")
      if int(os.getuid()) != int(DAEMON_UID):
        raise Exception("ERROR: grade_item should be run by submitty_daemon in a production environment")
