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
from .execution_environments import jailed_sandbox


class Testcase():
  def __init__(self, number, name, queue_obj, complete_config_obj, testcase_info, untrusted_user,
               is_vcs, job_id, is_batch, autograding_directory, previous_testcases, submission_string, 
               log_path, stack_trace_log_path, is_test_environment):
    self.number = number
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
    self.log_path = log_path
    self.stack_trace_log_path = stack_trace_log_path
    # if complete_config_obj.get("autograding_method", "") == "docker":
    #   self.secure_environment = ContainerNetwork(self.parent_directory, self.testcase_directory, self.log, testcase_info)
    # else:
    self.secure_environment = jailed_sandbox.JailedSandbox(self.testcase_directory, complete_config_obj, testcase_info.get('pre_commands', list()), self, autograding_directory, is_test_environment)
    
  def _run_execution(self):
    self.secure_environment.setup_for_execution_testcase()
    
    with open(os.path.join(self.secure_environment.tmp_logs,"runner_log.txt"), 'a') as logfile:
      print ("LOGGING BEGIN my_runner.out",file=logfile)

      # Used in graphics gradeables
      display_sys_variable = os.environ.get('DISPLAY', None)
      display_line = [] if display_sys_variable is None else ['--display', str(display_sys_variable)]

      logfile.flush()
      arguments = [
        self.queue_obj["gradeable"],
        self.queue_obj["who"],
        str(self.queue_obj["version"]),
        self.submission_string,
        '--testcase', str(self.number)]
      arguments += display_line

      try:
        success = self.secure_environment.execute(self.untrusted_user, 'my_runner.out', arguments, logfile)
      except Exception as e:
        self.log_message("ERROR thrown by main runner. See traces entry for more details.")
        self.log_stack_trace(traceback.format_exc())
        success = -1
      finally:
        self.secure_environment.lockdown_directory_after_execution()

      logfile.flush()

    return success
  
  def _run_compilation(self):
    self.secure_environment.setup_for_compilation_testcase()
    with open(os.path.join(self.secure_environment.tmp_logs,"compilation_log.txt"), 'a') as logfile:
        arguments = [
          self.queue_obj['gradeable'],
          self.queue_obj['who'],
          str(self.queue_obj['version']),
          self.submission_string,
          '--testcase', str(self.number)
        ]
        
        try:
            success = self.secure_environment.execute(self.untrusted_user, 'my_compile.out', arguments, logfile)
        except Exception as e:
            success = -1
            self.log_message("ERROR thrown by main compile. See traces entry for more details.")
            self.log_stack_trace(traceback.format_exc())
        finally:
            self.secure_environment.lockdown_directory_after_execution()
        return success

  def execute(self):
    if self.type in ['Compilation', 'FileCheck']:
        success = self._run_compilation()
    else:
        success = self._run_execution()
    
    if success == 0:
      print(self.machine, self.untrusted_user, "{0} OK".format(self.type.upper()))
    else:
      print(self.machine, self.untrusted_user, "{0} FAILURE".format(self.type.upper()))
      self.log_message("{0} FAILURE".format(self.type.upper()))   

  def setup_for_archival(self, overall_log):
    self.secure_environment.setup_for_archival(overall_log)

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
