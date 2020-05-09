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
  """ 
  ABSTRACT CLASS: A secure execution environment must be able to securely set up for and
  execute the various phases of compilation, including running the input generation,
  execution/compilation, output generation, archival, and pre_command phases of grading.
  """
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
    self.random_input_directory = os.path.join(self.tmp_work, 'random_input', testcase_directory)
    self.instructor_solution_path = os.path.join(self.tmp_autograding, "instructor_solution")
    self.random_output_directory = os.path.join(self.tmp_work,"random_output", testcase_directory)

    # If we are not in a test environment, we are able to load configuration variables using the CONFIG_PATH.
    if is_test_environment == False:
      from .. import CONFIG_PATH
      with open(os.path.join(CONFIG_PATH, 'submitty.json')) as open_file:
        OPEN_JSON = json.load(open_file)
      self.SUBMITTY_INSTALL_DIR = OPEN_JSON['submitty_install_dir']


  def _run_pre_commands(self, target_directory):
    """
    Run pre commands for a given directory. Currently only cp is supported.
    """
    for pre_command in self.pre_commands:
      command = pre_command['command']
      source_testcase   = pre_command["testcase"]
      source_directory  = pre_command['source']
      destination = pre_command['destination']

      if command == 'cp':
        try:
          autograding_utils.pre_command_copy_file(source_testcase, source_directory, target_directory, 
                                                  destination, self.job_id, self.tmp_logs,
                                                  self.log_path, self.stack_trace_log_path)
        except Exception as e:
          self.log_message("Encountered an error while processing pre-command. See traces entry for more details.")
          self.log_stack_trace(traceback.format_exc())
      else:
        self.log_message("Encountered an error while processing pre-command. See traces entry for more details.")
        print("Invalid pre-command '{0}'".format(command))


  def _setup_single_directory_for_compilation(self, directory):
    """ Prepare a directory to be used by a compilation testcase. """

    provided_code_path = os.path.join(self.tmp_autograding, 'provided_code')
    bin_path = os.path.join(self.tmp_autograding, 'bin')
    submission_path = os.path.join(self.tmp_submission, 'submission')

    # Create the directory
    os.makedirs(directory)

    # Copy in provided and submitted code.
    autograding_utils.pattern_copy("submission_to_compilation", self.patterns['submission_to_compilation'], submission_path, directory, self.tmp_logs)

    if self.is_vcs:
      checkout_subdir_path = os.path.join(self.tmp_submission, 'checkout', self.checkout_subdirectory)
      autograding_utils.pattern_copy("checkout_to_compilation",self.patterns['submission_to_compilation'],checkout_subdir_path, directory,self.tmp_logs)
    
    if os.path.exists(provided_code_path):
      autograding_utils.copy_contents_into(self.job_id, provided_code_path, directory, self.tmp_logs, self.log_path, self.stack_trace_log_path)

    # Copy compile.out to the current directory.
    shutil.copy (os.path.join(bin_path,"compile.out"),os.path.join(directory,"my_compile.out"))
    
    # Permission the directory. NOTE: After execution, lockdown_directory_after_execution will be called.
    autograding_utils.add_permissions(os.path.join(directory,"my_compile.out"), stat.S_IXUSR | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)
    autograding_utils.add_all_permissions(directory)


  def lockdown_directory_after_execution(self, directory=None):
    """ Lock down a directory so that the untrusted user does not have access. """
    directory = self.directory if directory is None else directory
    
    # If we are not in a test environment, there is an untrusted user, and we must get access to files back from them.
    if self.is_test_environment == False:
        autograding_utils.untrusted_grant_rwx_access(self.SUBMITTY_INSTALL_DIR, self.untrusted_user, directory)
    # First give daemon all permissions, then lock down the folder permissions (700).
    autograding_utils.add_all_permissions(directory)
    autograding_utils.lock_down_folder_permissions(directory)


  def _setup_single_directory_for_execution(self, directory, testcase_dependencies):
    """ Prepare a directory to be used by an execution testcase. """
    
    # Make the testcase directory.
    os.makedirs(directory)

    submission_path = os.path.join(self.tmp_submission, 'submission')
    checkout_path = os.path.join(self.tmp_submission, "checkout")

    # Copy in submitted code.
    autograding_utils.pattern_copy("submission_to_runner",self.patterns["submission_to_runner"], 
                            submission_path, directory, self.tmp_logs)

    # Copy these helper files for computing access duration
    # TODO: This could/should be restricted to test cases that require these files,
    # that have the field      "copy_access_files" : true,
    # for example, more_autograding_examples/notebook_time_limit/config/config.json
    shutil.copy(self.tmp_submission+"/queue_file.json",directory)

    user_assignment_settings = os.path.join(self.tmp_submission, "user_assignment_settings.json")
    submit_timestamp = os.path.join(self.tmp_submission, "submission", ".submit.timestamp")
    user_assignment_access = os.path.join(self.tmp_submission, "user_assignment_access.json")

    if os.path.exists(user_assignment_settings):
      shutil.copy(user_assignment_settings, directory)

    if os.path.exists(submit_timestamp):
      shutil.copy(submit_timestamp, directory)

    if os.path.exists(user_assignment_access):
      shutil.copy(user_assignment_access, directory)

    # Copy in checkout code.
    if self.is_vcs:
        autograding_utils.pattern_copy("checkout_to_runner",self.patterns["submission_to_runner"], 
                            os.path.join(checkout_path, self.checkout_subdirectory), directory, self.tmp_logs)

    # For the moment, we define testcase dependencies to be on any previous compilation testcases.
    # For these testcase, we copy in compilation_to_runner.
    # TODO: Update this as our idea of testcase_dependencies develops.
    for c in testcase_dependencies:
        if c.type == 'Compilation':
            autograding_utils.pattern_copy("compilation_to_runner", self.patterns['compilation_to_runner'], 
                         c.secure_environment.directory, directory, self.tmp_logs)

    # Copy in test input files.
    test_input_path = os.path.join(self.tmp_work, 'test_input')
    autograding_utils.copy_contents_into(self.job_id, test_input_path, directory, self.tmp_logs, self.log_path, self.stack_trace_log_path)

    if os.path.exists(self.random_input_directory):
      autograding_utils.pattern_copy("random_input_to_runner", ["*.txt",], self.random_input_directory, directory, self.tmp_logs)

    # Copy runner.out to the current directory.
    bin_runner = os.path.join(self.tmp_autograding, "bin","run.out")
    my_runner  = os.path.join(directory, "my_runner.out")
    shutil.copy(bin_runner, my_runner)

    # Permission the runner.
    autograding_utils.add_permissions(my_runner, stat.S_IXUSR | stat.S_IXGRP |stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)

    # If we are not in a test environment, there is an untrusted user, and we must get access to files back from them.
    if self.is_test_environment == False:
      autograding_utils.untrusted_grant_rwx_access(self.SUBMITTY_INSTALL_DIR, self.untrusted_user, directory)
    
    # Permission the directory. NOTE: After execution, lockdown_directory_after_execution will be called.
    autograding_utils.add_all_permissions(directory)


  def _setup_single_directory_for_random_output(self, directory, testcase_dependencies):
    """ Prepare a directory to run instructor code. """

    # First, we set up the directory as we would an execution directory.
    self._setup_single_directory_for_execution(directory, testcase_dependencies)
    
    # Copy any instructor provided solution to the testcase folder
    autograding_utils.copy_contents_into(self.job_id, self.instructor_solution_path, directory, self.tmp_logs, self.log_path, self.stack_trace_log_path)
    
    # Fix permissions on the solution code.
    if self.is_test_environment == False:
      autograding_utils.untrusted_grant_rwx_access(self.SUBMITTY_INSTALL_DIR, self.untrusted_user, directory)
    autograding_utils.add_all_permissions(directory)


  def setup_for_input_generation(self):
    """ Setup a directory to run input generation commands.  """
    
    # Make the random input directory.
    os.makedirs(self.random_input_directory)

    # copy any instructor provided solution code files to testcase folder (This is where generation code lives right now).
    # TODO: Should we separate out input generation code from the instructor_solution directory?
    autograding_utils.copy_contents_into(self.job_id, self.instructor_solution_path, self.random_input_directory, self.tmp_logs, self.log_path, self.stack_trace_log_path)
    
    # copy run.out to the current directory
    bin_path = os.path.join(self.tmp_autograding, 'bin')
    shutil.copy (os.path.join(bin_path,"run.out"),os.path.join(self.random_input_directory,"my_runner.out"))
    
    # If we are not in a test environment, there is an untrusted user, and we must get access to files back from them.
    if self.is_test_environment == False:
      autograding_utils.untrusted_grant_rwx_access(self.SUBMITTY_INSTALL_DIR, self.untrusted_user, self.random_input_directory)
    
    # Permission the directory. NOTE: After execution, lockdown_directory_after_execution will be called.
    autograding_utils.add_permissions(os.path.join(self.random_input_directory, "my_runner.out"), stat.S_IXUSR | stat.S_IXGRP |stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)          
    autograding_utils.add_all_permissions(self.random_input_directory)


  def setup_for_random_output(self, testcase_dependencies):
    """ 
    The default random output setup function just sets up this testcase's random_output_directory 
    for execution. This must be overridden if different directories are to be used (e.g. by container_network)
    """
    os.chdir(self.tmp_work)
    self._setup_single_directory_for_random_output(self.random_output_directory, testcase_dependencies)
    self._run_pre_commands(self.random_output_directory)


  def setup_for_compilation_testcase(self):
    """ 
    The default compilation setup function just sets up this testcase's directory 
    for execution. This must be overridden if different directories are to be used (e.g. by container_network)
    """
    os.chdir(self.tmp_work)
    self._setup_single_directory_for_compilation(self.directory)
    # Run any necessary pre_commands
    self._run_pre_commands(self.directory)

  def setup_for_execution_testcase(self, testcase_dependencies):
    """ 
    The default execution setup function just sets up this testcase's directory 
    for execution. This must be overridden if different directories are to be used (e.g. by container_network)
    """
    os.chdir(self.tmp_work)
    self._setup_single_directory_for_execution(self.directory, testcase_dependencies)
    self._run_pre_commands(self.directory)

  def setup_for_testcase_archival(self, overall_log):
    """ 
    The default execution setup function just sets up this testcase's directory 
    for archival. This must be overridden if different directories are being used (e.g. by container_network)
    """

    # Make the necessary details directories for archival.
    os.makedirs(os.path.join(self.tmp_results,"details"), exist_ok=True)
    os.makedirs(os.path.join(self.tmp_results,"results_public"), exist_ok=True)
    os.chdir(self.tmp_work)
    
    details_dir = os.path.join(self.tmp_results, "details", self.name)
    public_dir = os.path.join(self.tmp_results,"results_public", self.name)

    os.mkdir(details_dir)
    os.mkdir(public_dir)

    # Remove any test input files.
    test_input_path = os.path.join(self.tmp_work, 'test_input')
    autograding_utils.remove_test_input_files(overall_log, test_input_path, self.directory) 

    if os.path.exists(self.random_output_directory):
        autograding_utils.remove_test_input_files(overall_log, test_input_path, self.random_output_directory)


  def execute_random_input(self, untrusted_user, executable, arguments, logfile, cwd):
    """ 
    NOT IMPLEMENTED IN BASE CLASS: execute_random_input should be overridden to define
    how a given Secure Environment handles execution of the random input execution step.
    """
    raise NotImplementedError


  def execute_random_output(self, untrusted_user, executable, arguments, logfile, cwd):
    """ 
    NOT IMPLEMENTED IN BASE CLASS: execute_random_output should be overridden to define
    how a given Secure Environment handles execution of the random output generation step.
    """
    raise NotImplementedError


  def execute(self, untrusted_user, executable, arguments, logfile, cwd):
    """ 
    NOT IMPLEMENTED IN BASE CLASS: execute should be overridden to define
    how a given Secure Environment handles the execution step.
    """
    raise NotImplementedError


  def verify_execution_status(self):
    """ 
    A check to make certain this secure environment is being run in the
    environment it says it is.
    """

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
    """ A useful wrapper for the atuograding_utils.log_message function. """
    autograding_utils.log_message(self.log_path,
                                job_id = self.job_id,
                                is_batch = self.is_batch,
                                which_untrusted = self.untrusted_user,
                                message = message)


  """ A useful wrapper for the atuograding_utils.log_message function. """
  def log_stack_trace(self, trace):
    autograding_utils.log_stack_trace(self.stack_trace_log_path,
                                      job_id = self.job_id,
                                      is_batch = self.is_batch,
                                      which_untrusted = self.untrusted_user,
                                      trace = trace)

  """ A useful wrapper for the atuograding_utils.log_message function. """
  def log_container_meta(self, event, name='', container='', time=0):
    log_path = os.path.join(self.tmp_logs,'meta_log.txt')
    autograding_utils.log_container_meta(log_path, event, name, container, time)
