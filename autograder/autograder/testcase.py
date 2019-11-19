import json
import os
import shutil
"""
A Testcase contains a secure_execution_environment, which it uses to 
perform the various phases of autograding in a secure manner.
"""

import subprocess
import stat
import time
import traceback
import socket
from pwd import getpwnam
import glob

from submitty_utils import dateutils
from . import autograding_utils, CONFIG_PATH
from .execution_environments import jailed_sandbox, container_network


class Testcase():

  def __init__(self, number, queue_obj, complete_config_obj, testcase_info, untrusted_user,
               is_vcs, is_batch_job, job_id, autograding_directory, previous_testcases, submission_string, 
               log_path, stack_trace_log_path, is_test_environment):
    self.number = number
    self.queue_obj = queue_obj
    self.untrusted_user = untrusted_user
    self.testcase_directory = "test{:02}".format(number)
    self.type = testcase_info.get('type', 'Execution')
    self.machine = socket.gethostname()
    self.testcase_dependencies = previous_testcases.copy()
    self.submission_string = submission_string
    self.dependencies = previous_testcases

    # Create either a container network or a jailed sandbox based on autograding method.
    if complete_config_obj.get("autograding_method", "") == "docker":
      self.secure_environment = container_network.ContainerNetwork(job_id, untrusted_user, self.testcase_directory, is_vcs, is_batch_job, complete_config_obj, 
                                                           testcase_info, autograding_directory, log_path, stack_trace_log_path, is_test_environment)
    else:
      self.secure_environment = jailed_sandbox.JailedSandbox(job_id, untrusted_user, self.testcase_directory, is_vcs, is_batch_job, complete_config_obj, 
                                                             testcase_info, autograding_directory, log_path, stack_trace_log_path, is_test_environment)
    
    # Determine whether or not this testcase has an input generation phase.
    input_generation_commands = complete_config_obj["testcases"][number-1].get('input_generation_commands',None)
    if input_generation_commands is not None:
      self.has_input_generator_commands = True if len(input_generation_commands) > 0 else False
    else:
      self.has_input_generator_commands = False
    
    # Determine whether or not this testcase has an output generation phase.
    solution_containers = complete_config_obj["testcases"][number-1].get('solution_containers', None)
    if solution_containers is not None and len(solution_containers) > 0:
      self.has_solution_commands = True if len(solution_containers[0]['commands']) > 0 else False
    else:
      self.has_solution_commands = False

  def execute(self):
    """
    A wrapper function which executes either _run_compilation or _run_execution based on
    testcase type.
    """
    if self.type in ['Compilation', 'FileCheck']:
      self.secure_environment.log_container_meta("", "", "AUTOGRADING BEGIN", 0)  
      success = self._run_compilation()
    else:
      success = self._run_execution()
    
    if success == 0:
      print(self.machine, self.untrusted_user, f"{self.type.upper()} TESTCASE {self.number} OK")
    else:
      print(self.machine, self.untrusted_user, f"{self.type.upper()} TESTCASE {self.number} FAILURE")
      self.secure_environment.log_message(f"{self.type.upper()} TESTCASE {self.number} FAILURE")

  def _run_execution(self):
    """ Execute this testcase as an execution testcase. """

    # Create directories, set permissions, and copy in files.
    self.secure_environment.setup_for_execution_testcase(self.dependencies)
    
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
        # Execute this testcase using our secure environment.
        success = self.secure_environment.execute(self.untrusted_user, 'my_runner.out', arguments, logfile)
      except Exception as e:
        self.secure_environment.log_message("ERROR thrown by main runner. See traces entry for more details.")
        self.secure_environment.log_stack_trace(traceback.format_exc())
        success = -1
      finally:
        # Lock down permissions on our execution folder.
        self.secure_environment.lockdown_directory_after_execution()

      logfile.flush()

    return success
  
  def _run_compilation(self):
    """ Execute this testcase as a compilation testcase. """

    # Create directories, set permissions, and copy in files.
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
        # Execute this testcase using our secure environment.
        success = self.secure_environment.execute(self.untrusted_user, 'my_compile.out', arguments, logfile)
      except Exception as e:
        success = -1
        self.secure_environment.log_message("ERROR thrown by main compile. See traces entry for more details.")
        self.secure_environment.log_stack_trace(traceback.format_exc())
      finally:
        # Lock down permissions on our execution folder.
        self.secure_environment.lockdown_directory_after_execution()
      return success

  def generate_random_inputs(self):
    """ Generate random inputs for this testcase. """

    # If there is nothing to do, short circuit.
    if not self.has_input_generator_commands:
      return
    
    # Create directories, set permissions, and copy in files.
    self.secure_environment.setup_for_input_generation()
    with open(os.path.join(self.secure_environment.tmp_logs,"input_generator_log.txt"), 'a') as logfile:

      arguments = [
        self.queue_obj['gradeable'],
        self.queue_obj['who'],
        str(self.queue_obj['version']),
        self.submission_string,
        '--testcase', str(self.number),
        '--generation_type',str('input')
      ]
      
      try:
        # Generate input using our secure environment.
        success = self.secure_environment.execute_random_input(self.untrusted_user, 'my_runner.out', arguments, logfile, cwd=self.secure_environment.random_input_directory )
      except Exception as e:
        success = -1
        self.secure_environment.log_message("ERROR thrown by input generator. See traces entry for more details.")
        self.secure_environment.log_stack_trace(traceback.format_exc())
      finally:
        # Lock down permissions on our input generation folder.
        self.secure_environment.lockdown_directory_after_execution(self.secure_environment.random_input_directory)

      if success == 0:
        print(self.machine, self.untrusted_user, f"INPUT GENERATION TESTCASE {self.number} OK")
      else:
        print(self.machine, self.untrusted_user, f"INPUT GENERATION TESTCASE {self.number} FAILURE")
        self.secure_environment.log_message(f"INPUT GENERATION TESTCASE {self.number} FAILURE")
      return success

  def generate_random_outputs(self):
    """ Run an instructor solution to generate expected outputs for this testcase. """
    
    # If there is nothing to do, short circuit
    if not self.has_solution_commands:
      return

    # Create directories, set permissions, and copy in files.
    self.secure_environment.setup_for_random_output(self.dependencies)
    with open(os.path.join(self.secure_environment.tmp_logs,"output_generator_log.txt"), 'a') as logfile:
      arguments = [
        self.queue_obj["gradeable"],
        self.queue_obj["who"],
        str(self.queue_obj["version"]),
        self.submission_string,
        '--testcase', str(self.number),
        '--generation_type',str('output')
      ]
      
      try:
        # Generate random outputs for this testcase using our secure environment.
        success = self.secure_environment.execute_random_output(self.untrusted_user, 'my_runner.out', arguments, logfile, cwd=self.secure_environment.random_output_directory )
      except Exception as e:
        success = -1
        self.secure_environment.log_message("ERROR thrown by output generator. See traces entry for more details.")
        self.secure_environment.log_stack_trace(traceback.format_exc())
      finally:
        # Lock down permissions on our output generation folder.
        self.secure_environment.lockdown_directory_after_execution( self.secure_environment.random_output_directory )

      if success == 0:
        print(self.machine, self.untrusted_user, f"OUTPUT GENERATION TESTCASE {self.number} OK")
      else:
        print(self.machine, self.untrusted_user, f"OUTPUT GENERATION TESTCASE {self.number} FAILURE")
        self.secure_environment.log_message(f"OUTPUT GENERATION TESTCASE {self.number} FAILURE")
      return success

  def setup_for_archival(self, overall_log):
    """ Set up our testcase to be copied by the archival step."""
    self.secure_environment.setup_for_archival(overall_log)
