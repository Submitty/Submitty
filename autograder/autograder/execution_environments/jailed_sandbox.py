import os
import subprocess
import traceback

from . import secure_execution_environment


class JailedSandbox(secure_execution_environment.SecureExecutionEnvironment):
    """
    A Jailed Sandbox ensures a secure execution environment by carefully permissioning
    files during each phase of execution, and by running all execution steps as a limited-access,
    untrusted user.
    """

    def __init__(self, config, job_id, untrusted_user, testcase_directory, is_vcs, is_batch_job,
                 complete_config_obj, testcase_info, autograding_directory, log_path,
                 stack_trace_log_path, is_test_environment):
        super().__init__(config, job_id, untrusted_user, testcase_directory, is_vcs, is_batch_job,
                         complete_config_obj, testcase_info, autograding_directory, log_path,
                         stack_trace_log_path, is_test_environment)

    def setup_for_archival(self, overall_log):
        """
        Archive the results of an execution and validation.
        """
        self.setup_for_testcase_archival(overall_log)

    def execute_random_input(self, untrusted_user, script, arguments, logfile, cwd=None):
        """
        Given the correct script arguments, Jailed Sandbox is able to simple run the standard
        execute command from the random input directory to generate random input.
        """
        return self.execute(
            untrusted_user,
            script,
            arguments,
            logfile,
            cwd=self.random_input_directory
        )

    def execute_random_output(self, untrusted_user, script, arguments, logfile, cwd=None):
        """
        Given the correct script arguments, Jailed Sandbox is able to simple run the standard
        execute command from the random output directory to generate random output.
        """
        return self.execute(
            untrusted_user,
            script,
            arguments,
            logfile,
            cwd=self.random_output_directory
        )

    def execute(self, untrusted_user, script, arguments, logfile, cwd=None):
        """
        Given an untrusted user, a script, arguments, and a permissioned
        working directory, execute the script with the arguments as the user
        in the directory.
        """

        if cwd is None:
            cwd = self.directory

        try:
            # Make certain we are in the execution mode that we say we are
            # (i.e. we aren't accidentally running in production mode in a
            # test environment or vice versa.)
            self.verify_execution_status()
        except Exception:
            self.log_stack_trace(traceback.format_exc())
            self.log_message("ERROR: Could not verify execution mode status.")
            return

        script = os.path.join(cwd, script)
        # If we are in a test environment, don't bother with untrusted execute.
        if self.is_test_environment:
            full_script = [script, ]
        else:
            full_script = [
                os.path.join(
                    self.SUBMITTY_INSTALL_DIR,
                    "sbin",
                    "untrusted_execute"
                ),
                untrusted_user,
                script
            ]

        success = False
        try:
            success = subprocess.call(
                full_script + arguments,
                stdout=logfile,
                cwd=cwd
            )
        except Exception:
            self.log_message("ERROR. See traces entry for more details.")
            self.log_stack_trace(traceback.format_exc())

        try:
            os.remove(script)
        except Exception:
            self.log_message(f"ERROR. Could not remove {script}.")
            self.log_stack_trace(traceback.format_exc())

        return success
