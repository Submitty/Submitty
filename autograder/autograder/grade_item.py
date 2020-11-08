import json
import os
import tempfile
import shutil
import subprocess
import socket
import traceback

from . import autograding_utils, testcase
from . execution_environments import jailed_sandbox


def grade_from_zip(
    config,
    working_directory,
    which_untrusted,
    autograding_zip_file,
    submission_zip_file
):

    os.chdir(config.submitty['submitty_data_dir'])

    # A useful shorthand for a long variable name
    install_dir = config.submitty['submitty_install_dir']

    # Removes the working directory if it exists, creates subdirectories and unzips files.
    autograding_utils.prepare_directory_for_autograding(
        config,
        working_directory,
        which_untrusted,
        autograding_zip_file,
        submission_zip_file,
        False,
    )

    # Now that the files are unzipped, we no longer need them.
    os.remove(autograding_zip_file)
    os.remove(submission_zip_file)

    # Initialize variables needed for autograding.
    tmp_autograding = os.path.join(working_directory, "TMP_AUTOGRADING")
    tmp_submission = os.path.join(working_directory, "TMP_SUBMISSION")
    tmp_work = os.path.join(working_directory, "TMP_WORK")
    tmp_logs = os.path.join(working_directory, "TMP_SUBMISSION", "tmp_logs")
    tmp_results = os.path.join(working_directory, "TMP_RESULTS")

    # Used to separate sections of printed messages
    seperator = "====================================\n"

    # Open the JSON and timestamp files needed to grade. Initialize needed variables.
    with open(os.path.join(tmp_submission, "queue_file.json"), 'r') as infile:
        queue_obj = json.load(infile)
    waittime = queue_obj["waittime"]
    is_batch_job = queue_obj["regrade"]
    job_id = queue_obj["job_id"]

    with open(os.path.join(tmp_autograding, "complete_config.json"), 'r') as infile:
        complete_config_obj = json.load(infile)

    if "generate_output" in queue_obj and queue_obj["generate_output"]:
        item_name = os.path.join(
            queue_obj["semester"],
            queue_obj["course"],
            "generated_output",
            queue_obj["gradeable"]
        )
        config.logger.log_message(
            "Generating Output",
            job_id=job_id,
            is_batch=is_batch_job,
            which_untrusted=which_untrusted,
            jobname=item_name,
            timelabel="wait:",
            elapsed_time=waittime
        )

        with open(os.path.join(tmp_autograding, "form.json"), 'r') as infile:
            gradeable_config_obj = json.load(infile)

        testcases = list()
        testcase_num = 1
        for t in complete_config_obj['testcases']:
            tmp_test = testcase.Testcase(
                config,
                testcase_num,
                queue_obj,
                complete_config_obj,
                t,
                which_untrusted,
                False,
                is_batch_job,
                job_id,
                working_directory,
                testcases,
                '',
                config.log_path,
                config.error_path,
                is_test_environment=False
            )

            if tmp_test.has_solution_commands and not tmp_test.has_input_generator_commands:
                testcases.append(tmp_test)
            testcase_num += 1

        with open(os.path.join(tmp_logs, "overall.txt"), 'a') as overall_log:
            os.chdir(tmp_work)
            print(f"{seperator}OUTPUT GENERATION STARTS", file=overall_log)
            for tc in testcases:
                if tc.has_solution_commands:
                    tc.generate_random_outputs()

                    # Killalll removes any stray processes left over from output generation.
                    killall_success = subprocess.call(
                        [
                            os.path.join(install_dir, "sbin", "untrusted_execute"),
                            which_untrusted,
                            os.path.join(install_dir, "sbin", "killall.py")
                        ],
                        stdout=overall_log
                    )

                    if killall_success != 0:
                        print(
                            f'INPUT GENERATION ERROR: had to kill {killall_success} process(es)',
                            file=overall_log
                        )
                    else:
                        print("KILLALL COMPLETE INPUT GENERATION", file=overall_log)
                    overall_log.flush()

            subprocess.call(['ls', '-lR', '.'], stdout=overall_log)
            overall_log.flush()

            print(f"{seperator}ARCHIVING STARTS", file=overall_log)
            overall_log.flush()
            for tc in testcases:
                # Removes test input files, makes details directory for the testcase.
                tc.setup_for_archival(overall_log)

            try:
                # Perform archival.
                autograding_utils.archive_autograding_results(
                    config,
                    working_directory,
                    job_id,
                    which_untrusted,
                    is_batch_job,
                    complete_config_obj,
                    gradeable_config_obj,
                    queue_obj,
                    config.log_path,
                    config.error_path,
                    False
                )
            except Exception:
                config.logger.log_stack_trace(traceback.format_exc(), job_id=job_id)
            subprocess.call(['ls', '-lR', '.'], stdout=overall_log)

        config.logger.log_message(
            "Completed Generation",
            job_id=job_id,
            is_batch=is_batch_job,
            which_untrusted=which_untrusted,
            jobname=item_name,
            timelabel="wait:",
            elapsed_time=waittime,
        )

    else:
        sub_timestamp_path = os.path.join(tmp_submission, 'submission', ".submit.timestamp")
        with open(sub_timestamp_path, 'r') as submission_time_file:
            submission_string = submission_time_file.read().rstrip()

        item_name = os.path.join(
            queue_obj["semester"],
            queue_obj["course"],
            "submissions",
            queue_obj["gradeable"],
            queue_obj["who"],
            str(queue_obj["version"])
        )

        config.logger.log_message(
            "",
            job_id=job_id,
            is_batch=is_batch_job,
            which_untrusted=which_untrusted,
            jobname=item_name,
            timelabel="wait:",
            elapsed_time=waittime,
        )

        with open(os.path.join(tmp_autograding, "form.json"), 'r') as infile:
            gradeable_config_obj = json.load(infile)
        is_vcs = gradeable_config_obj["upload_type"] == "repository"

        # Load all testcases.
        testcases = list()
        testcase_num = 1
        for t in complete_config_obj['testcases']:
            tmp_test = testcase.Testcase(
                config,
                testcase_num,
                queue_obj,
                complete_config_obj,
                t,
                which_untrusted,
                is_vcs,
                is_batch_job,
                job_id,
                working_directory,
                testcases,
                submission_string,
                config.log_path,
                config.error_path,
                is_test_environment=False
            )
            testcases.append(tmp_test)
            testcase_num += 1

        with open(os.path.join(tmp_logs, "overall.txt"), 'a') as overall_log:
            os.chdir(tmp_work)

            # COMPILE THE SUBMITTED CODE
            print(f"{seperator}COMPILATION STARTS", file=overall_log)
            overall_log.flush()
            for tc in testcases:
                if tc.type != 'Execution':
                    tc.execute()

                    # Killalll removes any stray processes left over from compilation.
                    killall_success = subprocess.call(
                        [
                            os.path.join(install_dir, "sbin", "untrusted_execute"),
                            which_untrusted,
                            os.path.join(install_dir, "sbin", "killall.py")
                        ],
                        stdout=overall_log
                    )

                    if killall_success != 0:
                        print(
                            f'INPUT GENERATION ERROR: had to kill {killall_success} process(es)',
                            file=overall_log
                        )
                    else:
                        print("KILLALL COMPLETE INPUT GENERATION", file=overall_log)
                    overall_log.flush()

            overall_log.flush()
            subprocess.call(['ls', '-lR', '.'], stdout=overall_log)
            overall_log.flush()

            # GENERATE INPUT
            print(f"{seperator}INPUT GENERATION STARTS", file=overall_log)
            for tc in testcases:
                if tc.has_input_generator_commands:
                    tc.generate_random_inputs()

                    # Killalll removes any stray processes left over from input generation.
                    killall_success = subprocess.call(
                        [
                            os.path.join(install_dir, "sbin", "untrusted_execute"),
                            which_untrusted,
                            os.path.join(install_dir, "sbin", "killall.py")
                        ],
                        stdout=overall_log
                    )
                    if killall_success != 0:
                        print(
                            f'INPUT GENERATION ERROR: had to kill {killall_success} process(es)',
                            file=overall_log
                        )
                    else:
                        print("KILLALL COMPLETE INPUT GENERATION", file=overall_log)
                    overall_log.flush()

            subprocess.call(['ls', '-lR', '.'], stdout=overall_log)
            overall_log.flush()

            # RUN EXECUTION TESTCASES
            print(f"{seperator}RUNNER STARTS", file=overall_log)
            overall_log.flush()
            for tc in testcases:
                if tc.type == 'Execution':
                    tc.execute()

                    # Killalll removes any stray processes left over from execution.
                    killall_success = subprocess.call(
                        [
                            os.path.join(install_dir, "sbin", "untrusted_execute"),
                            which_untrusted,
                            os.path.join(install_dir, "sbin", "killall.py")
                        ],
                        stdout=overall_log
                    )
                    overall_log.flush()

                    print("LOGGING END my_runner.out", file=overall_log)
                    if killall_success != 0:
                        print(
                            f'RUNNER ERROR: had to kill {killall_success} process(es)',
                            file=overall_log
                        )
                    else:
                        print("KILLALL COMPLETE my_runner.out", file=overall_log)
                    overall_log.flush()

            subprocess.call(['ls', '-lR', '.'], stdout=overall_log)
            overall_log.flush()

            # RANDOM OUTPUT GENERATION
            print(f"{seperator}OUTPUT GENERATION STARTS", file=overall_log)
            for tc in testcases:
                if tc.has_solution_commands:
                    tc.generate_random_outputs()

                    # Killalll removes any stray processes left over from output generation.
                    killall_success = subprocess.call(
                        [
                            os.path.join(install_dir, "sbin", "untrusted_execute"),
                            which_untrusted,
                            os.path.join(install_dir, "sbin", "killall.py")
                        ],
                        stdout=overall_log
                    )

                    if killall_success != 0:
                        print(
                            f'OUTPUT GENERATION ERROR: had to kill {killall_success} process(es)',
                            file=overall_log
                        )
                    else:
                        print("KILLALL COMPLETE OUTPUT GENERATION", file=overall_log)
                    overall_log.flush()

            subprocess.call(['ls', '-lR', '.'], stdout=overall_log)
            overall_log.flush()

            # VALIDATE STUDENT OUTPUT
            print(f"{seperator}VALIDATION STARTS", file=overall_log)
            overall_log.flush()

            # Create a jailed sandbox to run validation inside of.
            validation_environment = jailed_sandbox.JailedSandbox(
                config,
                job_id,
                which_untrusted,
                tmp_work,
                is_vcs,
                is_batch_job,
                complete_config_obj,
                {},
                working_directory,
                config.log_path,
                config.error_path,
                False
            )

            # Copy sensitive expected output files into tmp_work.
            autograding_utils.setup_for_validation(
                config,
                working_directory,
                complete_config_obj,
                is_vcs,
                testcases,
                job_id,
            )

            with open(os.path.join(tmp_logs, "validator_log.txt"), 'w') as logfile:
                arguments = [
                    queue_obj["gradeable"],
                    queue_obj["who"],
                    str(queue_obj["version"]),
                    submission_string
                ]
                success = validation_environment.execute(
                    which_untrusted,
                    'my_validator.out',
                    arguments,
                    logfile,
                    cwd=tmp_work
                )

                if success == 0:
                    print(socket.gethostname(), which_untrusted, "VALIDATOR OK")
                else:
                    print(socket.gethostname(), which_untrusted, "VALIDATOR FAILURE")
            subprocess.call(['ls', '-lR', '.'], stdout=overall_log)
            overall_log.flush()

            os.chdir(working_directory)
            autograding_utils.untrusted_grant_rwx_access(
                install_dir,
                which_untrusted,
                tmp_work
            )
            autograding_utils.add_all_permissions(tmp_work)

            # ARCHIVE STUDENT RESULTS
            print(f"{seperator}ARCHIVING STARTS", file=overall_log)
            overall_log.flush()
            for tc in testcases:
                # Removes test input files, makes details directory for the testcase.
                tc.setup_for_archival(overall_log)

            try:
                # Perform archival.
                autograding_utils.archive_autograding_results(
                    config,
                    working_directory,
                    job_id,
                    which_untrusted,
                    is_batch_job,
                    complete_config_obj,
                    gradeable_config_obj,
                    queue_obj,
                    config.log_path,
                    config.error_path,
                    False
                )
            except Exception:
                traceback.print_exc()
            subprocess.call(['ls', '-lR', '.'], stdout=overall_log)

    # Zip the results
    filehandle, my_results_zip_file = tempfile.mkstemp()
    autograding_utils.zip_my_directory(tmp_results, my_results_zip_file)
    os.close(filehandle)

    # Remove the tmp directory.
    shutil.rmtree(working_directory)
    return my_results_zip_file


if __name__ == "__main__":
    raise SystemExit('ERROR: Do not call this script directly')
