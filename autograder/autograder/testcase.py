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
from . import autograding_utils, CONFIG_PATH, JailedSandbox

with open(os.path.join(CONFIG_PATH, 'submitty.json')) as open_file:
    OPEN_JSON = json.load(open_file)
    SUBMITTY_INSTALL_DIR = OPEN_JSON['submitty_install_dir']

class Testcase():
  def __init__(name, queue_obj, complete_config_obj, testcase_info, parent_directory, untrusted_user,
               is_vcs, job_id, is_batch, autograding_directory, previous_testcases, submission_string, 
               untrusted_execute_path):
    self.name = name
    self.queue_obj = queue_obj
    self.untrusted_user = untrusted_user
    self.testcase_directory = name
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
    self.secure_environment = JailedSandbox.JailedSandbox(self.testcase_directory, complete_config_obj, self, autograding_directory, untrusted_execute_path)
    
  
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
        autograding_utils.log_message(job_id, message="ERROR thrown by main runner. See traces entry for more details.")
        autograding_utils.log_stack_trace(job_id,trace=traceback.format_exc())
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
    autograding_utils.log_message(job_id = self.job_id,
                                    is_batch = self.is_batch,
                                    which_untrusted = self.untrusted_user,
                                    message = message)

  def log_stack_trace(self, trace):
    autograding_utils.log_stack_trace(job_id = self.job_id,
                                        is_batch = self.is_batch,
                                        which_untrusted = self.untrusted_user,
                                        trace = trace)
