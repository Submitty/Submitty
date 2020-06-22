#!/usr/bin/env python3

import collections
import os
import time
import signal
import string
import json

import shutil
import contextlib
import datetime
import multiprocessing
from pathlib import Path
from submitty_utils import dateutils
import operator
import paramiko
import tempfile
import socket
import traceback
import subprocess
import random

import requests

from math import floor

from autograder import autograding_utils
from autograder import packer_unpacker

from submitty_utils import string_utils

CONFIG_PATH = os.path.join(os.path.dirname(os.path.realpath(__file__)), '..', 'config')
with open(os.path.join(CONFIG_PATH, 'submitty.json')) as open_file:
    OPEN_JSON = json.load(open_file)
SUBMITTY_DATA_DIR = OPEN_JSON['submitty_data_dir']
SUBMITTY_INSTALL_DIR = OPEN_JSON['submitty_install_dir']
AUTOGRADING_LOG_PATH = OPEN_JSON['autograding_log_path']
AUTOGRADING_STACKTRACE_PATH = os.path.join(OPEN_JSON['site_log_path'], 'autograding_stack_traces')

with open(os.path.join(CONFIG_PATH, 'submitty_users.json')) as open_file:
    OPEN_JSON = json.load(open_file)
DAEMON_UID = OPEN_JSON['daemon_uid']

INTERACTIVE_QUEUE = os.path.join(SUBMITTY_DATA_DIR, "to_be_graded_queue")
IN_PROGRESS_PATH = os.path.join(SUBMITTY_DATA_DIR, "grading")

JOB_ID = '~SHIP~'

def worker_folder(worker_name):
    return os.path.join(IN_PROGRESS_PATH, worker_name)

# ==================================================================================
def initialize(untrusted_queue):
    """
    Initializer function for all our processes. We get one untrusted user off our queue which
    we then set in our Process. We cannot recycle the shipper process as else the untrusted user
    we set for this process will be lost.

    :param untrusted_queue: multiprocessing.queues.Queue that contains all untrusted users left to
                            assign
    """
    multiprocessing.current_process().untrusted = untrusted_queue.get()

# ==================================================================================
def add_fields_to_autograding_worker_json(autograding_worker_json, entry):

    submitty_config  = os.path.join(SUBMITTY_INSTALL_DIR, 'config', 'version.json')

    try:
        with open(submitty_config) as infile:
            submitty_details = json.load(infile)
            installed_commit = submitty_details['installed_commit']
            most_recent_tag  = submitty_details['most_recent_git_tag']
    except FileNotFoundError as e:
        autograding_utils.log_stack_trace(AUTOGRADING_STACKTRACE_PATH, trace=traceback.format_exc())
        raise SystemExit("ERROR, could not locate the submitty.json:", e)

    autograding_worker_json[entry]['server_name']     = socket.getfqdn()
    autograding_worker_json[entry]['primary_commit']  = installed_commit
    autograding_worker_json[entry]['most_recent_tag'] = most_recent_tag
    return autograding_worker_json
# ==================================================================================
def update_all_foreign_autograding_workers():
    success_map = dict()
    all_workers_json = os.path.join(SUBMITTY_INSTALL_DIR, 'config', "autograding_workers.json")

    try:
        with open(all_workers_json, 'r') as infile:
            autograding_workers = json.load(infile)
    except FileNotFoundError as e:
        autograding_utils.log_stack_trace(AUTOGRADING_STACKTRACE_PATH, trace=traceback.format_exc())
        raise SystemExit("ERROR, could not locate autograding_workers_json :", e)

    for key, value in autograding_workers.items():
        if value['enabled'] == False:
            continue
        formatted_entry = {key: value}
        formatted_entry = add_fields_to_autograding_worker_json(formatted_entry, key)
        success = update_worker_json(key, formatted_entry)
        success_map[key] = success
    return success_map

# ==================================================================================
# Updates the autograding_worker.json in a workers autograding_TODO folder (tells it)
#   how many threads to be running on startup.
def update_worker_json(name, entry):

    fd, tmp_json_path = tempfile.mkstemp()
    foreign_json = os.path.join(SUBMITTY_DATA_DIR, "autograding_TODO", "autograding_worker.json")
    autograding_worker_to_ship = entry

    try:
        user = autograding_worker_to_ship[name]['username']
        host = autograding_worker_to_ship[name]['address']
    except Exception as e:
        print("ERROR: autograding_workers.json entry for {0} is malformatted. {1}".format(e, name))
        autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID, message="ERROR: autograding_workers.json entry for {0} is malformed. {1}".format(e, name))
        autograding_utils.log_stack_trace(AUTOGRADING_STACKTRACE_PATH, job_id=JOB_ID, trace=traceback.format_exc())
        return False

    #create a new temporary json with only the entry for the current machine.
    with open(tmp_json_path, 'w') as outfile:
        json.dump(autograding_worker_to_ship, outfile, sort_keys=True, indent=4)
    #if we are updating the current machine, we can just move the new json to the appropriate spot (no ssh needed)
    if host == "localhost":
        try:
            shutil.move(tmp_json_path,foreign_json)
            print("Successfully updated local autograding_TODO/autograding_worker.json")
            autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID, message="Successfully updated local autograding_TODO/autograding_worker.json")
            return True
        except Exception as e:
            autograding_utils.log_stack_trace(AUTOGRADING_STACKTRACE_PATH, job_id=JOB_ID, trace=traceback.format_exc())
            autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID, message="ERROR: could not mv to local autograding_TODO/autograding_worker.json due to the following error: "+str(e))
            print("ERROR: could not mv to local autograding_worker.json due to the following error: {0}".format(e))
            return False
        finally:
            os.close(fd)
    #if we are updating a foreign machine, we must connect via ssh and use sftp to update it.
    else:
        #try to establish an ssh connection to the host
        try:
            ssh = establish_ssh_connection(None, user, host, only_try_once = True)
        except Exception as e:
            autograding_utils.log_stack_trace(AUTOGRADING_STACKTRACE_PATH, job_id=JOB_ID, trace=traceback.format_exc())
            autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID, message="ERROR: could not ssh to {0}@{1} due to following error: {2}".format(user, host,str(e)))
            print("ERROR: could not ssh to {0}@{1} due to following error: {2}".format(user, host,str(e)))
            return False
        #try to copy the files over to the host
        try:
            sftp = ssh.open_sftp()

            sftp.put(tmp_json_path,foreign_json)

            sftp.close()
            print("Successfully forwarded autograding_worker.json to {0}".format(name))
            autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID, message="Successfully forwarded autograding_worker.json to {0}".format(name))
            success = True
        except Exception as e:
            autograding_utils.log_stack_trace(AUTOGRADING_STACKTRACE_PATH, job_id=JOB_ID, trace=traceback.format_exc())
            autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID, message="ERROR: could not sftp to foreign autograding_TODO/autograding_worker.json due to the following error: "+str(e))
            print("ERROR: could sftp to foreign autograding_TODO/autograding_worker.json due to the following error: {0}".format(e))
            success = False
        finally:
            os.close(fd)
            os.remove(tmp_json_path)
            sftp.close()
            ssh.close()
            return success

def establish_ssh_connection(my_name, user, host, only_try_once = False):
    """
    Returns a connected paramiko ssh session.
    Tries to connect until a connection is established, unless only_try_once
    is set to true. If only_try_once is true, raise whatever connection error is thrown.
    """
    connected = False
    ssh = None
    retry_delay = .1
    while not connected:
        ssh = paramiko.SSHClient()
        ssh.get_host_keys()
        ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        try:
            ssh.connect(hostname = host, username = user, timeout=10)
            connected = True
        except:
            if only_try_once:
                raise
            time.sleep(retry_delay)
            retry_relay = min(10, retry_delay * 2)
            autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID, message=f"{my_name} Could not establish connection with {user}@{host} going to re-try.")
            autograding_utils.log_stack_trace(AUTOGRADING_STACKTRACE_PATH, job_id=JOB_ID, trace=traceback.format_exc())
    return ssh

# ==================================================================================
def prepare_job(my_name,which_machine,which_untrusted,next_directory,next_to_grade,random_identifier):
    # verify the DAEMON_USER is running this script
    if not int(os.getuid()) == int(DAEMON_UID):
        autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID, message="ERROR: must be run by DAEMON_USER")
        raise SystemExit("ERROR: the submitty_autograding_shipper.py script must be run by the DAEMON_USER")

    if which_machine == 'localhost':
        address = which_machine
    else:
        address = which_machine.split('@')[1]

    # prepare the zip files
    try:
        autograding_zip_tmp,submission_zip_tmp = packer_unpacker.prepare_autograding_and_submission_zip(which_machine,which_untrusted,next_directory,next_to_grade)

        fully_qualified_domain_name = socket.getfqdn()
        servername_workername = "{0}_{1}".format(fully_qualified_domain_name, address)
        autograding_zip = os.path.join(SUBMITTY_DATA_DIR,"autograding_TODO",servername_workername+"_"+which_untrusted+"_autograding.zip")
        submission_zip = os.path.join(SUBMITTY_DATA_DIR,"autograding_TODO",servername_workername+"_"+which_untrusted+"_submission.zip")
        todo_queue_file = os.path.join(SUBMITTY_DATA_DIR,"autograding_TODO",servername_workername+"_"+which_untrusted+"_queue.json")

        with open(next_to_grade, 'r') as infile:
            queue_obj = json.load(infile)
            queue_obj["which_untrusted"] = which_untrusted
            queue_obj["which_machine"] = which_machine
            queue_obj["ship_time"] = dateutils.write_submitty_date(microseconds=True)
            queue_obj['identifier'] = random_identifier
    except Exception as e:
        autograding_utils.log_stack_trace(AUTOGRADING_STACKTRACE_PATH, job_id=JOB_ID, trace=traceback.format_exc())
        autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID, message="ERROR: failed preparing submission zip or accessing next to grade "+str(e))
        print("ERROR: failed preparing submission zip or accessing next to grade ", e)
        return False

    if address == "localhost":
        try:
            shutil.move(autograding_zip_tmp,autograding_zip)
            shutil.move(submission_zip_tmp,submission_zip)
            with open(todo_queue_file, 'w') as outfile:
                json.dump(queue_obj, outfile, sort_keys=True, indent=4)
        except Exception as e:
            autograding_utils.log_stack_trace(AUTOGRADING_STACKTRACE_PATH, job_id=JOB_ID, trace=traceback.format_exc())
            autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID, message="ERROR: could not move files due to the following error: "+str(e))
            print("ERROR: could not move files due to the following error: {0}".format(e))
            return False
    else:
        sftp = ssh = None
        try:
            user, host = which_machine.split("@")

            ssh = establish_ssh_connection(my_name, user, host)
            sftp = ssh.open_sftp()
            sftp.put(autograding_zip_tmp,autograding_zip)
            sftp.put(submission_zip_tmp,submission_zip)
            with open(todo_queue_file, 'w') as outfile:
                json.dump(queue_obj, outfile, sort_keys=True, indent=4)
            sftp.put(todo_queue_file, todo_queue_file)
            os.remove(todo_queue_file)
            print("Successfully forwarded files to {0}".format(my_name))
            success = True
        except Exception as e:
            autograding_utils.log_stack_trace(AUTOGRADING_STACKTRACE_PATH, job_id=JOB_ID, trace=traceback.format_exc())
            autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID, message="ERROR: could not move files due to the following error: "+str(e))
            print("Could not move files due to the following error: {0}".format(e))
            success = False
        finally:
            if sftp:
                sftp.close()
            if ssh:
                ssh.close()
            os.remove(autograding_zip_tmp)
            os.remove(submission_zip_tmp)
            return success

    # log completion of job preparation
    obj = packer_unpacker.load_queue_file_obj(JOB_ID,next_directory,next_to_grade)
    if "generate_output" not in obj:
        partial_path = os.path.join(obj["gradeable"],obj["who"],str(obj["version"]))
        item_name = os.path.join(obj["semester"],obj["course"],"submissions",partial_path)
    elif obj["generate_output"]:
        item_name = os.path.join(obj["semester"],obj["course"],"generated_output",obj["gradeable"])
    is_batch = "regrade" in obj and obj["regrade"]
    autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID, jobname=item_name, which_untrusted=which_untrusted,
                                    is_batch=is_batch, message="Prepared job for " + which_machine)
    return True


# ==================================================================================
# ==================================================================================
def unpack_job(which_machine,which_untrusted,next_directory,next_to_grade,random_identifier):

    # variables needed for logging
    obj = packer_unpacker.load_queue_file_obj(JOB_ID,next_directory,next_to_grade)
    if "generate_output" not in obj:
        partial_path = os.path.join(obj["gradeable"],obj["who"],str(obj["version"]))
        item_name = os.path.join(obj["semester"],obj["course"],"submissions",partial_path)
    elif obj["generate_output"]:
        item_name = os.path.join(obj["semester"],obj["course"],"generated_output")
    is_batch = "regrade" in obj and obj["regrade"]

    # verify the DAEMON_USER is running this script
    if not int(os.getuid()) == int(DAEMON_UID):
        autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID, message="ERROR: must be run by DAEMON_USER")
        raise SystemExit("ERROR: the submitty_autograding_shipper.py script must be run by the DAEMON_USER")

    if which_machine == 'localhost':
        address = which_machine
    else:
        address = which_machine.split('@')[1]

    fully_qualified_domain_name = socket.getfqdn()
    servername_workername = "{0}_{1}".format(fully_qualified_domain_name, address)
    target_results_zip = os.path.join(SUBMITTY_DATA_DIR,"autograding_DONE",servername_workername+"_"+which_untrusted+"_results.zip")
    target_done_queue_file = os.path.join(SUBMITTY_DATA_DIR,"autograding_DONE",servername_workername+"_"+which_untrusted+"_queue.json")

    if which_machine == "localhost":
        if not os.path.exists(target_done_queue_file):
            return False
        else:
          local_done_queue_file = target_done_queue_file
          local_results_zip = target_results_zip
    else:
        ssh = sftp = fd1 = fd2 = local_done_queue_file = local_results_zip = None
        try:
            user, host = which_machine.split("@")
            ssh = establish_ssh_connection(which_machine, user, host)
            sftp = ssh.open_sftp()
            fd1, local_done_queue_file = tempfile.mkstemp()
            fd2, local_results_zip     = tempfile.mkstemp()
            #remote path first, then local.
            sftp.get(target_done_queue_file, local_done_queue_file)
            sftp.get(target_results_zip, local_results_zip)
            #Because get works like cp rather tnan mv, we have to clean up.
            sftp.remove(target_done_queue_file)
            sftp.remove(target_results_zip)
            success = True
        #This is the normal case (still grading on the other end) so we don't need to print anything.
        except (socket.timeout, TimeoutError) as e:
            success = False
        except FileNotFoundError:
            # Remove results files
            for var in [local_results_zip, local_done_queue_file]:
                if var:
                    with contextlib.suppress(FileNotFoundError):
                        os.remove(var)
            success = False
        #In this more general case, we do want to print what the error was.
        #TODO catch other types of exception as we identify them.
        except Exception as e:
            autograding_utils.log_stack_trace(AUTOGRADING_STACKTRACE_PATH, job_id=JOB_ID, trace=traceback.format_exc())
            autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID, message="ERROR: Could not retrieve the file from the foreign machine "+str(e))
            print("ERROR: Could not retrieve the file from the foreign machine.\nERROR: {0}".format(e))

            # Remove results files
            for var in [local_results_zip, local_done_queue_file]:
                if var:
                    with contextlib.suppress(FileNotFoundError):
                        os.remove(var)

            success = False
        finally:
            # Close SSH connections
            for var in [sftp, ssh]:
                if var:
                    var.close()

            # Close file descriptors
            for var in [fd1, fd2]:
                if var:
                    try:
                        os.close(var)
                    except Exception:
                        pass

            if not success:
                return False

    try:
        with open(local_done_queue_file, 'r') as infile:
            local_done_queue_obj = json.load(infile)
        # Check to make certain that the job we received was the correct job
        if random_identifier != local_done_queue_obj['identifier']:
            success= False
            msg = f"{which_machine} returned a stale job (ids don't match). Discarding."
        else:
            # archive the results of grading
            success = packer_unpacker.unpack_grading_results_zip(which_machine,which_untrusted,local_results_zip)
            msg = "Unpacked job from " + which_machine
    except:
        autograding_utils.log_stack_trace(AUTOGRADING_STACKTRACE_PATH, job_id=JOB_ID, trace=traceback.format_exc())
        autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID,jobname=item_name,message="ERROR: Exception when unpacking zip. For more details, see traces entry.")
        with contextlib.suppress(FileNotFoundError):
            os.remove(local_results_zip)
        msg = "ERROR: failure returned from worker machine"
        success = False

    with contextlib.suppress(FileNotFoundError):
        os.remove(local_done_queue_file)

    print(msg)
    autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID, jobname=item_name, which_untrusted=which_untrusted, is_batch=is_batch, message=msg)
    return success


# ==================================================================================
def grade_queue_file(my_name, which_machine,which_untrusted,queue_file):
    """
    Oversees the autograding of single item from the queue

    :param queue_file: details of what to grade
    :param which_machine: name of machine to send this job to (might be "localhost")
    :param which_untrusted: specific untrusted user for this autograding job
    """

    my_dir,my_file=os.path.split(queue_file)
    pid = os.getpid()
    directory = os.path.dirname(os.path.realpath(queue_file))
    name = os.path.basename(os.path.realpath(queue_file))
    grading_file = os.path.join(directory, "GRADING_" + name)

    # Try to short-circuit this job. If it's possible, then great! Clean
    # everything up and return.
    if try_short_circuit(queue_file):
        grading_cleanup(queue_file, grading_file)
        return

    #TODO: break which_machine into id, address, and passphrase.
    
    try:
        # prepare the job
        shipper_counter=0
        random_identifier = string_utils.generate_random_string(64)
        #prep_job_success = prepare_job(my_name,which_machine, which_untrusted, my_dir, queue_file)
        while not prepare_job(my_name,which_machine, which_untrusted, my_dir, queue_file, random_identifier):
            time.sleep(5)

        prep_job_success = True
        
        if not prep_job_success:
            print (my_name, " ERROR unable to prepare job: ", queue_file)
            autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID, message=str(my_name)+" ERROR unable to prepare job: " + queue_file)

        else:
            # then wait for grading to be completed
            shipper_counter=0
            while not unpack_job(which_machine, which_untrusted, my_dir, queue_file, random_identifier):
                shipper_counter+=1
                time.sleep(1)
                if shipper_counter >= 10:
                    print (my_name,which_untrusted,"shipper wait for grade: ",queue_file)
                    shipper_counter=0

    except Exception as e:
        autograding_utils.log_stack_trace(AUTOGRADING_STACKTRACE_PATH, job_id=JOB_ID, trace=traceback.format_exc())
        print (my_name, " ERROR attempting to grade item: ", queue_file, " exception=",str(e))
        autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID, message=str(my_name)+" ERROR attempting to grade item: " + queue_file + " exception " + repr(e))

    grading_cleanup(queue_file, grading_file)


def grading_cleanup(queue_file, grading_file):
    # note: not necessary to acquire lock for these statements, but
    # make sure you remove the queue file, then the grading file
    try:
        os.remove(queue_file)
    except Exception as e:
        autograding_utils.log_stack_trace(AUTOGRADING_STACKTRACE_PATH, job_id=JOB_ID, trace=traceback.format_exc())
        print (my_name, " ERROR attempting to remove queue file: ", queue_file, " exception=",str(e))
        autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID, message=str(my_name)+" ERROR attempting to remove queue file: " + queue_file + " exception=" + str(e))
    try:
        os.remove(grading_file)
    except Exception as e:
        autograding_utils.log_stack_trace(AUTOGRADING_STACKTRACE_PATH, job_id=JOB_ID, trace=traceback.format_exc())
        print (my_name, " ERROR attempting to remove grading file: ", grading_file, " exception=",str(e))
        autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID, message=str(my_name)+" ERROR attempting to remove grading file: " + grading_file + " exception=" + str(e))


# ==================================================================================
# ==================================================================================
def valid_github_user_id(userid):
    # Github username may only contain alphanumeric characters or
    # hyphens. Github username cannot have multiple consecutive
    # hyphens. Github username cannot begin or end with a hyphen.
    # Maximum is 39 characters.
    #
    # NOTE: We only scrub the input for allowed characters.
    if (userid==''):
        # GitHub userid cannot be empty
        return False
    checklegal = lambda char: char.isalnum() or char == '-'
    filtered_userid = ''.join(list(filter(checklegal,userid)))
    if not userid == filtered_userid:
        return False
    return True


def valid_github_repo_id(repoid):
    # Only characters, numbers, dots, minus and underscore are allowed.
    if (repoid==''):
        # GitHub repoid cannot be empty
        return False
    checklegal = lambda char: char.isalnum() or char == '.' or char == '-' or char == '_'
    filtered_repoid = ''.join(list(filter(checklegal,repoid)))
    if not repoid == filtered_repoid:
        return False
    return True


def checkout_vcs_repo(my_file):
    print ("SHIPPER CHECKOUT VCS REPO ", my_file)

    with open(my_file, 'r') as infile:
        obj = json.load(infile)

    partial_path = os.path.join(obj["gradeable"],obj["who"],str(obj["version"]))
    course_dir = os.path.join(SUBMITTY_DATA_DIR, "courses", obj["semester"], obj["course"])
    submission_path = os.path.join(course_dir, "submissions", partial_path)
    checkout_path = os.path.join(course_dir, "checkout", partial_path)
    results_path = os.path.join(course_dir, "results", partial_path)

    is_vcs,vcs_type,vcs_base_url,vcs_subdirectory = packer_unpacker.get_vcs_info(SUBMITTY_DATA_DIR,obj["semester"],obj["course"],obj["gradeable"],obj["who"],obj["team"])

    # cleanup the previous checkout (if it exists)
    shutil.rmtree(checkout_path,ignore_errors=True)
    os.makedirs(checkout_path, exist_ok=True)

    job_id = "~VCS~"

    try:
        # If we are public or private github, we will have an empty vcs_subdirectory
        if vcs_subdirectory == '':
            with open (os.path.join(submission_path,".submit.VCS_CHECKOUT")) as submission_vcs_file:
                VCS_JSON = json.load(submission_vcs_file)
                git_user_id = VCS_JSON["git_user_id"]
                git_repo_id = VCS_JSON["git_repo_id"]
                if not valid_github_user_id(git_user_id):
                    raise Exception ("Invalid GitHub user/organization name: '"+git_user_id+"'")
                if not valid_github_repo_id(git_repo_id):
                    raise Exception ("Invalid GitHub repository name: '"+git_repo_id+"'")
                # construct path for GitHub
                vcs_path="https://www.github.com/"+git_user_id+"/"+git_repo_id

        # is vcs_subdirectory standalone or should it be combined with base_url?
        elif vcs_subdirectory[0] == '/' or '://' in vcs_subdirectory:
            vcs_path = vcs_subdirectory
        else:
            if '://' in vcs_base_url:
                vcs_path = urllib.parse.urljoin(vcs_base_url, vcs_subdirectory)
            else:
                vcs_path = os.path.join(vcs_base_url, vcs_subdirectory)

        # warning: --depth is ignored in local clones; use file:// instead.
        if not '://' in vcs_path:
            vcs_path = "file:///" + vcs_path

        Path(results_path+"/logs").mkdir(parents=True, exist_ok=True)
        checkout_log_file = os.path.join(results_path, "logs", "vcs_checkout.txt")

        # grab the submission time
        with open (os.path.join(submission_path,".submit.timestamp")) as submission_time_file:
            submission_string = submission_time_file.read().rstrip()


        # OPTION: A shallow clone with only the most recent commit
        # from the submission timestamp.
        #
        #   NOTE: if the student has set their computer time in the
        #     future, they could be confused that we don't grab their
        #     most recent code.
        #   NOTE: github repos currently fail (a bug?) with an error when
        #     --shallow-since is used:
        #     "fatal: The remote end hung up unexpectedly"
        #
        #clone_command = ['/usr/bin/git', 'clone', vcs_path, checkout_path, '--shallow-since='+submission_string, '-b', 'master']


        # OPTION: A shallow clone, with just the most recent commit.
        #
        #  NOTE: If the server is busy, it might take seconds or
        #     minutes for an available shipper to process the git
        #     clone, and thethe timestamp might be slightly late)
        #
        #  So we choose this option!  (for now)
        #
        clone_command = ['/usr/bin/git', 'clone', vcs_path, checkout_path, '--depth', '1', '-b', 'master']


        with open(checkout_log_file, 'a') as f:
            print("VCS CHECKOUT", file=f)
            print('vcs_base_url', vcs_base_url, file=f)
            print('vcs_subdirectory', vcs_subdirectory, file=f)
            print('vcs_path', vcs_path, file=f)
            print(' '.join(clone_command), file=f)
            print("\n====================================\n", file=f)

        # git clone may fail -- because repository does not exist,
        # or because we don't have appropriate access credentials
        try:
            subprocess.check_call(clone_command)
            os.chdir(checkout_path)

            # determine which version we need to checkout
            # if the repo is empty or the master branch does not exist, this command will fail
            try:
                what_version = subprocess.check_output(['git', 'rev-list', '-n', '1', 'master'])
                # old method:  when we had the full history, roll-back to a version by date
                #what_version = subprocess.check_output(['git', 'rev-list', '-n', '1', '--before="'+submission_string+'"', 'master'])
                what_version = str(what_version.decode('utf-8')).rstrip()
                if what_version == "":
                    # oops, pressed the grade button before a valid commit
                    shutil.rmtree(checkout_path, ignore_errors=True)
                # old method:
                #else:
                #    # and check out the right version
                #    subprocess.call(['git', 'checkout', '-b', 'grade', what_version])

                subprocess.call(['ls', '-lR', checkout_path], stdout=open(checkout_log_file, 'a'))
                print("\n====================================\n", file=open(checkout_log_file, 'a'))
                subprocess.call(['du', '-skh', checkout_path], stdout=open(checkout_log_file, 'a'))
                obj['revision'] = what_version

            # exception on git rev-list
            except subprocess.CalledProcessError as error:
                autograding_utils.log_message(AUTOGRADING_LOG_PATH, job_id,message="ERROR: failed to determine version on master branch " + str(error))
                os.chdir(checkout_path)
                with open(os.path.join(checkout_path,"failed_to_determine_version_on_master_branch.txt"),'w') as f:
                    print(str(error),file=f)
                    print("\n",file=f)
                    print("Check to be sure the repository is not empty.\n",file=f)
                    print("Check to be sure the repository has a master branch.\n",file=f)
                    print("And check to be sure the timestamps on the master branch are reasonable.\n",file=f)

        # exception on git clone
        except subprocess.CalledProcessError as error:
            autograding_utils.log_message(AUTOGRADING_LOG_PATH, job_id,message="ERROR: failed to clone repository " + str(error))
            os.chdir(checkout_path)
            with open(os.path.join(checkout_path,"failed_to_clone_repository.txt"),'w') as f:
                print(str(error),file=f)
                print("\n",file=f)
                print("Check to be sure the repository exists.\n",file=f)
                print("And check to be sure the submitty_daemon user has appropriate access credentials.\n",file=f)

    # exception in constructing full git repository url/path
    except Exception as error:
        autograding_utils.log_message(AUTOGRADING_LOG_PATH, job_id,message="ERROR: failed to construct valid repository url/path" + str(error))
        os.chdir(checkout_path)
        with open(os.path.join(checkout_path,"failed_to_construct_valid_repository_url.txt"),'w') as f:
            print(str(error),file=f)
            print("\n",file=f)
            print("Check to be sure the repository exists.\n",file=f)
            print("And check to be sure the submitty_daemon user has appropriate access credentials.\n",file=f)

    return obj

# ==================================================================================
def get_job(my_name,which_machine,my_capabilities,which_untrusted):
    """
    Pick a job from the queue.
    """

    time_get_job_begin = dateutils.get_current_time()

    folder = worker_folder(my_name)

    '''
    ----------------------------------------------------------------
    Our first priority is to perform any awaiting VCS checkouts

    Note: This design is imperfect:
    
        * If all shippers are busy working on long-running autograding
        tasks there will be a delay of seconds or minutes between
        a student pressing the submission button and clone happening.
        This is a minor exploit allowing them to theoretically
        continue working on their submission past the deadline for
        the time period of the delay.
        -- This is not a significant, practical problem.
    
        * If multiple and/or large git submissions arrive close
        together, this shipper job will be tied up performing these
        clone operations.  Because we don't release the lock, any
        other shippers that complete their work will also be blocked
        from either helping with the clones or tackling the next
        autograding job.
        -- Based on experience with actual submission patterns, we
            do not anticipate that this will be a significant
            bottleneck at this time.
    
        * If a git clone takes a very long time and/or hangs because of
        network problems, this could halt all work on the server.
        -- We'll need to monitor the production server.
    
    We plan to do a complete overhaul of the
    scheduler/shipper/worker and refactoring this design should be
    part of the project.
    ----------------------------------------------------------------
    '''

    # Grab all the VCS files currently in the folder...
    vcs_files = [str(f) for f in Path(folder).glob('VCS__*')]
    for f in vcs_files:
        vcs_file = f[len(folder)+1:]
        no_vcs_file = f[len(folder)+1+5:]
        # do the checkout
        updated_obj = checkout_vcs_repo(folder+"/"+vcs_file)
        # save the regular grading queue file
        with open(os.path.join(folder,no_vcs_file), "w") as queue_file:
            json.dump(updated_obj, queue_file)
        # cleanup the vcs queue file
        os.remove(folder+"/"+vcs_file)
    # ----------------------------------------------------------------


    # Grab all the files currently in the folder, sorted by creation
    # time, and put them in the queue to be graded
    files = [str(f) for f in Path(folder).glob('*')]
    files_and_times = list()
    for f in files:
        try:
            my_time = os.path.getctime(f)
        except:
            continue
        tup = (f, my_time)
        files_and_times.append(tup)

    files_and_times = sorted(files_and_times, key=operator.itemgetter(1))
    my_job=""

    for full_path_file, file_time in files_and_times:
        # get the file name (without the path)
        just_file = full_path_file[len(folder)+1:]

        # skip items that are already being graded
        if (just_file[0:8]=="GRADING_"):
            continue
        grading_file = os.path.join(folder,"GRADING_"+just_file)
        if grading_file in files:
            continue

        # skip items (very recently added!) that are already waiting for a VCS checkout
        if (just_file[0:5]=="VCS__"):
            continue

        # found something to do
        try:
            with open(full_path_file, 'r') as infile:
                queue_obj = json.load(infile)
        except:
            continue

        #Check to make sure that we are capable of grading this submission
        required_capabilities = queue_obj["required_capabilities"]
        if not required_capabilities in my_capabilities:
            continue

        # prioritize interactive jobs over (batch) regrades
        # if you've found an interactive job, exit early (since they are sorted by timestamp)
        if not "regrade" in queue_obj or not queue_obj["regrade"]:
            my_job = just_file
            break

        # otherwise it's a regrade, and if we don't already have a
        # job, take it, but we have to search the rest of the list
        if my_job == "":
            my_job = just_file

    if not my_job == "":
        grading_file = os.path.join(folder, "GRADING_" + my_job)
        # create the grading file
        with open(os.path.join(grading_file), "w") as queue_file:
                json.dump({"untrusted": which_untrusted, "machine": which_machine}, queue_file)

    time_get_job_end = dateutils.get_current_time()

    time_delta = time_get_job_end-time_get_job_begin
    if time_delta > datetime.timedelta(milliseconds=100):
        print (my_name, " WARNING: submitty_autograding shipper get_job time ", time_delta)
        autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID, message=str(my_name)+" WARNING: submitty_autograding shipper get_job time "+str(time_delta))

    return (my_job)


# ==================================================================================
# ==================================================================================
def shipper_process(my_name,my_data,full_address,which_untrusted):
    """
    Each shipper process spins in a loop, looking for a job that
    matches the capabilities of this machine, and then oversees the
    autograding of that job.  Interactive jobs are prioritized over
    batch (regrade) jobs.  If no jobs are available, the shipper waits
    on an event editing one of the queues.
    """

    which_machine   = full_address
    my_capabilities = my_data['capabilities']
    my_folder = worker_folder(my_name)

    # ignore keyboard interrupts in the shipper processes
    signal.signal(signal.SIGINT, signal.SIG_IGN)

    counter=0
    while True:
        try:
            my_job = get_job(my_name,which_machine,my_capabilities,which_untrusted)
            if not my_job == "":
                counter=0
                grade_queue_file(my_name,which_machine,which_untrusted,os.path.join(my_folder, my_job))
                continue
            else:
                if counter == 0 or counter >= 10:
                    # do not log this message, only print it to console when manual testing & debugging
                    print ("{0} {1}: no available job".format(my_name, which_untrusted))
                    counter=0
                counter+=1
                time.sleep(1)

        except Exception as e:
            autograding_utils.log_stack_trace(AUTOGRADING_STACKTRACE_PATH, job_id=JOB_ID, trace=traceback.format_exc())
            my_message = f"ERROR in get_job {which_machine} {which_untrusted} {str(e)}. For more details, see traces entry"
            autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID, message=my_message)
            time.sleep(1)



# ==================================================================================
# ==================================================================================
def is_testcase_submission_limit(testcase: dict) -> bool:
    """Check whether the given testcase object is a submission limit check."""
    return (testcase['type'] == 'FileCheck' and
            testcase['title'] == 'Submission Limit')


def can_short_circuit(config_obj: str) -> bool:
    """Check if a job can be short-circuited.

    Currently, a job can be short-circuited if either:

    * It has no autograding test cases
    * It has one autograding test case and that test case is the submission limit check.
    """

    testcases = config_obj['testcases']
    if len(testcases) == 0:
        # No test cases, so this is trivially short-circuitable.
        return True
    elif len(testcases) == 1:
        # We have only one test case; check if it's a submission limit check
        return is_testcase_submission_limit(testcases[0])
    else:
        return False


def check_submission_limit_penalty_inline(config_obj: dict,
                                          queue_obj: dict) -> dict:
    """Check if a submission violates the submission limit.
    
    Note that this function makes the assumption that the file being graded is
    short-circuitable (i.e. only has one test case; the sentinel "submission
    limit" test case).
    """

    ###########################################################################
    #
    # NOTE: Editing this? Make sure this function stays in-sync with the
    #       my_testcase.isSubmissionLimit() branch in
    #       grading/main_validator.cpp::ValidateATestCase()
    #
    ###########################################################################

    # regrade.py seems to make this into a str, so force into int
    subnum = int(queue_obj['version'])
    testcase = config_obj['testcases'][0]
    penalty = testcase['penalty']
    possible_points = testcase['points']
    max_submissions = testcase['max_submissions']

    excessive = max(subnum - max_submissions, 0)
    points = floor(excessive * penalty)
    points = max(points, possible_points)
    view_testcase = points != 0
    
    return {
        'test_name': f"Test 1 {testcase['title']}",
        'view_testcase': view_testcase,
        'points_awarded': points
    }


def history_short_circuit_helper(base_path: str,
                                 course_path: str,
                                 queue_obj: dict,
                                 gradeable_config_obj: dict) -> dict:
    """Figure out parameter values for just_write_grade_history."""
    user_path = os.path.join(course_path,
                             'submissions',
                             queue_obj['gradeable'],
                             queue_obj['who'])
    submit_timestamp_path = os.path.join(user_path,
                                         str(queue_obj['version']),
                                         '.submit.timestamp')
    user_assignment_access_path = os.path.join(user_path,
                                               'user_assignment_access.json')

    gradeable_deadline = gradeable_config_obj['date_due']
    with open(submit_timestamp_path) as fd:
        submit_timestamp = dateutils.read_submitty_date(fd.read().rstrip())
    submit_time = dateutils.write_submitty_date(submit_timestamp)
    gradeable_deadline_dt = dateutils.read_submitty_date(gradeable_deadline)

    seconds_late = int((submit_timestamp - gradeable_deadline_dt).total_seconds())

    first_access = ''
    access_duration = -1
    if os.path.exists(user_assignment_access_path):
        with open(user_assignment_access_path) as fd:
            obj = json.load(fd, object_pairs_hook=collections.OrderedDict)
        first_access = obj['page_load_history'][0]['time']
        first_access = dateutils.normalize_submitty_date(first_access)
        first_access_dt = dateutils.read_submitty_date(first_access)
        access_duration = int((submit_timestamp - first_access_dt).total_seconds())

    return {
        'gradeable_deadline': gradeable_deadline,
        'submission': submit_time,
        'seconds_late': seconds_late,
        'first_access': first_access,
        'access_duration': access_duration
    }


def try_short_circuit(queue_file: str) -> bool:
    """Attempt to short-circuit the job represented by the given queue file.

    Returns True if the job is short-circuitable and was successfully
    short-circuited.

    This function will first check if the queue file represents a gradeable
    that supports short-circuiting. If so, then this function will attempt to
    short-circuit the job within this child shipper process.

    Once the job is finished grading, then this function will write the history
    JSON file, zip up the results, and use the standard
    unpack_grading_results_zip function to place the results where they are
    expected. If something goes wrong during this process, then this function
    will return False, signalling to the caller that this job should be graded
    normally.
    """
    with open(queue_file) as fd:
        queue_obj = json.load(fd)

    course_path = os.path.join(SUBMITTY_DATA_DIR,
                               'courses',
                               queue_obj['semester'],
                               queue_obj['course'])

    config_path = os.path.join(course_path,
                               'config',
                               'complete_config',
                               f'complete_config_{queue_obj["gradeable"]}.json')

    gradeable_config_path = os.path.join(course_path,
                                         'config',
                                         'form',
                                         f'form_{queue_obj["gradeable"]}.json')

    with open(config_path) as fd:
        config_obj = json.load(fd)

    if not can_short_circuit(config_obj):
        return False

    job_id =''.join(random.choice(string.ascii_letters + string.digits) for _ in range(6))
    gradeable_id = f"{queue_obj['semester']}/{queue_obj['course']}/{queue_obj['gradeable']}"
    autograding_utils.log_message(AUTOGRADING_LOG_PATH,
                                  message=f"Short-circuiting {gradeable_id}",
                                  job_id=job_id)

    with open(gradeable_config_path) as fd:
        gradeable_config_obj = json.load(fd)

    # Augment the queue object
    base, path = os.path.split(queue_file)
    queue_time = packer_unpacker.get_queue_time(base, path)
    queue_time_longstring = dateutils.write_submitty_date(queue_time)
    grading_began = dateutils.get_current_time()
    wait_time = (grading_began - queue_time).total_seconds()

    queue_obj.update(queue_time=queue_time_longstring,
                     regrade=queue_obj.get('regrade', False),
                     waittime=wait_time,
                     job_id=job_id)

    base_dir = tempfile.mkdtemp()

    results_dir = os.path.join(base_dir, 'TMP_RESULTS')    
    results_json_path = os.path.join(results_dir, 'results.json')
    grade_txt_path = os.path.join(results_dir, 'grade.txt')
    queue_file_json_path = os.path.join(results_dir, 'queue_file.json')
    history_json_path = os.path.join(results_dir, 'history.json')
    logs_dir = os.path.join(results_dir, 'logs')

    os.makedirs(results_dir, exist_ok=True)
    os.makedirs(logs_dir, exist_ok=True)

    testcases = config_obj['testcases']
    testcase_outputs = []

    # This will probably always run, but it gives us a degree of
    # future-proofing.
    if len(testcases) > 0:
        testcase_outputs.append(
            check_submission_limit_penalty_inline(config_obj, queue_obj)
        )

    autograde_result_msg = write_grading_outputs(testcases, testcase_outputs,
                                                 results_json_path,
                                                 grade_txt_path)

    grading_finished = dateutils.get_current_time()
    grading_time = (grading_finished - grading_began).total_seconds()

    queue_obj['gradingtime'] = grading_time
    queue_obj['grade_result'] = autograde_result_msg
    queue_obj['which_untrusted'] = '(short-circuited)'

    # Save the augmented queue object
    with open(queue_file_json_path, 'w') as fd:
        json.dump(queue_obj, fd, indent=4, sort_keys=True,
                  separators=(',', ':'))

    h = history_short_circuit_helper(base_dir,
                                     course_path,
                                     queue_obj,
                                     gradeable_config_obj)

    try:
        autograding_utils.just_write_grade_history(history_json_path,
                                                   h['gradeable_deadline'],
                                                   h['submission'],
                                                   h['seconds_late'],
                                                   h['first_access'],
                                                   h['access_duration'],
                                                   queue_obj['queue_time'],
                                                   'BATCH' if queue_obj.get('regrade', False) else 'INTERACTIVE',
                                                   dateutils.write_submitty_date(grading_began),
                                                   int(queue_obj['waittime']),
                                                   dateutils.write_submitty_date(grading_finished),
                                                   int(grading_time),
                                                   autograde_result_msg,
                                                   queue_obj.get('revision', None))

        results_zip_path = os.path.join(base_dir, 'results.zip')
        autograding_utils.zip_my_directory(results_dir, results_zip_path)
        packer_unpacker.unpack_grading_results_zip('(short-circuit)',
                                                   '(short-circuit)',
                                                   results_zip_path)
    except Exception as e:
        autograding_utils.log_message(AUTOGRADING_LOG_PATH,
                                      message=f"Short-circuit failed for {gradeable_id} (check stack traces). Falling back to standard grade.",
                                      job_id=job_id)
        autograding_utils.log_stack_trace(AUTOGRADING_STACKTRACE_PATH,
                                          trace=traceback.format_exc(),
                                          job_id=job_id)
        return False
    finally:
        shutil.rmtree(base_dir, ignore_errors=True)

    autograding_utils.log_message(AUTOGRADING_LOG_PATH,
                                  message=f"Successfully short-circuited {gradeable_id}!",
                                  job_id=job_id)
    return True


def write_grading_outputs(testcases: list,
                          testcase_outputs: list,
                          results_json: str,
                          grade_txt: str) -> str:
    """Write the grading output data to the specified paths."""

    ###########################################################################
    #
    # NOTE: Editing this file? Make sure that this function stays in-sync with
    #       grading/main_validator.cpp::validateTestCase()
    #
    ###########################################################################

    results = {'testcases': testcase_outputs}
    with open(results_json, 'w') as fd:
        json.dump(results, fd, indent=4)

    max_auto_points = sum(tc['points'] for tc in testcases)
    max_nonhidden_auto_points = sum(
        tc['points'] for tc in testcases if not tc.get('hidden', False)
    )

    auto_points = sum(r['points_awarded'] for r in testcase_outputs)
    nonhidden_auto_points = sum(
        r['points_awarded']
        for r, tc in zip(testcase_outputs, testcases)
        if not tc.get('hidden', False)
    )
    
    with open(grade_txt, 'w') as fd:
        # Write each test case's individual output
        for i, tc in enumerate(testcases):
            title = tc['title']
            extra_credit = tc.get('extra_credit', False)
            max_points = tc['points']
            points = testcase_outputs[i]['points_awarded']
            fd.write(f"Testcase {i:3}: {title:<50} ")

            if extra_credit:
                if points > 0:
                    fd.write(f"+{points:2} points")
                else:
                     fd.write(' ' * 10)
            elif max_points < 0:
                if points < 0:
                    fd.write(f"{points:3} points")
                else:
                    fd.write(' ' * 10)
            else:
                fd.write(f"{points:3} / {max_points:3}  ")
            
            if tc.get('hidden', False):
                fd.write("  [ HIDDEN ]")
            fd.write('\n')
        
        # Write the final lines
        autograde_total_msg = f"{'Automatic grading total:':<64}{auto_points:3} /{max_auto_points:3}\n"
        fd.write(autograde_total_msg)
        fd.write(f"{'Non-hidden automatic grading total:':<64}{nonhidden_auto_points:3} /{max_nonhidden_auto_points:3}\n")
    return autograde_total_msg


# ==================================================================================
# ==================================================================================
def launch_shippers(worker_status_map):
    # verify the DAEMON_USER is running this script
    if not int(os.getuid()) == int(DAEMON_UID):
        raise SystemExit("ERROR: the submitty_autograding_shipper.py script must be run by the DAEMON_USER")
    autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID, message="grade_scheduler.py launched")

    for file_path in Path(SUBMITTY_DATA_DIR, "autograding_TODO").glob("untrusted*"):
        file_path = str(file_path)
        autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID, message="Remove autograding TODO file: " + file_path)
        os.remove(file_path)
    for file_path in Path(SUBMITTY_DATA_DIR, "autograding_DONE").glob("*"):
        file_path = str(file_path)
        autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID, message="Remove autograding DONE file: " + file_path)
        os.remove(file_path)

    # The names of the worker machines, the capabilities of each
    # worker machine, and the number of workers per machine are stored
    # in the autograding_workers json.
    try:
        autograding_workers_path = os.path.join(SUBMITTY_INSTALL_DIR, 'config', "autograding_workers.json")
        with open(autograding_workers_path, 'r') as infile:
            autograding_workers = json.load(infile)
    except Exception as e:
        autograding_utils.log_stack_trace(AUTOGRADING_STACKTRACE_PATH, job_id=JOB_ID, trace=traceback.format_exc())
        raise SystemExit("ERROR: could not locate the autograding workers json: {0}".format(e))

    # There must always be a primary machine, it may or may not have
    # autograding workers.
    if not "primary" in autograding_workers:
        raise SystemExit("ERROR: autograding_workers.json contained no primary machine.")

    # One (or more) of the machines must accept "default" jobs.
    default_present = False
    for name, machine in autograding_workers.items():
        if "default" in machine["capabilities"]:
            default_present = True
            break
    if not default_present:
        raise SystemExit("ERROR: autograding_workers.json contained no machine with default capabilities")

    # Launch a shipper process for every worker on the primary machine and each worker machine
    total_num_workers = 0
    processes = list()
    for name, machine in autograding_workers.items():
        thread_count = machine["num_autograding_workers"]
        
        # Cleanup previous in-progress submissions
        worker_folders = [worker_folder(f'{name}_{i}') for i in range(thread_count)]
        for folder in worker_folders:
            os.makedirs(folder, exist_ok=True)
            # Clear out in-progress files, as these will be re-done.
            for grading in Path(folder).glob('GRADING_*'):
                os.remove(grading)

        if worker_status_map[name] == False:
            print("{0} could not be reached, so we are not spinning up shipper threads.".format(name))
            autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID, message="{0} could not be reached, so we are not spinning up shipper threads.".format(name))
            continue
        if 'enabled' in machine and machine['enabled'] == False:
            print("{0} is disabled, so we are not spinning up shipper threads.".format(name))
            autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID, message="{0} is disabled, so we are not spinning up shipper threads.")
            continue
        try:
            full_address = ""
            if machine["address"] != "localhost":
                if machine["username"] == "":
                    raise SystemExit("ERROR: empty username for worker machine {0} ".format(machine["address"]))
                full_address = "{0}@{1}".format(machine["username"], machine["address"])
            else:
                if not machine["username"] == "":
                    raise SystemExit('ERROR: username for primary (localhost) must be ""')
                full_address = machine['address']

            num_workers_on_machine = machine["num_autograding_workers"]
            if num_workers_on_machine < 0:
                raise SystemExit("ERROR: num_workers_on_machine for '{0}' must be non-negative.".format(machine))

            single_machine_data = {name : machine}
            single_machine_data = add_fields_to_autograding_worker_json(single_machine_data, name)
        except Exception as e:
            autograding_utils.log_stack_trace(AUTOGRADING_STACKTRACE_PATH, job_id=JOB_ID, trace=traceback.format_exc())
            print("ERROR: autograding_workers.json entry for {0} contains an error: {1}. For more details, see trace entry.".format(name, e))
            autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID, message="ERROR: autograding_workers.json entry for {0} contains an error: {1} For more details, see trace entry.".format(name,e))
            continue
        # launch the shipper threads
        for i in range(thread_count):
            thread_name = f'{name}_{i}'
            u = "untrusted" + str(i).zfill(2)
            p = multiprocessing.Process(target=shipper_process,args=(thread_name,single_machine_data[name],full_address, u))
            p.start()
            processes.append((thread_name, p))
        total_num_workers += num_workers_on_machine

    # main monitoring loop
    try:
        while True:
            alive = 0
            for name, p in processes:
                if p.is_alive:
                    alive = alive+1
                else:
                    autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID, message="ERROR: process "+name+" is not alive")
            if alive != total_num_workers:
                autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID, message="ERROR: #shippers="+str(total_num_workers)+" != #alive="+str(alive))

            # Find which workers are currently idle, as well as any autograding
            # jobs which need to be scheduled.
            workers = [name for (name, p) in processes if p.is_alive]
            idle_workers = list(filter(
                lambda n: len(os.listdir(worker_folder(n))) == 0,
                workers))
            jobs = filter(os.path.isfile, 
                map(lambda f: os.path.join(INTERACTIVE_QUEUE, f), 
                    os.listdir(INTERACTIVE_QUEUE)))
            
            # Distribute available jobs randomly among workers currently idle.
            for job in jobs:
                if len(idle_workers) == 0:
                    break
                dest = random.choice(idle_workers)
                autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID, 
                    message=f"Pushing job {os.path.basename(job)} to {dest}.")
                shutil.move(job, worker_folder(dest))
                idle_workers.remove(dest)

            time.sleep(1)

    except KeyboardInterrupt:
        autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID, message="grade_scheduler.py keyboard interrupt")
        # just kill everything in this group id right now
        # NOTE:  this may be a bug if the grandchildren have a different group id and not be killed
        os.kill(-os.getpid(), signal.SIGKILL)

        # run this to check if everything is dead
        #    ps  xao pid,ppid,pgid,sid,comm,user  | grep untrust

        # everything's dead, including the main process so the rest of this will be ignored
        # but this was mostly working...

        # terminate the jobs
        for i in range(0,total_num_workers):
            processes[i].terminate()
        # wait for them to join
        for i in range(0,total_num_workers):
            processes[i].join()

    autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID, message="grade_scheduler.py terminated")


# ==================================================================================
if __name__ == "__main__":
    # verify the DAEMON_USER is running this script
    if not int(os.getuid()) == int(DAEMON_UID):
        raise SystemExit("ERROR: the submitty_autograding_shipper.py script must be run by the DAEMON_USER")

    worker_status_map = update_all_foreign_autograding_workers()
    launch_shippers(worker_status_map)
