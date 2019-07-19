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
from . import grade_item, grade_items_logging

class SecureExecutionEnvironment():
  def __init__(testcase_directory, complete_config_obj, testcase_obj, autograding_directory, untrusted_execute_path):
    self.directory = testcase_directory
    self.patterns = complete_config_obj['autograding']
    self.my_testcase = testcase_obj
    self.pre_commands = testcase_obj['pre_commands']
    self.untrusted_execute_path = untrusted_execute_path

    self.tmp = autograding_directory
    self.tmp_work = os.path.join(autograding_directory, 'TMP_WORK')
    self.tmp_autograding = os.path.join(autograding_directory, 'TMP_AUTOGRADING')
    self.tmp_submission = os.path.join(autograding_directory, 'TMP_SUBMISSION')
    self.tmp_logs = os.path.join(autograding_directory,"TMP_SUBMISSION","tmp_logs")
    self.checkout_path = os.path.join(self.tmp_submission, "checkout")
    self.checkout_subdirectory = complete_config_obj["autograding"].get("use_checkout_subdirectory","")

  def _run_pre_commands(self):
    for pre_command in self.pre_commands:
      command = pre_command['command']
      source_testcase   = pre_command["testcase"]
      source_directory  = pre_command['source']
      destination = pre_command['destination']

      if command == 'cp':
        try:
          pre_command_copy_file(source_testcase, source_directory, self.directory, destination, self.my_testcase.job_id, self.tmp_logs)
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
    pattern_copy("submission_to_compilation", self.patterns['submission_to_compilation'], submission_path, self.directory, self.tmp_logs)

    if self.my_testcase.is_vcs:
      checkout_subdir_path = os.path.join(self.tmp_submission, 'checkout', self.checkout_subdirectory)
      pattern_copy("checkout_to_compilation",self.patterns['checkout_to_compilation'],checkout_subdir_path,self.directory,self.tmp_logs)
    
    # copy any instructor provided code files to tmp compilation directory
    copy_contents_into(self.my_testcase.job_id,provided_code_path,self.directory,self.tmp_logs)
    # copy compile.out to the current directory
    shutil.copy (os.path.join(bin_path,"compile.out"),os.path.join(self.directory,"my_compile.out"))
    add_permissions(os.path.join(self.directory,"my_compile.out"), stat.S_IXUSR | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)

    untrusted_grant_rwx_access(which_untrusted, self.directory)
    add_permissions_recursive(self.directory,
                              stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                              stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                              stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)

    untrusted_grant_rwx_access(which_untrusted, tmp_work)          

  def _setup_single_directory_for_execution(self, directory):
    # Make the testcase folder
    os.makedirs(self.directory)

    # Patterns
    submission_path = os.path.join(self.tmp_submission, 'submission')
    checkout_path = os.path.join(submission_path, "checkout")

    pattern_copy("submission_to_runner",self.patterns["submission_to_runner"], 
                            submission_path, self.directory, self.tmp_logs)

    if self.my_testcase.is_vcs:
        pattern_copy("checkout_to_runner",self.patterns["checkout_to_runner"], 
                            checkout_path, self.directory, self.tmp_logs)

    for c in self.my_testcase.testcase_dependencies:
        if c.is_compilation():
            pattern_copy("compilation_to_runner", self.patterns['compilation_to_runner'], 
                         c.secure_environment.directory, self.directory, self.tmp_logs)

    # copy input files
    test_input_path = os.path.join(self.tmp_autograding, 'test_input_path')
    copy_contents_into(self.my_testcase.job_id, test_input_path, self.directory, self.tmp_logs)

    # copy runner.out to the current directory
    bin_runner = os.path.join(self.tmp_autograding, "bin","run.out")
    my_runner  = os.path.join(self.directory, "my_runner.out")
    
    shutil.copy(bin_runner, my_runner)

    add_permissions(my_runner, stat.S_IXUSR | stat.S_IXGRP |stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)

    # Add the correct permissions to the target folder.
    add_permissions_recursive(self.directory,
                              stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                              stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                              stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)

  def _setup_single_directory_for_validation(self):
    add_permissions_recursive(self.tmp_work,
                              stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                              stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                              stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH) 

    submission_path = os.path.join(self.tmp_submission, 'submission')

    # copy results files from compilation...
    pattern_copy("submission_to_validation", self.patterns['submission_to_validation'],
                 submission_path, self.directory, self.tmp_logs)
    if self.my_testcase.is_vcs:
        checkout_subdir_path = os.path.join(self.tmp_submission, 'checkout', self.checkout_subdirectory)
        pattern_copy("checkout_to_validation",self.patterns['submission_to_validation'],checkout_subdir_path,tmp_work,tmp_logs)
    
    pattern_copy("compilation_to_validation", self.patterns['compilation_to_validation'], 
                 self.tmp_compilation, self.directory, self.tmp_logs)

    # copy output files
    test_output_path = os.path.join(self.tmp_autograding, 'test_output')
    copy_contents_into(self.my_testcase.job_id, test_output_path, self.directory, self.tmp_logs)

    # copy any instructor instructor_solution code into the tmp work directory
    instructor_solution = os.path.join(self.tmp_autograding, 'instructor_solution')
    copy_contents_into(self.my_testcase.job_id, instructor_solution, self.directory, self.tmp_logs)

    # copy any instructor custom validation code into the tmp work directory
    custom_validation_code_path = os.path.join(self.tmp_autograding, 'custom_validation_code')
    copy_contents_into(self.my_testcase.job_id, custom_validation_code_path, self.directory, self.tmp_logs)

    add_permissions_recursive(self.tmp_work,
                              stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                              stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                              stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)


    # copy the runner
    bin_runner = os.path.join(self.tmp_autograding, "bin","validate.out")
    my_runner  = os.path.join(self.directory, "my_validator.out")
    
    shutil.copy(bin_runner, my_runner)

    add_permissions(my_runner, stat.S_IXUSR | stat.S_IXGRP |stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)

  def setup_for_compilation(self):
    os.chdir(self.tmp_work)
    self._setup_single_directory_for_compilation(self.directory)
    # Run any necessary pre_commands
    self._run_pre_commands()

  def setup_for_execution(self):
    os.chdir(self.tmp_work)
    self._setup_single_directory_for_execution(self.directory)
    self._run_pre_commands()

  def setup_for_validation(self):
    os.chdir(self.tmp_work)
    self._setup_single_directory_for_validation(self.directory)

  def setup_for_archival(self):
    os.makedirs(os.path.join(self.tmp_results,"details"), exist_ok=True)
    os.makedirs(os.path.join(self.tmp_results,"results_public"), exist_ok=True)
    os.chdir(self.tmp)

  def archive_results(self, overall_log):
    raise NotImplementedError

  # Should start with a chdir and end with a chdir
  def execute(self, untrusted_user, executable, arguments, logfile):
    raise NotImplementedError
