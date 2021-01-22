import os
import traceback
import socket
import json

from .execution_environments import jailed_sandbox, container_network


autograding_worker_json = "/var/local/submitty/autograding_TODO/autograding_worker.json"


class Testcase():
    """
    A Testcase contains a secure_execution_environment, which it uses to
    perform the various phases of autograding in a secure manner.
    """
    def __init__(self, config, testcase_id, queue_obj, complete_config_obj, testcase_info,
                 untrusted_user, is_vcs, is_batch_job, job_id, autograding_directory,
                 previous_testcases, submission_string, log_path, stack_trace_log_path,
                 is_test_environment):
        self.testcase_id = testcase_id
        self.queue_obj = queue_obj
        self.untrusted_user = untrusted_user
        self.testcase_directory = testcase_id
        self.type = testcase_info.get('type', 'Execution')
        self.machine = socket.gethostname()
        self.testcase_dependencies = previous_testcases.copy()
        self.submission_string = submission_string
        self.dependencies = previous_testcases

        # Create either a container network or a jailed sandbox based on autograding method.
        if complete_config_obj.get("autograding_method", "") == "docker":
            self.secure_environment = container_network.ContainerNetwork(
                config,
                job_id,
                untrusted_user,
                self.testcase_directory,
                is_vcs,
                is_batch_job,
                complete_config_obj,
                testcase_info,
                autograding_directory,
                log_path,
                stack_trace_log_path,
                is_test_environment
            )
        else:
            self.secure_environment = jailed_sandbox.JailedSandbox(
                config,
                job_id,
                untrusted_user,
                self.testcase_directory,
                is_vcs,
                is_batch_job,
                complete_config_obj,
                testcase_info,
                autograding_directory,
                log_path,
                stack_trace_log_path,
                is_test_environment
            )

        # Determine whether or not this testcase has an input generation phase.
        gen_cmds = testcase_info.get('input_generation_commands', None)
        if gen_cmds is not None:
            self.has_input_generator_commands = len(gen_cmds) > 0
        else:
            self.has_input_generator_commands = False

        # Determine whether or not this testcase has an output generation phase.
        solution_containers = testcase_info.get(
            'solution_containers',
            None
        )

        if solution_containers is not None and len(solution_containers) > 0:
            self.has_solution_commands = len(solution_containers[0]['commands']) > 0
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

        test_id = f'{self.type.upper()} TESTCASE {self.testcase_id}'
        if success == 0:
            print(self.machine, self.untrusted_user, f"{test_id} OK")
        else:
            print(self.machine, self.untrusted_user, f"{test_id} FAILURE")
            self.secure_environment.log_message(f"{test_id} FAILURE")

    def _run_execution(self):
        """ Execute this testcase as an execution testcase. """

        # Create directories, set permissions, and copy in files.
        self.secure_environment.setup_for_execution_testcase(self.dependencies)

        with open(os.path.join(self.secure_environment.tmp_logs, "runner_log.txt"), 'a') as logfile:
            print("LOGGING BEGIN my_runner.out", file=logfile)

            # Used in graphics gradeables
            display_sys_variable = ""
            with open(autograding_worker_json) as f:
                data = json.load(f)
                for machine in data:
                    if "display_environment_variable" in data[machine]:
                        display_sys_variable = data[machine]["display_environment_variable"]
            display_line = [] if display_sys_variable == "" else ['--display',
                                                                  str(display_sys_variable)]

            logfile.flush()
            arguments = [
                self.queue_obj["gradeable"],
                self.queue_obj["who"],
                str(self.queue_obj["version"]),
                self.submission_string,
                str(self.testcase_id)
            ]
            arguments += display_line

            try:
                # Execute this testcase using our secure environment.
                success = self.secure_environment.execute(self.untrusted_user, 'my_runner.out',
                                                          arguments, logfile)
            except Exception:
                self.secure_environment.log_message("ERROR thrown by main runner. See traces entry"
                                                    " for more details.")
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
        comp_log_directory = os.path.join(self.secure_environment.tmp_logs, "compilation_log.txt")
        with open(comp_log_directory, 'a') as logfile:
            arguments = [
                self.queue_obj['gradeable'],
                self.queue_obj['who'],
                str(self.queue_obj['version']),
                self.submission_string,
                str(self.testcase_id)
            ]

            try:
                # Execute this testcase using our secure environment.
                success = self.secure_environment.execute(
                    self.untrusted_user,
                    'my_compile.out',
                    arguments,
                    logfile
                )
            except Exception:
                success = -1
                self.secure_environment.log_message("ERROR thrown by main compile. See traces entry"
                                                    " for more details.")
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
        log_directory = os.path.join(self.secure_environment.tmp_logs, "input_generator_log.txt")
        with open(log_directory, 'a') as logfile:

            arguments = [
                self.queue_obj['gradeable'],
                self.queue_obj['who'],
                str(self.queue_obj['version']),
                self.submission_string,
                str(self.testcase_id),
                '--generation_type', str('input')
            ]

            try:
                # Generate input using our secure environment.
                success = self.secure_environment.execute_random_input(
                    self.untrusted_user,
                    'my_runner.out',
                    arguments,
                    logfile,
                    cwd=self.secure_environment.random_input_directory
                )
            except Exception:
                success = -1
                self.secure_environment.log_message("ERROR thrown by input generator. See traces "
                                                    "entry for more details.")
                self.secure_environment.log_stack_trace(traceback.format_exc())
            finally:
                # Lock down permissions on our input generation folder.
                self.secure_environment.lockdown_directory_after_execution(
                    self.secure_environment.random_input_directory
                )

            t_name = f"INPUT GENERATION TESTCASE {self.testcase_id}"
            if success == 0:
                print(self.machine, self.untrusted_user, f"{t_name} OK")
            else:
                print(self.machine, self.untrusted_user, f"{t_name} FAILURE")
                self.secure_environment.log_message(f"{t_name} FAILURE")
        return success

    def generate_random_outputs(self):
        """ Run an instructor solution to generate expected outputs for this testcase. """

        # If there is nothing to do, short circuit
        if not self.has_solution_commands:
            return

        if "generate_output" not in self.queue_obj:
            # If there is no random inputs then it will take from generated_output
            if not self.has_input_generator_commands:
                return

        # Create directories, set permissions, and copy in files.
        self.secure_environment.setup_for_random_output(self.dependencies)
        log_dir = os.path.join(self.secure_environment.tmp_logs, "output_generator_log.txt")
        with open(log_dir, 'a') as logfile:
            if "generate_output" not in self.queue_obj:
                arguments = [
                    self.queue_obj["gradeable"],
                    self.queue_obj["who"],
                    str(self.queue_obj["version"]),
                    self.submission_string,
                    str(self.testcase_id),
                    '--generation_type', str('output')
                ]
            elif self.queue_obj['generate_output']:
                arguments = [
                    self.queue_obj["gradeable"],
                    'Generating Output',
                    '0',
                    '',
                    str(self.testcase_id),
                    '--generation_type', str('output')
                ]

            try:
                # Generate random outputs for this testcase using our secure environment.
                success = self.secure_environment.execute_random_output(
                    self.untrusted_user,
                    'my_runner.out',
                    arguments,
                    logfile,
                    cwd=self.secure_environment.random_output_directory
                )
            except Exception:
                success = -1
                self.secure_environment.log_message("ERROR thrown by output generator. "
                                                    "See traces entry for more details.")
                self.secure_environment.log_stack_trace(traceback.format_exc())
            finally:
                # Lock down permissions on our output generation folder.
                self.secure_environment.lockdown_directory_after_execution(
                    self.secure_environment.random_output_directory
                )

            t_name = f"OUTPUT GENERATION TESTCASE {self.testcase_id}"
            if success == 0:
                print(self.machine, self.untrusted_user, f"{t_name} OK")
            else:
                print(self.machine, self.untrusted_user, f"{t_name} FAILURE")
                self.secure_environment.log_message(f"{t_name} FAILURE")
        return success

    def setup_for_archival(self, overall_log):
        """ Set up our testcase to be copied by the archival step."""
        self.secure_environment.setup_for_archival(overall_log)
