import json
import os
import tempfile
import shutil
import subprocess
import socket
import traceback

from . import autograding_utils, testcase
from . execution_environments import jailed_sandbox


def get_item_from_item_pool(complete_config_obj, item_name):
    for item in complete_config_obj['item_pool']:
        if item['item_name'] == item_name:
            return item
    return None


def get_testcases(
    complete_config_obj,
    config,
    queue_obj,
    working_directory,
    which_untrusted,
    item_name,
    notebook_data=None
):
    '''
    Retrieve testcases from a config obj. If notebook_data is
    not null, return testcases corresponding to it, else return all testcases.
    '''
    testcase_objs = []
    testcase_specs = complete_config_obj['testcases']

    if notebook_data is not None:
        # Gather the testcase specifications for all itempool testcases
        for notebook_item in notebook_data:
            item_dict = get_item_from_item_pool(complete_config_obj, notebook_item)
            if item_dict is None:
                autograding_utils.log_message(
                    config.log_path,
                    queue_obj["job_id"],
                    queue_obj["regrade"],
                    which_untrusted,
                    item_name,
                    message=f"ERROR: could not find {notebook_item} in item pool."
                )
                continue
            testcase_specs += item_dict['testcases']
    else:
        for item in complete_config_obj['item_pool']:
            testcase_specs += item['testcases']

    is_vcs = queue_obj['vcs_checkout']

    # Construct the testcase objects
    for t in testcase_specs:
        tmp_test = testcase.Testcase(
            config,
            t['testcase_id'],
            queue_obj,
            complete_config_obj,
            t,
            which_untrusted,
            is_vcs,
            queue_obj["regrade"],
            queue_obj["job_id"],
            working_directory,
            testcase_objs,
            '',
            config.log_path,
            config.error_path,
            is_test_environment=False
        )
        testcase_objs.append(tmp_test)

    return testcase_objs


def killall(config, which_untrusted, log_file):
    ''' Killalll removes any stray processes belonging to the untrusted user '''
    killall_success = subprocess.call(
        [
            os.path.join(config.submitty['submitty_install_dir'], "sbin", "untrusted_execute"),
            which_untrusted,
            os.path.join(config.submitty['submitty_install_dir'], "sbin", "killall.py")
        ],
        stdout=log_file
    )

    if killall_success != 0:
        print(
            f'KILLALL: had to kill {killall_success} process(es)',
            file=log_file
        )
    log_file.flush()


def run_compilation(testcases, config, which_untrusted, seperator, log_file):
    # COMPILE THE SUBMITTED CODE
    print(f"{seperator}COMPILATION STARTS", file=log_file)
    log_file.flush()
    for tc in testcases:
        if tc.type != 'Execution' and not os.path.exists(tc.secure_environment.directory):
            tc.execute()
            killall(config, which_untrusted, log_file)

    log_file.flush()
    subprocess.call(['ls', '-lR', '.'], stdout=log_file)
    log_file.flush()


def generate_input(testcases, config, which_untrusted, seperator, log_file):
    # GENERATE INPUT
    print(f"{seperator}INPUT GENERATION STARTS", file=log_file)
    for tc in testcases:
        if tc.has_input_generator_commands:
            tc.generate_random_inputs()
            killall(config, which_untrusted, log_file)

    subprocess.call(['ls', '-lR', '.'], stdout=log_file)
    log_file.flush()


def run_execution(testcases, config, which_untrusted, seperator, log_file):
    # RUN EXECUTION TESTCASES
    print(f"{seperator}RUNNER STARTS", file=log_file)
    log_file.flush()
    for tc in testcases:
        if tc.type == 'Execution' and not os.path.exists(tc.secure_environment.directory):
            tc.execute()
            killall(config, which_untrusted, log_file)

    subprocess.call(['ls', '-lR', '.'], stdout=log_file)
    log_file.flush()


def generate_output(testcases, config, which_untrusted, seperator, log_file):
    # RANDOM OUTPUT GENERATION
    print(f"{seperator}OUTPUT GENERATION STARTS", file=log_file)
    for tc in testcases:
        if tc.has_solution_commands:
            tc.generate_random_outputs()
            killall(config, which_untrusted, log_file)

    subprocess.call(['ls', '-lR', '.'], stdout=log_file)
    log_file.flush()


def run_validation(
    testcases,
    config,
    which_untrusted,
    seperator,
    queue_obj,
    tmp_work,
    is_vcs,
    complete_config_obj,
    working_directory,
    submission_string,
    log_file,
    tmp_logs,
    generate_all_output
):
    # VALIDATE STUDENT OUTPUT
    print(f"{seperator}VALIDATION STARTS", file=log_file)
    log_file.flush()

    # Create a jailed sandbox to run validation inside of.
    validation_environment = jailed_sandbox.JailedSandbox(
        config,
        queue_obj["job_id"],
        which_untrusted,
        tmp_work,
        is_vcs,
        queue_obj["regrade"],
        complete_config_obj,
        {},
        working_directory,
        config.log_path,
        config.error_path,
        False
    )

    # Copy sensitive expected output files into tmp_work.
    autograding_utils.setup_for_validation(
        working_directory,
        complete_config_obj,
        is_vcs,
        testcases,
        queue_obj["job_id"],
        config.log_path,
        config.error_path
    )

    with open(os.path.join(tmp_logs, "validator_log.txt"), 'w') as logfile:
        arguments = [
            queue_obj["gradeable"],
            queue_obj["who"],
            str(queue_obj["version"]),
            submission_string
        ]
        if generate_all_output:
            arguments.append('--generate_all_output')
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
    subprocess.call(['ls', '-lR', '.'], stdout=log_file)
    log_file.flush()

    # Remove the temporary .submit.notebook from tmp_work (not to be confused
    # with the copy tmp_submission/submission/.submit.notebook)
    submit_notebook_path = os.path.join(tmp_work, '.submit.notebook')
    if os.path.exists(submit_notebook_path):
        os.remove(submit_notebook_path)

    os.chdir(working_directory)
    autograding_utils.untrusted_grant_rwx_access(
        config.submitty['submitty_install_dir'],
        which_untrusted,
        tmp_work
    )
    autograding_utils.add_all_permissions(tmp_work)


def archive(
    testcases,
    config,
    working_directory,
    queue_obj,
    which_untrusted,
    item_name,
    complete_config_obj,
    gradeable_config_obj,
    seperator,
    log_file
):
    # ARCHIVE STUDENT RESULTS
    print(f"{seperator}ARCHIVING STARTS", file=log_file)
    log_file.flush()
    for tc in testcases:
        # Removes test input files, makes details directory for the testcase.
        tc.setup_for_archival(log_file)

    try:
        autograding_utils.archive_autograding_results(
            config,
            working_directory,
            queue_obj["job_id"],
            which_untrusted,
            queue_obj["regrade"],
            complete_config_obj,
            gradeable_config_obj,
            queue_obj,
            config.log_path,
            config.error_path,
            False
        )
    except Exception:
        print("\n\nERROR: Grading incomplete -- could not perform archival")
        autograding_utils.log_message(
            queue_obj['job_id'],
            queue_obj["regrade"],
            which_untrusted,
            item_name,
            message="ERROR: could not archive autograding results. See stack trace for more info."
        )
        autograding_utils.log_stack_trace(
            queue_obj['job_id'],
            queue_obj["regrade"],
            which_untrusted,
            item_name,
            trace=traceback.format_exc()
        )
    subprocess.call(['ls', '-lR', '.'], stdout=log_file)


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
        working_directory,
        which_untrusted,
        autograding_zip_file,
        submission_zip_file,
        False,
        config.log_path,
        config.error_path,
        install_dir
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

    with open(os.path.join(tmp_autograding, "form.json"), 'r') as infile:
        gradeable_config_obj = json.load(infile)
    is_vcs = gradeable_config_obj["upload_type"] == "repository"

    if "generate_output" in queue_obj and queue_obj["generate_output"]:
        ''' Cache the results when there are solution commands be no input generation commands'''
        item_name = os.path.join(
            queue_obj["semester"],
            queue_obj["course"],
            "generated_output",
            queue_obj["gradeable"]
        )

        testcases = list()
        for tmp_test in get_testcases(
            complete_config_obj,
            config,
            queue_obj,
            working_directory,
            which_untrusted,
            item_name
        ):
            if tmp_test.has_solution_commands and not tmp_test.has_input_generator_commands:
                testcases.append(tmp_test)

        with open(os.path.join(tmp_logs, "overall.txt"), 'a') as overall_log:
            os.chdir(tmp_work)

            generate_output(testcases, config, which_untrusted, seperator, overall_log)

            archive(
                testcases,
                config,
                working_directory,
                queue_obj,
                which_untrusted,
                item_name,
                complete_config_obj,
                gradeable_config_obj,
                seperator,
                overall_log
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

        autograding_utils.log_message(
            config.log_path,
            job_id,
            is_batch_job,
            which_untrusted,
            item_name,
            "wait:",
            waittime,
            ""
        )

        notebook_data_path = os.path.join(tmp_submission, 'submission', ".submit.notebook")
        if os.path.exists(notebook_data_path):
            with open(notebook_data_path, 'r') as infile:
                notebook_data = json.load(infile).get('item_pools_selected', [])
        else:
            notebook_data = []

        # Load all testcases.
        testcases = get_testcases(
            complete_config_obj,
            config,
            queue_obj,
            working_directory,
            which_untrusted,
            item_name,
            notebook_data=notebook_data
        )

        with open(os.path.join(tmp_logs, "overall.txt"), 'a') as overall_log:
            os.chdir(tmp_work)

            run_compilation(testcases, config, which_untrusted, seperator, overall_log)
            generate_input(testcases, config, which_untrusted, seperator, overall_log)
            run_execution(testcases, config, which_untrusted, seperator, overall_log)
            generate_output(testcases, config, which_untrusted, seperator, overall_log)
            run_validation(
                testcases,
                config,
                which_untrusted,
                seperator,
                queue_obj,
                tmp_work,
                is_vcs,
                complete_config_obj,
                working_directory,
                submission_string,
                overall_log,
                tmp_logs,
                False
            )
            archive(
                testcases,
                config,
                working_directory,
                queue_obj,
                which_untrusted,
                item_name,
                complete_config_obj,
                gradeable_config_obj,
                seperator,
                overall_log
            )

    # Zip the results
    filehandle, my_results_zip_file = tempfile.mkstemp()
    autograding_utils.zip_my_directory(tmp_results, my_results_zip_file)
    os.close(filehandle)

    # Remove the tmp directory.
    shutil.rmtree(working_directory)
    autograding_utils.cleanup_stale_containers(which_untrusted)
    return my_results_zip_file


if __name__ == "__main__":
    raise SystemExit('ERROR: Do not call this script directly')
