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

class SecureExecutionEnvironment():
  def __init__(parent_directory, directory, pre_commands, testcase_obj):
    self.parent_directory = parent_directory
    self.directory = directory
    self.pre_commands = pre_commands
    self.testcase = testcase_obj

  def _run_pre_commands():
    for pre_command in self.pre_commands:
      command = pre_command['command']
      source_testcase   = pre_command["testcase"]
      source_directory  = pre_command['source']
      destination = pre_command['destination']

      if command == 'cp':
        try:
          pre_command_copy_file(source_testcase, source_directory, self.directory, destination, self.testcase.job_id, self.testcase.tmp_logs)
        except Exception as e:
          self.testcase.log(job_id, message="Encountered an error while processing pre-command. See traces entry for more details.")
          self.testcase.log_stack_trace(traceback.format_exc())
      else:
        self.testcase.log(job_id, message="Encountered an error while processing pre-command. See traces entry for more details.")
        print("Invalid pre-command '{0}'".format(command))

  def setup_for_compilation():
    raise NotImplementedError

  def setup_for_execution():
    raise NotImplementedError

  def setup_for_validation():
    raise NotImplementedError

  # Should start with a chdir and end with a chdir
  def execute(untrusted_user, executable, arguments, logfile, working_directory):
    raise NotImplementedError 

  # Update this to remove permissions
  def lock_down_permissions():
    raise NotImplementedError

class JailedSandbox(SecureExecutionEnvironment):
  def __init__(parent_directory, directory, pre_commands, testcase_obj):
     super().__init__(parent_directory, directory, pre_commands, testcase_obj)

  def setup_for_compilation_testcase():
    patterns_submission_to_compilation = self.testcase.patterns['submission_to_compilation']
    provided_code_path = os.path.join(tmp_autograding, "provided_code")
    bin_path = os.path.join(tmp_autograding, "bin")

    os.makedirs(self.directory)
    grade_item.pattern_copy("submission_to_compilation", patterns_submission_to_compilation, submission_path, self.directory, self.testcase.tmp_logs)

    if self.is_vcs:
      pattern_copy("checkout_to_compilation",patterns_submission_to_compilation,checkout_subdir_path,self.directory,tmp_logs)
    
    # copy any instructor provided code files to tmp compilation directory
    copy_contents_into(job_id,provided_code_path,self.directory,tmp_logs)
    # copy compile.out to the current directory
    shutil.copy (os.path.join(bin_path,"compile.out"),os.path.join(self.directory,"my_compile.out"))
    grade_item.add_permissions(os.path.join(self.directory,"my_compile.out"), stat.S_IXUSR | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)

    untrusted_grant_rwx_access(which_untrusted, self.directory)
    add_permissions_recursive(self.directory,
      stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
      stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
      stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)

  def setup_for_execution_testcase():
    # Make the testcase folder
    os.makedirs(self.directory)
    # Run any necessary pre_commands
    self._run_pre_commands()

    tmp_work_test_input = os.path.join(self.parent_directory, "test_input")
    tmp_work_submission = os.path.join(self.parent_directory, "submitted_files")
    tmp_work_compiled = os.path.join(self.parent_directory, "compiled_files")
    tmp_work_checkout = os.path.join(self.parent_directory, "checkout")
    my_runner = os.path.join(self.parent_directory,"my_runner.out")

    # Copy in necessary files
    grade_item.copy_contents_into(self.testcase.job_id,tmp_work_submission,self.directory,self.testcase.tmp_logs)
    grade_item.copy_contents_into(self.testcase.job_id,tmp_work_compiled  ,self.directory,self.testcase.tmp_logs)
    grade_item.copy_contents_into(self.testcase.job_id,tmp_work_checkout  ,self.directory,self.testcase.tmp_logs)
    grade_item.copy_contents_into(self.testcase.job_id,tmp_work_test_input,self.directory,self.testcase.tmp_logs)
    
    #copy the compiled runner to the test directory
    shutil.copy(my_runner,self.directory)

    # Add the correct permissions to the target folder.
    grade_item.add_permissions_recursive(self.directory,
                                      stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                                      stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                                      stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)

  def setup_for_validation():
    raise NotImplementedError

  def execute(untrusted_user, executable, arguments, logfile, working_directory):
    untrusted_execute = os.path.join(SUBMITTY_INSTALL_DIR, "sbin", "untrusted_execute")
    try:
      success = subprocess.call([untrusted_execute,
                                  untrusted_user,
                                  executable]
                                  + 
                                  arguments,
                                  stdout=logfile,
                                  cwd=working_directory)
    except Exception as e:
      self.testcase.log(message="ERROR. See traces entry for more details.")
      self.testcase.log_stack_trace(traceback.format_exc())
    return success

  

  def lock_down_permissions():
    pass


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
    self.patterns = testcase_info['autograding']
    self.is_vcs

    if complete_config_obj.get("autograding_method", "") == "docker":
      self.my_secure_environment = ContainerNetwork(self.parent_directory, self.testcase_directory, self.log, testcase_info)
    else:
      self.my_secure_environment = JailedSandbox(self.parent_directory, self.testcase_directory, self.log)


  def execute():
    # if my type is FileCheck or Compilation

    # Used in graphics gradeables
    display_sys_variable = os.environ.get('DISPLAY', None)
    display_line = [] if display_sys_variable is None else ['--display', str(display_sys_variable)]






def pre_command_copy_file(source_testcase, source_directory, destination_testcase, destination, job_id, tmp_logs):

  source_testcase = os.path.join(str(os.getcwd()), '..',source_testcase)
  destination_testcase = os.path.join(str(os.getcwd()), '..',destination_testcase)

  if not os.path.isdir(source_testcase):
    raise RuntimeError("ERROR: The directory {0} does not exist.".format(source_testcase))

  if not os.path.isdir(destination_testcase):
    raise RuntimeError("ERROR: The directory {0} does not exist.".format(destination_testcase))

  source = os.path.join(source_testcase, source_directory)
  target = os.path.join(destination_testcase,destination)

  #the target without the potential executable.
  target_base = '/'.join(target.split('/')[:-1])

  #If the source is a directory, we copy the entire thing into the
  # target.
  if os.path.isdir(source):
    #We must copy from directory to directory 
    grade_item.copy_contents_into(job_id,source,target,tmp_logs)

  # Separate ** and * for simplicity.
  elif not '**' in source:
    #Grab all of the files that match the pattern
    files = glob.glob(source, recursive=True)
    
    #The target base must exist in order for a copy to occur
    if target_base != '' and not os.path.isdir(target_base):
      raise RuntimeError("ERROR: The directory {0} does not exist.".format(target_base))
    #Copy every file. This works whether target exists (is a directory) or does not (is a target file)
    for file in files:
      try:
        shutil.copy(file, target)
      except Exception as e:
        traceback.print_exc()
        grade_items_logging.log_message(job_id, message="Pre Command could not perform copy: {0} -> {1}".format(file, target))

  else:
    #Everything after the first **. 
    source_base = source[:source.find('**')]
    #The full target must exist (we must be moving to a directory.)
    if not os.path.isdir(target):
      raise RuntimeError("ERROR: The directory {0} does not exist.".format(target))

    #Grab all of the files that match the pattern.
    files = glob.glob(source, recursive=True)


    #For every file matched
    for file_source in files:
      file_target = os.path.join(target, file_source.replace(source_base,''))
      #Remove the file path.
      file_target_dir = '/'.join(file_target.split('/')[:-1])
      #If the target directory doesn't exist, create it.
      if not os.path.isdir(file_target_dir):
        os.makedirs(file_target_dir)
      #Copy.
      try:
        shutil.copy(file_source, file_target)
      except Exception as e:
        traceback.print_exc()
        grade_items_logging.log_message(job_id, message="Pre Command could not perform copy: {0} -> {1}".format(file_source, file_target))

