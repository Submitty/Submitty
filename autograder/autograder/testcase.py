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
from . import grade_item, grade_items_logging, CONFIG_PATH

with open(os.path.join(CONFIG_PATH, 'submitty.json')) as open_file:
    OPEN_JSON = json.load(open_file)
    SUBMITTY_INSTALL_DIR = OPEN_JSON['submitty_install_dir']
    SUBMITTY_DATA_DIR = OPEN_JSON['submitty_data_dir']

with open(os.path.join(CONFIG_PATH, 'submitty_users.json')) as open_file:
    OPEN_JSON = json.load(open_file)
    DAEMON_UID = OPEN_JSON['daemon_uid']

class SecureExecutionEnvironment():
  def __init__(directory, pre_commands, testcase_obj):
    self.directory = directory
    self.pre_commands = pre_commands
    self.my_testcase = testcase_obj

  def _run_pre_commands(self):
    for pre_command in self.pre_commands:
      command = pre_command['command']
      source_testcase   = pre_command["testcase"]
      source_directory  = pre_command['source']
      destination = pre_command['destination']

      if command == 'cp':
        try:
          pre_command_copy_file(source_testcase, source_directory, self.directory, destination, self.my_testcase.job_id, my_testcase.testcase.tmp_logs)
        except Exception as e:
          self.my_testcase.log(job_id, message="Encountered an error while processing pre-command. See traces entry for more details.")
          self.my_testcase.log_stack_trace(traceback.format_exc())
      else:
        self.my_testcase.log(job_id, message="Encountered an error while processing pre-command. See traces entry for more details.")
        print("Invalid pre-command '{0}'".format(command))

  def _setup_single_directory_for_compilation(self, directory):
    provided_code_path = os.path.join(self.my_testcase.tmp_autograding, 'provided_code')
    bin_path = os.path.join(self.my_testcase.tmp_autograding, 'bin')
    submission_path = os.path.join(self.my_testcase.tmp_submission, 'submission')

    os.makedirs(self.directory)
    grade_item.pattern_copy("submission_to_compilation", self.my_testcase.patterns['submission_to_compilation'], submission_path, self.directory, self.my_testcase.tmp_logs)

    if self.is_vcs:
      checkout_subdir_path = os.path.join(self.my_testcase.tmp_submission, 'checkout', self.my_testcase.checkout_subdirectory)
      pattern_copy("checkout_to_compilation",self.my_testcase.patterns['checkout_to_compilation'],checkout_subdir_path,self.directory,tmp_logs)
    
    # copy any instructor provided code files to tmp compilation directory
    copy_contents_into(job_id,provided_code_path,self.directory,self.my_testcase.tmp_logs)
    # copy compile.out to the current directory
    shutil.copy (os.path.join(bin_path,"compile.out"),os.path.join(self.directory,"my_compile.out"))
    grade_item.add_permissions(os.path.join(self.directory,"my_compile.out"), stat.S_IXUSR | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)

    untrusted_grant_rwx_access(which_untrusted, self.directory)
    grade_item.add_permissions_recursive(self.directory,
      stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
      stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
      stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)

    grade_item.untrusted_grant_rwx_access(which_untrusted, tmp_work)          

  def _setup_single_directory_for_execution(self, directory):
    # Make the testcase folder
    os.makedirs(self.directory)

    # Patterns
    submission_path = os.path.join(self.my_testcase.tmp_submission, 'submission')
    checkout_path = os.path.join(submission_path, "checkout")

    grade_item.pattern_copy("submission_to_runner",self.patterns["submission_to_runner"], 
                            submission_path, self.directory, self.my_testcase.tmp_logs)

    if self.is_vcs:
        grade_item.pattern_copy("checkout_to_runner",self.patterns["checkout_to_runner"], 
                            checkout_path, self.directory, self.my_testcase.tmp_logs)

    for c in self.testcase_dependencies:
        if c.is_compilation():
            pattern_copy("compilation_to_runner", self.patterns['compilation_to_runner'], 
                         c.secure_environment.directory, self.directory, self.my_testcase.tmp_logs)

    # copy input files
    test_input_path = os.path.join(self.my_testcase.tmp_autograding, 'test_input_path')
    copy_contents_into(self.my_testcase.job_id, test_input_path, self.directory, self.my_testcase.tmp_logs)

    # copy runner.out to the current directory
    bin_runner = os.path.join(self.my_testcase.tmp_autograding, "bin","run.out")
    my_runner  = os.path.join(self.directory, "my_runner.out")
    
    shutil.copy(bin_runner, my_runner)

    add_permissions(my_runner, stat.S_IXUSR | stat.S_IXGRP |stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)

    # Add the correct permissions to the target folder.
    grade_item.add_permissions_recursive(self.directory,
                                      stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                                      stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                                      stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)

  def _setup_single_directory_for_validation(self):
    add_permissions_recursive(self.my_testcase.tmp_work,
                            stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                            stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                            stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH) 

    submission_path = os.path.join(self.my_testcase.tmp_submission, 'submission')

    # copy results files from compilation...
    pattern_copy("submission_to_validation", self.patterns['submission_to_validation'],
                 submission_path, self.directory, self.my_testcase.tmp_logs)
    if self.is_vcs:
        checkout_subdir_path = os.path.join(self.my_testcase.tmp_submission, 'checkout', self.my_testcase.checkout_subdirectory)
        pattern_copy("checkout_to_validation",self.patterns['submission_to_validation'],checkout_subdir_path,tmp_work,tmp_logs)
    
    pattern_copy("compilation_to_validation", self.patterns['compilation_to_validation'], 
                 self.my_testcase.tmp_compilation, self.directory, self.my_testcase.tmp_logs)

    # copy output files
    test_output_path = os.path.join(self.my_testcase.tmp_autograding, 'test_output')
    copy_contents_into(self.my_testcase.job_id, test_output_path, self.directory, self.my_testcase.tmp_logs)

    # copy any instructor instructor_solution code into the tmp work directory
    instructor_solution = os.path.join(self.my_testcase.tmp_autograding, 'instructor_solution')
    copy_contents_into(self.my_testcase.job_id, instructor_solution, self.directory, self.my_testcase.tmp_logs)

    # copy any instructor custom validation code into the tmp work directory
    custom_validation_code_path = os.path.join(self.my_testcase.tmp_autograding, 'custom_validation_code')
    copy_contents_into(self.my_testcase.job_id, custom_validation_code_path, self.directory, self.my_testcase.tmp_logs)

    add_permissions_recursive(self.my_testcase.tmp_work,
                              stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                              stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                              stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)


    # copy the runner
    bin_runner = os.path.join(self.my_testcase.tmp_autograding, "bin","validate.out")
    my_runner  = os.path.join(self.directory, "my_validator.out")
    
    shutil.copy(bin_runner, my_runner)

    add_permissions(my_runner, stat.S_IXUSR | stat.S_IXGRP |stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)

  def setup_for_compilation(self):
    os.chdir(self.testcase.tmp_work)
    self._setup_single_directory_for_compilation(self.directory)
    # Run any necessary pre_commands
    self._run_pre_commands()

  def setup_for_execution(self):
    os.chdir(self.testcase.tmp_work)
    self._setup_single_directory_for_execution(self.directory)
    self._run_pre_commands()

  def setup_for_validation(self):
    self._setup_single_directory_for_validation(self.directory)
    os.chdir(self.testcase.tmp_work)

  def setup_for_archival(self):
    os.makedirs(os.path.join(self.testcase.tmp_results,"details"), exist_ok=True)
    os.makedirs(os.path.join(self.testcase.tmp_results,"results_public"), exist_ok=True)
    os.chdir(self.testcase.tmp)

  def archive_results(self, overall_log):
    raise NotImplementedError

  # Should start with a chdir and end with a chdir
  def execute(self, untrusted_user, executable, arguments, logfile):
    raise NotImplementedError

class JailedSandbox(SecureExecutionEnvironment):
  def __init__(directory, pre_commands, testcase_obj):
     super().__init__(directory, pre_commands, testcase_obj)   

  def archive_results(self, overall_log):
    """
    Archive the results of an execution and validation.
    """
    self.setup_for_archival()
    untrusted_grant_rwx_access(which_untrusted, self.directory)

    test_input_path = os.path.join(self.my_testcase.tmp_autograding, 'test_input_path')

    # Remove any files that are also in the test output folder
    remove_test_input_files(overall_log, test_input_path, self.directory)

    # Copy work to details
    os.mkdir(os.path.join(tmp_results,"details", self.directory))
    pattern_copy("work_to_details",self.patterns['work_to_details'], self.directory, os.path.join(tmp_results,"details", self.directory), self.my_testcase.tmp_logs)
    
    # Copy work to public
    os.mkdir(os.path.join(tmp_results,"results_public", self.directory))
    pattern_copy("work_to_public",self.patterns['work_to_public'], self.directory, os.path.join(tmp_results,"results_public", self.directory), self.my_testcase.tmp_logs)

  def execute(self, untrusted_user, executable, arguments, logfile):
    untrusted_execute = os.path.join(SUBMITTY_INSTALL_DIR, "sbin", "untrusted_execute")
    try:
      success = subprocess.call([untrusted_execute,
                                  untrusted_user,
                                  executable]
                                  + 
                                  arguments,
                                  stdout=logfile,
                                  cwd=self.directory)
    except Exception as e:
      self.my_testcase.log(message="ERROR. See traces entry for more details.")
      self.my_testcase.log_stack_trace(traceback.format_exc())
    os.remove(executable)
    return success

class Testcase():
  def __init__(name, queue_obj, complete_config_obj, testcase_info, parent_directory, untrusted_user,
               is_vcs, job_id, is_batch, tmp, previous_testcases, submission_string):
    self.name = name
    self.queue_obj = queue_obj
    self.untrusted_user = untrusted_user
    self.testcase_directory = name
    self.patterns = complete_config_obj['autograding']
    self.type = testcase_info.get('type', 'Execution')
    self.is_vcs = is_vcs
    self.job_id = job_id
    self.is_batch = is_batch
    self.machine = socket.gethostname()
    self.testcase_dependencies = previous_testcases.copy()
    self.submission_string = submission_string

    # if complete_config_obj.get("autograding_method", "") == "docker":
    #   self.secure_environment = ContainerNetwork(self.parent_directory, self.testcase_directory, self.log, testcase_info)
    # else:
    self.secure_environment = JailedSandbox(self.parent_directory, self.testcase_directory, self.log)

    self.tmp = tmp
    self.tmp_work = os.path.join(tmp, 'TMP_WORK')
    self.tmp_autograding = os.path.join(tmp, 'TMP_AUTOGRADING')
    self.tmp_submission = os.path.join(tmp, 'TMP_SUBMISSION')
    self.tmp_logs = os.path.join(tmp,"TMP_SUBMISSION","tmp_logs")
    self.checkout_path = os.path.join(self.tmp_submission, "checkout")
    self.checkout_subdirectory = complete_config_obj["autograding"].get("use_checkout_subdirectory","")
  
  def _run_execution(self):
    self.secure_environment.setup_for_execution_testcase()
    
    with open(os.path.join(tmp_logs,"runner_log.txt"), 'a') as logfile:
      print ("LOGGING BEGIN my_runner.out",file=logfile)

      # Used in graphics gradeables
      display_sys_variable = os.environ.get('DISPLAY', None)
      display_line = [] if display_sys_variable is None else ['--display', str(display_sys_variable)]

      logfile.flush()
      arguments = [
        queue_obj["gradeable"],
        queue_obj["who"],
        str(queue_obj["version"]),
        submission_string,
        '--testcase', str(testcase_num)]
      arguments += display_line

      try:
        success = self.secure_environment.execute(self.untrusted_user, 'my_runner.out', arguments, logfile)
      except Exception as e:
        grade_items_logging.log_message(job_id, message="ERROR thrown by main runner. See traces entry for more details.")
        grade_items_logging.log_stack_trace(job_id,trace=traceback.format_exc())
        success = -1

      killall_success = subprocess.call([os.path.join(SUBMITTY_INSTALL_DIR, "sbin", "untrusted_execute"),
                                             which_untrusted,
                                             os.path.join(SUBMITTY_INSTALL_DIR, "sbin", "killall.py")],
                                            stdout=logfile)
      
      print ("LOGGING END my_runner.out",file=logfile)
      if killall_success != 0:
        self.log_message('RUNNER ERROR: had to kill {} process(es)'.format(killall_success))
      else:
        print ("KILLALL COMPLETE my_runner.out",file=logfile)
      logfile.flush()

    return success
  
  def _run_compilation(self):
    self.secure_environment.setup_for_compilation_testcase()
    with open(os.path.join(self.tmp_logs,"compilation_log.txt"), 'a') as logfile:
        arguments = [
          self.queue_obj['gradeable'],
          self.queue_obj['who'],
          str(self.queue_obj['version']),
          self.submission_string,
          '--testcase', str(name)
        ]
        return self.secure_environment.execute(self.untrusted_user, 'my_compile.out', arguments, logfile)

  def execute(self):
    if self.type in ['Compilation', 'FileCheck']:
        succes = self._run_compilation()
    else:
        success = self._run_execution()
    
    if success == 0:
      print(self.machine, self.untrusted_user, "{0} OK".format(self.type.upper()))
    else:
      print(self.machine, self.untrusted_user, "{0} FAILURE".format(self.type.upper()))
      self.log_message(job_id,is_batch_job,which_untrusted,item_name,message="{0} FAILURE".format(self.type.upper()))

  def validate(self):
    with open(os.path.join(tmp_logs,"validator_log.txt"), 'w') as logfile:
      arguments = [self.queue_obj["gradeable"],
                   self.queue_obj["who"],
                   str(self.queue_obj["version"]),
                   self.submission_string]
      success = self.secure_environment.execute(self.untrusted_user, 'my_validator.out', arguments, logfile)

      if success == 0:
          print (self.machine, self.untrusted_user,"VALIDATOR OK")
      else:
          print (self.which_machine, self.which_untrusted,"VALIDATOR FAILURE")
          self.log_message(message="VALIDATION FAILURE")
    return success

  def log_message(self, message):
    grade_items_logging.log_message(job_id = self.job_id,
                                    is_batch = self.is_batch,
                                    which_untrusted = self.untrusted_user,
                                    message = message)

  def log_stack_trace(self, trace):
    grade_items_logging.log_stack_trace(job_id = self.job_id,
                                        is_batch = self.is_batch,
                                        which_untrusted = self.untrusted_user,
                                        trace = trace)


def pre_command_copy_file(source_testcase, source_directory, destination_testcase, destination, job_id, tmp_logs):

  source_testcase = os.path.join(str(os.getcwd()), '..', source_testcase)
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

# go through the testcase folder (e.g. test01/) and remove anything
# that matches the test input (avoid archiving copies of these files!)
def remove_test_input_files(overall_log,testcase_folder):
    test_input_path = os.path.join(tmp_autograding, "test_input")
    for path, subdirs, files in os.walk(test_input_path):
        for name in files:
            relative = path[len(test_input_path)+1:]
            my_file = os.path.join(testcase_folder, relative, name)
            if os.path.isfile(my_file):
                print ("removing (likely) stale test_input file: ", my_file, file=overall_log)
                os.remove(my_file)