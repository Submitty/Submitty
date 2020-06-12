"""
Writes out submission datetime details (when it was submitted, how long it was in grading
process, etc) to a history.json file which is a list of all grading attempts for a
particular submission (including initial grading of it and all regrades).
"""

import os
import sys
import collections
import json
from datetime import datetime
from submitty_utils import dateutils
import fcntl
import traceback
import zipfile
import stat
import subprocess
import shutil
import codecs
import glob

def just_write_grade_history(json_file,assignment_deadline,submission_time,seconds_late,
                             first_access_time,access_duration,queue_time,batch_regrade,grading_began,
                             wait_time,grading_finished,grade_time,autograde_total,
                             revision):

    #####################################
    # LOAD THE PREVIOUS HISTORY
    if os.path.isfile(json_file):
        with open(json_file, 'r') as infile:
            obj = json.load(infile, object_pairs_hook=collections.OrderedDict)
    else:
        obj = []

    #####################################
    # CREATE THE NEWEST INFO BLOB
    blob = collections.OrderedDict()
    blob["assignment_deadline"] = assignment_deadline
    blob["submission_time"] = submission_time
    seconds_late = seconds_late
    if seconds_late > 0:
        minutes_late = int((seconds_late+60-1) / 60)
        hours_late = int((seconds_late+60*60-1) / (60*60))
        days_late = int((seconds_late+60*60*24-1) / (60*60*24))
        blob["days_late_before_extensions"] = days_late
    blob["queue_time"] = queue_time
    blob["batch_regrade"] = True if batch_regrade == "BATCH" else False
    blob["first_access_time"] = first_access_time
    blob["access_duration"] = access_duration
    blob["grading_began"] = grading_began
    blob["wait_time"] = wait_time
    blob["grading_finished"] = grading_finished
    blob["grade_time"] = grade_time
    blob["autograde_result"] = autograde_total
    autograde_array = str.split(autograde_total)
    if len(autograde_array) > 0 and autograde_array[0] == "Automatic":
        blob["autograde_total"] = int(autograde_array[3])
        if len(autograde_array) == 6:
            blob["autograde_max_possible"] = int(autograde_array[5])
    if revision:
        blob["revision"] = revision


    #####################################
    #  ADD IT TO THE HISTORY
    obj.append(blob)
    with open(json_file, 'w') as outfile:
        json.dump(obj, outfile, indent=4, separators=(',', ': '))


# ==================================================================================
#
#  LOGGING FUNCTIONS
#
# ==================================================================================

def log_message(log_path, job_id="UNKNOWN", is_batch=False, which_untrusted="", jobname="", timelabel="", elapsed_time=-1,
                message=""):
    """ Given a log directory, create or append a message to a dated log file in that directory. """

    now = dateutils.get_current_time()
    datefile = datetime.strftime(now, "%Y%m%d")+".txt"
    autograding_log_file = os.path.join(log_path, datefile)
    easy_to_read_date = dateutils.write_submitty_date(now, True)
    batch_string = "BATCH" if is_batch else ""
    if elapsed_time == "":
        elapsed_time = -1
    elapsed_time_string = "" if elapsed_time < 0 else '{:9.3f}'.format(elapsed_time)
    time_unit = "" if elapsed_time < 0 else "sec"
    parts = (easy_to_read_date, f"{job_id:>6s}", f"{batch_string:>5s}", f"{which_untrusted:>11s}", 
             f"{jobname:75s}", f"{timelabel:6s} {elapsed_time_string:>9s} {time_unit:>3s}", message)
    write_to_log(autograding_log_file, parts)


def log_stack_trace(log_path, job_id="UNKNOWN", is_batch=False, which_untrusted="", jobname="", timelabel="", elapsed_time=-1, trace=""):
    """ Given a log directory, create or append a stack trace to a dated log file in that directory. """

    now = dateutils.get_current_time()
    datefile = "{0}.txt".format(datetime.strftime(now, "%Y%m%d"))
    os.makedirs(log_path, exist_ok=True)
    autograding_log_file = os.path.join(log_path, datefile)
    easy_to_read_date = dateutils.write_submitty_date(now, True)
    batch_string = "BATCH" if is_batch else ""
    if elapsed_time == "":
        elapsed_time = -1
    elapsed_time_string = "" if elapsed_time < 0 else '{:9.3f}'.format(elapsed_time)
    time_unit = "" if elapsed_time < 0 else "sec"
    parts = (easy_to_read_date, f"{job_id:>6s}", f"{batch_string:>5s}", f"{which_untrusted:>11s}", 
             f"{jobname:75s}", f"{timelabel:6s} {elapsed_time_string:>9s} {time_unit:>3s}\n", trace)
    write_to_log(autograding_log_file, parts)


def log_container_meta(log_path, event="", name="", container="", time=0):
    """ Given a log file, create or append container meta data to a log file. """

    now = dateutils.get_current_time()
    easy_to_read_date = dateutils.write_submitty_date(now, True)
    time_unit = "sec"
    parts = (easy_to_read_date, name, container, event, f"{time:.3f}", time_unit)
    write_to_log(log_path, parts)


def write_to_log(log_path, message):
    """ Given a log file, create or append message to log file"""
    with open(log_path, 'a+') as log_file:
        try:
            fcntl.flock(log_file, fcntl.LOCK_EX | fcntl.LOCK_NB)
            print(' | '.join((str(x) for x in message)), file=log_file)
            fcntl.flock(log_file, fcntl.LOCK_UN)
        except:
            print("Could not gain a lock on the log file.")


# ==================================================================================
#
#  VALIDATION FUNCTIONS
#
# ==================================================================================

def setup_for_validation(working_directory, complete_config, is_vcs, testcases, job_id, log_path, stack_trace_log_path):
    """ Prepare a directory for validation by copying in and permissioning the required files. """
    
    tmp_submission = os.path.join(working_directory,"TMP_SUBMISSION")
    tmp_work = os.path.join(working_directory,"TMP_WORK")
    tmp_results = os.path.join(working_directory,"TMP_RESULTS")
    submission_path = os.path.join(tmp_submission, "submission")
    checkout_subdirectory = complete_config["autograding"].get("use_checkout_subdirectory","")
    tmp_logs = os.path.join(working_directory,"TMP_SUBMISSION","tmp_logs")
    tmp_work_test_output = os.path.join(tmp_work, "test_output")
    tmp_work_generated_output = os.path.join(tmp_work, "generated_output")
    tmp_work_instructor_solution = os.path.join(tmp_work, "instructor_solution")
    tmp_autograding = os.path.join(working_directory,"TMP_AUTOGRADING")

    os.mkdir(tmp_work_test_output)
    os.mkdir(tmp_work_generated_output)
    os.mkdir(tmp_work_instructor_solution)

    patterns = complete_config['autograding']

    # Add all permissions to tmp_work
    add_permissions_recursive(tmp_work,
                              stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                              stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                              stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH) 

    # Copy required submission/checkout files
    pattern_copy("submission_to_validation", patterns['submission_to_validation'], submission_path, tmp_work, tmp_logs)
    if is_vcs:
        checkout_subdir_path = os.path.join(tmp_submission, 'checkout', checkout_subdirectory)
        pattern_copy("checkout_to_validation", patterns['submission_to_validation'],checkout_subdir_path,tmp_work,tmp_logs)
    
    for c in testcases:
        if c.type == 'Compilation':
            pattern_copy("compilation_to_validation", patterns['compilation_to_validation'], c.secure_environment.directory, tmp_work, tmp_logs)

    # Copy expected files into the tmp_work_test_output path
    test_output_path = os.path.join(tmp_autograding, 'test_output')
    copy_contents_into(job_id, test_output_path, tmp_work_test_output, tmp_logs, log_path, stack_trace_log_path)
    generated_output_path = os.path.join(tmp_autograding, 'generated_output')
    copy_contents_into(job_id, generated_output_path, tmp_work_generated_output, tmp_logs, log_path, stack_trace_log_path)

    # Copy in instructor solution code.
    instructor_solution = os.path.join(tmp_autograding, 'instructor_solution')
    copy_contents_into(job_id, instructor_solution, tmp_work_instructor_solution, tmp_logs, log_path, stack_trace_log_path)

    # Copy any instructor custom validation code into the tmp work directory
    custom_validation_code_path = os.path.join(tmp_autograding, 'custom_validation_code')
    copy_contents_into(job_id, custom_validation_code_path, tmp_work, tmp_logs, log_path, stack_trace_log_path)

    

    # Copy the validation script into this directory.
    bin_runner = os.path.join(tmp_autograding, "bin","validate.out")
    my_runner  = os.path.join(tmp_work, "my_validator.out")
    
    shutil.copy(bin_runner, my_runner)

    add_permissions_recursive(tmp_work,
                              stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                              stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                              stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)
    add_permissions(my_runner, stat.S_IXUSR | stat.S_IXGRP |stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)

# ==================================================================================
#
#  ARCHIVAL AND PERMISSIONS FUNCTIONS
#
# ==================================================================================

def add_all_permissions(path):
    """ Recursively chmod a directory or file 777. """
    if os.path.isdir(path):
        add_permissions_recursive(path,
                          stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                          stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                          stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)
    elif os.path.isfile(path):
        add_permissions(path, stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)


def lock_down_folder_permissions(top_dir):
    # Chmod a directory to take away group and other rwx. 
    os.chmod(top_dir,os.stat(top_dir).st_mode & ~stat.S_IRGRP & ~stat.S_IWGRP & ~stat.S_IXGRP & ~stat.S_IROTH & ~stat.S_IWOTH & ~stat.S_IXOTH)
   

def prepare_directory_for_autograding(working_directory, user_id_of_runner, autograding_zip_file, submission_zip_file, is_test_environment, log_path, stack_trace_log_path, SUBMITTY_INSTALL_DIR):
    """ 
    Given a working directory, set up that directory for autograding by creating the required subdirectories
    and configuring permissions. 
    """
    
    # If an old (stale) version of the working directory exists, we need to remove it.
    if os.path.exists(working_directory):
        # Make certain we can remove old instances of the working directory.
        if not is_test_environment:
            untrusted_grant_rwx_access(SUBMITTY_INSTALL_DIR, user_id_of_runner, working_directory)
        add_all_permissions(working_directory)
        shutil.rmtree(working_directory,ignore_errors=True)
    
    # Create the working directory
    os.mkdir(working_directory)

    # Important directory variables.
    tmp_autograding = os.path.join(working_directory,"TMP_AUTOGRADING")
    tmp_submission = os.path.join(working_directory,"TMP_SUBMISSION")
    tmp_work = os.path.join(working_directory,"TMP_WORK")
    tmp_logs = os.path.join(working_directory,"TMP_SUBMISSION","tmp_logs")
    submission_path = os.path.join(tmp_submission, "submission")
    tmp_work_test_input = os.path.join(tmp_work, "test_input")

    os.mkdir(tmp_work)
    os.mkdir(tmp_work_test_input)

    # Remove any docker containers left over from past runs.
    old_containers = subprocess.check_output(['docker', 'ps', '-aq', '-f', 'name={0}'.format(user_id_of_runner)]).split()
    if len(old_containers) > 0:
        print('REMOVING STALE CONTAINERS')
    for old_container in old_containers:
        subprocess.call(['docker', 'rm', '-f', old_container.decode('utf8')])

    # Remove any docker networks left over from past runs.
    old_networks = subprocess.check_output(['docker', 'network', 'ls', '-qf', 'name={0}'.format(user_id_of_runner)]).split()
    if len(old_containers) > 0:
        print('REMOVING STALE NETWORKS')
    for old_network in old_networks:
        subprocess.call(['docker', 'network', 'rm', old_network.decode('utf8')])

    # Unzip the autograding and submission folders
    unzip_this_file(autograding_zip_file,tmp_autograding)
    unzip_this_file(submission_zip_file,tmp_submission)


    with open(os.path.join(tmp_autograding, "complete_config.json"), 'r') as infile:
        complete_config_obj = json.load(infile)

    # Handle the case where a student errantly submits to multiple parts of a one part only gradeable.
    if complete_config_obj.get('one_part_only', False) == True:
        allow_only_one_part(submission_path, log_path=os.path.join(tmp_logs, "overall.txt"))

    with open(os.path.join(tmp_submission,"queue_file.json"), 'r') as infile:
        queue_obj = json.load(infile)
    job_id = queue_obj["job_id"]

    # copy output files
    test_input_path = os.path.join(tmp_autograding, 'test_input')
    # Copy test input files into tmp_work_test_input.
    copy_contents_into(job_id, test_input_path, tmp_work_test_input, tmp_logs, log_path, stack_trace_log_path)

    # Lock down permissions on the unzipped folders/test input folder to stop untrusted users from gaining access.
    lock_down_folder_permissions(tmp_work_test_input)
    lock_down_folder_permissions(tmp_autograding)
    lock_down_folder_permissions(tmp_submission)


def archive_autograding_results(working_directory, job_id, which_untrusted, is_batch_job, complete_config_obj, 
                                gradeable_config_obj, queue_obj, log_path, stack_trace_log_path, is_test_environment):
    """ After grading is finished, archive the results. """

    tmp_autograding = os.path.join(working_directory,"TMP_AUTOGRADING")
    tmp_submission = os.path.join(working_directory,"TMP_SUBMISSION")
    tmp_work = os.path.join(working_directory,"TMP_WORK")
    tmp_logs = os.path.join(working_directory,"TMP_SUBMISSION","tmp_logs")
    tmp_results = os.path.join(working_directory,"TMP_RESULTS")
    submission_path = os.path.join(tmp_submission, "submission")
    random_output_path = os.path.join(tmp_work, 'random_output')

    if "generate_output" not in queue_obj:
        partial_path = os.path.join(queue_obj["gradeable"],queue_obj["who"],str(queue_obj["version"]))
        item_name = os.path.join(queue_obj["semester"],queue_obj["course"],"submissions",partial_path)
    elif queue_obj["generate_output"]:
        item_name = os.path.join(queue_obj["semester"],queue_obj["course"],"generated_output",queue_obj["gradeable"])
    results_public_dir = os.path.join(tmp_results,"results_public")
    results_details_dir = os.path.join(tmp_results, "details")
    patterns = complete_config_obj['autograding']

    # Copy work to details
    pattern_copy("work_to_details", patterns['work_to_details'], tmp_work, results_details_dir, tmp_logs)
    
    # Copy work to public
    if 'work_to_public' in patterns:
        pattern_copy("work_to_public", patterns['work_to_public'], tmp_work, results_public_dir, tmp_logs)

    if os.path.exists(random_output_path):
        pattern_copy("work_to_random_output", [os.path.join(random_output_path, 'test*', '**', '*.txt'),], tmp_work, tmp_results, tmp_logs)
    # timestamp of first access to the gradeable page
    first_access_string = ""
    # grab the submission time
    if "generate_output" in queue_obj and queue_obj["generate_output"]:
        submission_string = ""
    else:
        with open(os.path.join(tmp_submission, 'submission' ,".submit.timestamp"), 'r') as submission_time_file:
            submission_string = submission_time_file.read().rstrip()
        # grab the first access to the gradeable page (if it exists)
        user_assignment_access_filename = os.path.join(tmp_submission, "user_assignment_access.json")
        if os.path.exists(user_assignment_access_filename):
            with open(user_assignment_access_filename, 'r') as access_file:
                obj = json.load(access_file, object_pairs_hook=collections.OrderedDict)
                first_access_string = obj["page_load_history"][0]["time"]

    history_file_tmp = os.path.join(tmp_submission,"history.json")
    history_file = os.path.join(tmp_results,"history.json")
    if os.path.isfile(history_file_tmp) and not is_test_environment:

        from . import CONFIG_PATH
        with open(os.path.join(CONFIG_PATH, 'submitty_users.json')) as open_file:
            OPEN_JSON = json.load(open_file)
        DAEMON_UID = OPEN_JSON['daemon_uid']

        shutil.move(history_file_tmp, history_file)
        # fix permissions
        ta_group_id = os.stat(tmp_results).st_gid
        os.chown(history_file, int(DAEMON_UID),ta_group_id)
        add_permissions(history_file, stat.S_IRGRP)
    grading_finished = dateutils.get_current_time()

    if "generate_output" not in queue_obj:
        try:
            shutil.copy(os.path.join(tmp_work, "grade.txt"), tmp_results)
        except:
            with open(os.path.join(tmp_logs,"overall.txt"),'a') as f:
                print ("\n\nERROR: Grading incomplete -- Could not copy ",os.path.join(tmp_work,"grade.txt"))
            log_message(log_path, job_id, is_batch_job, which_untrusted, item_name, message="ERROR: grade.txt does not exist")
            log_stack_trace(stack_trace_log_path, job_id, is_batch_job, which_untrusted, item_name, trace=traceback.format_exc())

        grade_result = ""
        try:
            with open(os.path.join(tmp_work,"grade.txt")) as f:
                lines = f.readlines()
                for line in lines:
                    line = line.rstrip('\n')
                    if line.startswith("Automatic grading total:"):
                        grade_result = line
        except:
            with open(os.path.join(tmp_logs,"overall.txt"),'a') as f:
                print ("\n\nERROR: Grading incomplete -- Could not open ",os.path.join(tmp_work,"grade.txt"))
                log_message(job_id,is_batch_job,which_untrusted,item_name,message="ERROR: grade.txt does not exist")
                log_stack_trace(job_id,is_batch_job,which_untrusted,item_name,trace=traceback.format_exc())


        gradeable_deadline_string = gradeable_config_obj["date_due"]

        # FIXME: The access date string is currently misformatted
        #    mm-dd-yyyy, but we want yyyy-mm-dd.  Also it is missing
        #    the common name timezone string, e.g., "America/NewYork".
        #    We should standardize this logging eventually, but
        #    keeping it as is because we are mid-semester with this
        #    new feature and I don't want to break things.
        words = first_access_string.split(' ')
        date_parts = words[0].split('-')
        if len(date_parts[0]) == 2:
            words[0] = date_parts[2]+'-'+date_parts[0]+'-'+date_parts[1]
            first_access_string = ' '.join(words)
        
        submission_datetime = dateutils.read_submitty_date(submission_string)
        gradeable_deadline_datetime = dateutils.read_submitty_date(gradeable_deadline_string)
        gradeable_deadline_longstring = dateutils.write_submitty_date(gradeable_deadline_datetime)
        submission_longstring = dateutils.write_submitty_date(submission_datetime)
        seconds_late = int((submission_datetime-gradeable_deadline_datetime).total_seconds())
        # compute the access duration in seconds (if it exists)
        access_duration = -1
        if first_access_string != "":
            first_access_datetime = dateutils.read_submitty_date(first_access_string)
            access_duration = int((submission_datetime-first_access_datetime).total_seconds())

        # note: negative = not late
        grading_finished_longstring = dateutils.write_submitty_date(grading_finished)

        with open(os.path.join(tmp_submission,".grading_began"), 'r') as f:
            grading_began_longstring = f.read()
        grading_began = dateutils.read_submitty_date(grading_began_longstring)

        gradingtime = (grading_finished - grading_began).total_seconds()

        queue_obj["gradingtime"]=gradingtime
        queue_obj["grade_result"]=grade_result
        queue_obj["which_untrusted"]=which_untrusted
        waittime = queue_obj["waittime"]

        try:

            # Make certain results.json is utf-8 encoded.
            results_json_path = os.path.join(tmp_work, 'results.json')
            with codecs.open(results_json_path, 'r', encoding='utf-8', errors='ignore') as infile:
                results_str = "".join(line.rstrip() for line in infile)
                results_obj = json.loads(results_str)
            with open(results_json_path, 'w') as outfile:
                json.dump(results_obj, outfile, indent=4)

            shutil.move(results_json_path, os.path.join(tmp_results, "results.json"))
        except:
            with open(os.path.join(tmp_logs,"overall.txt"),'a') as f:
                print ("\n\nERROR: Grading incomplete -- Could not open/write ",os.path.join(tmp_work,"results.json"))
                log_message(log_path, job_id,is_batch_job,which_untrusted,item_name,message="ERROR: results.json read/write error")
                log_stack_trace(stack_trace_log_path, job_id,is_batch_job,which_untrusted,item_name,trace=traceback.format_exc())

        # Rescue custom validator files
        custom_validator_output_directory = os.path.join(tmp_results, "custom_validator_output")
        pattern_copy("rescue_custom_validator_validation_jsons", [os.path.join(tmp_work, 'validation_results_*.json'),], tmp_work, custom_validator_output_directory, tmp_logs)
        pattern_copy("rescue_custom_validator_logs", [os.path.join(tmp_work, 'validation_logfile_*.txt'),], tmp_work, custom_validator_output_directory, tmp_logs)
        pattern_copy("rescue_custom_validator_errors", [os.path.join(tmp_work, 'validation_stderr_*.txt'),], tmp_work, custom_validator_output_directory, tmp_logs)

        just_write_grade_history(history_file,
                                gradeable_deadline_longstring,
                                submission_longstring,
                                seconds_late,
                                first_access_string,
                                access_duration,
                                queue_obj["queue_time"],
                                "BATCH" if is_batch_job else "INTERACTIVE",
                                grading_began_longstring,
                                int(waittime),
                                grading_finished_longstring,
                                int(gradingtime),
                                grade_result,
                                queue_obj.get("revision", None))

        with open(os.path.join(tmp_logs,"overall.txt"),'a') as f:
            f.write("FINISHED GRADING!\n")
        
        log_message(log_path, job_id,is_batch_job,which_untrusted,item_name,"grade:",gradingtime,grade_result)

    with open(os.path.join(tmp_results,"queue_file.json"),'w') as outfile:
        json.dump(queue_obj,outfile,sort_keys=True,indent=4,separators=(',', ': '))

    # save the logs!
    shutil.copytree(tmp_logs,os.path.join(tmp_results,"logs"))


def allow_only_one_part(path, log_path=os.devnull):
    """
    Given a path to a directory, iterate through the directory and detect folders that start with
    "part". If there is more than one and they have files, then delete all of the part folders except
    for the first one that has files.

    An example would be if you had the folder structure:
    part1/
        test.py
    part2/
        test.cpp

    Then the part2 folder would be deleted, leaving just the part1 folder.

    :param path: string filepath to directory to scan for parts in
    :param log_path: string filepath to file to write print statements to
    """
    if not os.path.isdir(path):
        return
    with open(log_path, 'a') as log:
        clean_directories = []
        print('Clean up multiple parts')
        log.flush()
        for entry in sorted(os.listdir(path)):
            full_path = os.path.join(path, entry)
            if not os.path.isdir(full_path) or not entry.startswith('part'):
                continue
            count = len(os.listdir(full_path))
            print('{}: {}'.format(entry, count))
            if count > 0:
                clean_directories.append(full_path)

        if len(clean_directories) > 1:
            print("Student submitted to multiple parts in violation of instructions.\n"
                  "Removing files from all but first non empty part.")

            for i in range(1, len(clean_directories)):
                print("REMOVE: {}".format(clean_directories[i]))
                for entry in os.listdir(clean_directories[i]):
                    print("  -> {}".format(entry))
                shutil.rmtree(clean_directories[i])

# go through the testcase folder (e.g. test01/) and remove anything
# that matches the test input (avoid archiving copies of these files!)
def remove_test_input_files(overall_log, test_input_path, testcase_folder):
    for path, subdirs, files in os.walk(test_input_path):
        for name in files:
            relative = path[len(test_input_path)+1:]
            my_file = os.path.join(testcase_folder, relative, name)
            if os.path.isfile(my_file):
                print ("removing (likely) stale test_input file: ", my_file, file=overall_log)
                overall_log.flush()
                os.remove(my_file)


def add_permissions(item,perms):
    if os.getuid() == os.stat(item).st_uid:
        os.chmod(item,os.stat(item).st_mode | perms)
    # else, can't change permissions on this file/directory!


def add_permissions_recursive(top_dir,root_perms,dir_perms,file_perms):
    for root, dirs, files in os.walk(top_dir):
        add_permissions(root,root_perms)
        for d in dirs:
            add_permissions(os.path.join(root, d),dir_perms)
        for f in files:
            add_permissions(os.path.join(root, f),file_perms)


# copy the files & directories from source to target
# it will create directories as needed
# it's ok if the target directory or subdirectories already exist
# it will overwrite files with the same name if they exist
def copy_contents_into(job_id,source,target,tmp_logs, log_path, stack_trace_log_path):
    if not os.path.isdir(target):
        log_message(log_path, job_id, message="ERROR: Could not copy contents. The target directory does not exist " + target)
        raise RuntimeError("ERROR: the target directory does not exist '", target, "'")
    if os.path.isdir(source):
        for item in os.listdir(source):
            if os.path.isdir(os.path.join(source,item)):
                if os.path.isdir(os.path.join(target,item)):
                    # recurse
                    copy_contents_into(job_id,os.path.join(source,item),os.path.join(target,item),tmp_logs, log_path, stack_trace_log_path)
                elif os.path.isfile(os.path.join(target,item)):
                    log_message(log_path, job_id,message="ERROR: the target subpath is a file not a directory '" + os.path.join(target,item) + "'")
                    raise RuntimeError("ERROR: the target subpath is a file not a directory '", os.path.join(target,item), "'")
                else:
                    # copy entire subtree
                    shutil.copytree(os.path.join(source,item),os.path.join(target,item))
            else:
                if os.path.exists(os.path.join(target,item)):
                    with open(os.path.join(tmp_logs,"overall.txt"),'a') as f:
                        print ("\nWARNING: REMOVING DESTINATION FILE" , os.path.join(target,item),
                               " THEN OVERWRITING: ", os.path.join(source,item), "\n", file=f)
                    os.remove(os.path.join(target,item))
                try:
                    shutil.copy(os.path.join(source,item),target)
                except:
                    grade_items_logging.log_stack_trace(stack_trace_log_path, job_id=job_id,trace=traceback.format_exc())
                    return
    else:
        print(f'{source} is not a directory')


# copy files that match one of the patterns from the source directory
# to the target directory.  
def pattern_copy(what, patterns, source, target, tmp_logs):
    with open(os.path.join(tmp_logs,"overall.txt"),'a') as f:
        print (what," pattern copy ", patterns, " from ", source, " -> ", target, file=f)
        for pattern in patterns:
            for my_file in glob.glob(os.path.join(source,pattern),recursive=True):
                if (os.path.isfile(my_file)):
                    # grab the matched name
                    relpath = os.path.relpath(my_file,source)
                    # make the necessary directories leading to the file
                    os.makedirs(os.path.join(target,os.path.dirname(relpath)),exist_ok=True)
                    # copy the file
                    shutil.copy(my_file,os.path.join(target,relpath))
                    print ("    COPY ",my_file,
                           " -> ",os.path.join(target,relpath), file=f)
                else:
                    print ("skip this directory (will recurse into it later)", my_file, file=f)


# give permissions to all created files to the DAEMON_USER
def untrusted_grant_rwx_access(SUBMITTY_INSTALL_DIR, which_untrusted, my_dir):
    subprocess.call([os.path.join(SUBMITTY_INSTALL_DIR, "sbin", "untrusted_execute"),
                     which_untrusted,
                     "/usr/bin/find",
                     my_dir,
                     "-user",
                     which_untrusted,
                     "-exec",
                     "/bin/chmod",
                     "ugo+rwx",
                     "{}",
                     ";"])

# Used by packer unpacker
def zip_my_directory(path,zipfilename):
    zipf = zipfile.ZipFile(zipfilename,'w',zipfile.ZIP_DEFLATED)
    for root,dirs,files in os.walk(path):
        for my_file in files:
            relpath = root[len(path)+1:]
            zipf.write(os.path.join(root,my_file),os.path.join(relpath,my_file))
    zipf.close()

# Used by packer unpacker
def unzip_this_file(zipfilename,path):
    if not os.path.exists(zipfilename):
        raise RuntimeError("ERROR: zip file does not exist '", zipfilename, "'")
    zip_ref = zipfile.ZipFile(zipfilename,'r')
    zip_ref.extractall(path)
    zip_ref.close()

# ==================================================================================
#
#  PRE- AND POST-COMMAND FUNCTIONS
#
# ==================================================================================


def pre_command_copy_file(source_testcase, source_directory, destination_testcase, destination, job_id, tmp_logs, log_path, stack_trace_log_path):
    """ Handles the cp pre_command. """

    source_testcase = os.path.join(str(os.getcwd()), source_testcase)

    if not os.path.isdir(source_testcase):
        raise RuntimeError("ERROR: The directory {0} does not exist.".format(source_testcase))

    if not os.path.isdir(destination_testcase):
        raise RuntimeError("ERROR: The directory {0} does not exist.".format(destination_testcase))

    source = os.path.join(source_testcase, source_directory)
    target = os.path.join(destination_testcase, destination)

    # The target without the potential executable.
    target_base = '/'.join(target.split('/')[:-1])

    # If the source is a directory, we copy the entire thing into the
    # target.
    if os.path.isdir(source):
        # We must copy from directory to directory 
        copy_contents_into(job_id, source, target, tmp_logs, log_path, stack_trace_log_path)

    # Separate ** and * for simplicity.
    elif not '**' in source:
        # Grab all of the files that match the pattern
        files = glob.glob(source, recursive=True)

        # The target base must exist in order for a copy to occur
        if target_base != '' and not os.path.isdir(target_base):
            raise RuntimeError("ERROR: The directory {0} does not exist.".format(target_base))
        # Copy every file. This works whether target exists (is a directory) or does not (is a target file)
        for file in files:
            try:
                shutil.copy(file, target)
            except Exception as e:
                traceback.print_exc()
                log_message(log_path, job_id, message="Pre Command could not perform copy: {0} -> {1}".format(file, target))
    else:
        # Everything after the first **. 
        source_base = source[:source.find('**')]
        # The full target must exist (we must be moving to a directory.)
        if not os.path.isdir(target):
            raise RuntimeError("ERROR: The directory {0} does not exist.".format(target))

        # Grab all of the files that match the pattern.
        files = glob.glob(source, recursive=True)

        # For every file matched
        for file_source in files:
            file_target = os.path.join(target, file_source.replace(source_base,''))
            # Remove the file path.
            file_target_dir = '/'.join(file_target.split('/')[:-1])
            # If the target directory doesn't exist, create it.
            if not os.path.isdir(file_target_dir):
                os.makedirs(file_target_dir)
            # Copy.
            try:
                shutil.copy(file_source, file_target)
            except Exception as e:
                traceback.print_exc()
                log_message(log_path, job_id, message="Pre Command could not perform copy: {0} -> {1}".format(file_source, file_target))
