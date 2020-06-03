#!/usr/bin/env python3

import os
import time
import signal
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

from autograder import autograding_utils
from autograder import packer_unpacker

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
def prepare_job(my_name,which_machine,which_untrusted,next_directory,next_to_grade):
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
def unpack_job(which_machine,which_untrusted,next_directory,next_to_grade):

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
    # archive the results of grading
    try:
        success = packer_unpacker.unpack_grading_results_zip(which_machine,which_untrusted,local_results_zip)
    except:
        autograding_utils.log_stack_trace(AUTOGRADING_STACKTRACE_PATH, job_id=JOB_ID, trace=traceback.format_exc())
        autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID,jobname=item_name,message="ERROR: Exception when unpacking zip. For more details, see traces entry.")
        with contextlib.suppress(FileNotFoundError):
            os.remove(local_results_zip)
        success = False

    with contextlib.suppress(FileNotFoundError):
        os.remove(local_done_queue_file)

    msg = "Unpacked job from " + which_machine if success else "ERROR: failure returned from worker machine"
    print(msg)
    autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID, jobname=item_name, which_untrusted=which_untrusted, is_batch=is_batch, message=msg)
    return True


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

    #TODO: break which_machine into id, address, and passphrase.
    
    try:
        # prepare the job
        shipper_counter=0

        #prep_job_success = prepare_job(my_name,which_machine, which_untrusted, my_dir, queue_file)
        while not prepare_job(my_name,which_machine, which_untrusted, my_dir, queue_file):
            time.sleep(5)

        prep_job_success = True
        
        if not prep_job_success:
            print (my_name, " ERROR unable to prepare job: ", queue_file)
            autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID, message=str(my_name)+" ERROR unable to prepare job: " + queue_file)

        else:
            # then wait for grading to be completed
            shipper_counter=0
            while not unpack_job(which_machine, which_untrusted, my_dir, queue_file):
                shipper_counter+=1
                time.sleep(1)
                if shipper_counter >= 10:
                    print (my_name,which_untrusted,"shipper wait for grade: ",queue_file)
                    shipper_counter=0

    except Exception as e:
        autograding_utils.log_stack_trace(AUTOGRADING_STACKTRACE_PATH, job_id=JOB_ID, trace=traceback.format_exc())
        print (my_name, " ERROR attempting to grade item: ", queue_file, " exception=",str(e))
        autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID, message=str(my_name)+" ERROR attempting to grade item: " + queue_file + " exception " + repr(e))

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
                    autograding_utils.log_message(AUTOGRADING_LOG_PATH, JOB_ID,
                        message=f"{my_name} {which_untrusted}: no available job")
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
