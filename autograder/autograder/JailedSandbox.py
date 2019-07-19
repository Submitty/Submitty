import json
import os
import subprocess
import traceback

from submitty_utils import dateutils
from . import autograding_utils

class JailedSandbox(SecureExecutionEnvironment):
  def __init__(testcase_directory, complete_config_obj, testcase_obj, autograding_directory, is_test_environment):
     super().__init__(testcase_directory, complete_config_obj, testcase_obj, autograding_directory, is_test_environment)   

  def archive_results(self, overall_log):
    """
    Archive the results of an execution and validation.
    """
    self.setup_for_archival()

    test_input_path = os.path.join(self.tmp_autograding, 'test_input_path')

    # Remove any files that are also in the test output folder
    remove_test_input_files(overall_log, test_input_path, self.directory)

    # Copy work to details
    os.mkdir(os.path.join(tmp_results,"details", self.directory))
    pattern_copy("work_to_details",self.patterns['work_to_details'], self.directory, os.path.join(tmp_results,"details", self.directory), self.tmp_logs)
    
    # Copy work to public
    os.mkdir(os.path.join(tmp_results,"results_public", self.directory))
    pattern_copy("work_to_public",self.patterns['work_to_public'], self.directory, os.path.join(tmp_results,"results_public", self.directory), self.tmp_logs)

  def execute(self, untrusted_user, script, arguments, logfile):
    
    try:
      self.verify_execution_status():
    except Exception as e:
      return

    if self.is_test_environment:
      script = [script, ]
    else:
      script = [os.path.join(SUBMITTY_INSTALL_DIR, "sbin", "untrusted_execute"), untrusted_user, script]


    try:
      success = subprocess.call(script
                                + 
                                arguments,
                                stdout=logfile,
                                cwd=self.directory)
    except Exception as e:
      self.my_testcase.log(message="ERROR. See traces entry for more details.")
      self.my_testcase.log_stack_trace(traceback.format_exc())
    os.remove(executable)
    return success
