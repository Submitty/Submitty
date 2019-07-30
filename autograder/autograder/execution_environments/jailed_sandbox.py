import json
import os
import subprocess
import traceback

from submitty_utils import dateutils
from . import secure_execution_environment
from .. import autograding_utils

class JailedSandbox(secure_execution_environment.SecureExecutionEnvironment):
  def __init__(self, job_id, untrusted_user, testcase_directory, is_vcs, is_batch_job, complete_config_obj, 
               testcase_info, autograding_directory, log_path, stack_trace_log_path, is_test_environment):
     super().__init__(job_id, untrusted_user, testcase_directory, is_vcs, is_batch_job, complete_config_obj, 
                      testcase_info, autograding_directory, log_path, stack_trace_log_path, is_test_environment)

  def setup_for_archival(self, overall_log):
    """
    Archive the results of an execution and validation.
    """
    self.setup_for_testcase_archival()
    test_input_path = os.path.join(self.tmp_autograding, 'test_input_path')
    public_dir = os.path.join(self.tmp_results,"results_public", self.name)
    details_dir = os.path.join(self.tmp_results, "details", self.name)

    # Remove any files that are also in the test output folder
    autograding_utils.remove_test_input_files(overall_log, test_input_path, self.directory)    

  def execute(self, untrusted_user, script, arguments, logfile, cwd=None):

    if cwd is None:
      cwd = self.directory

    try:
      self.verify_execution_status()
    except Exception as e:
      self.log_stack_trace(traceback.format_exc())
      self.log("ERROR: Could not verify execution mode status.")
      return

    script = os.path.join(self.directory, script)
    if self.is_test_environment:
      full_script = [script, ]
    else:
      full_script = [os.path.join(self.SUBMITTY_INSTALL_DIR, "sbin", "untrusted_execute"), untrusted_user, script]

    success = False
    try:
      success = subprocess.call(full_script
                                + 
                                arguments,
                                stdout=logfile,
                                cwd=cwd)
    except Exception as e:
      self.log_message("ERROR. See traces entry for more details.")
      self.log_stack_trace(traceback.format_exc())

    try:
      os.remove(script)
    except Exception as e:
      self.log_message(f"ERROR. Could not remove {script}.")
      self.log_stack_trace(traceback.format_exc())
    
    return success
