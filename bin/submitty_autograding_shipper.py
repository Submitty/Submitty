#!/usr/bin/env python3

import os
import sys
import time
import signal
import json
import grade_items_logging
import grade_item
import shutil
import contextlib
import datetime
from submitty_utils import glob
import multiprocessing
from submitty_utils import dateutils, glob
import operator
import paramiko
import tempfile
import socket
# ==================================================================================
# these variables will be replaced by INSTALL_SUBMITTY.sh

# NOTE: DEPRECATED
# NUM_GRADING_SCHEDULER_WORKERS_string = "__INSTALL__FILLIN__NUM_GRADING_SCHEDULER_WORKERS__"
# NUM_GRADING_SCHEDULER_WORKERS_int    = int(NUM_GRADING_SCHEDULER_WORKERS_string)

AUTOGRADING_LOG_PATH="__INSTALL__FILLIN__AUTOGRADING_LOG_PATH__"
SUBMITTY_DATA_DIR = "__INSTALL__FILLIN__SUBMITTY_DATA_DIR__"
HWCRON_UID = "__INSTALL__FILLIN__HWCRON_UID__"
SUBMITTY_INSTALL_DIR= "__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__"
INTERACTIVE_QUEUE = os.path.join(SUBMITTY_DATA_DIR, "to_be_graded_queue")
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
def update_all_foreign_autograding_workers():
    all_workers_json = os.path.join(SUBMITTY_INSTALL_DIR, ".setup", "autograding_workers.json")
    try:
        with open(all_workers_json, 'r') as infile:
            autograding_workers = json.load(infile)
    except FileNotFoundError as e:
        raise SystemExit("ERROR, could not locate autograding_workers_json :", e)

    for key, value in autograding_workers.items():
        formatted_entry = {}
        formatted_entry[key] = value
        server_name = socket.getfqdn()
        formatted_entry[key]['server_name']=server_name
        update_foreign_autograding_worker_json(key, formatted_entry)

# ==================================================================================
def update_foreign_autograding_worker_json(name, entry):

    fd, tmp_json_path = tempfile.mkstemp()
    foreign_json   = os.path.join(SUBMITTY_DATA_DIR,"autograding_TODO","autograding_worker.json")
    autograding_worker_to_ship = entry

    try:
        user = autograding_worker_to_ship[name]['username']
        host = autograding_worker_to_ship[name]['address']
    except Exception as e:
        print("ERROR: autograding_workers.json entry for {0} is malformatted. {1}".format(e, name))
        grade_items_logging.log_message(message="ERROR: autograding_workers.json entry for {0} is malformatted. {1}".format(e, name))
        return

    #create a new temporary json with only the entry for the current machine.
    with open(tmp_json_path, 'w') as outfile:
        json.dump(autograding_worker_to_ship, outfile, sort_keys=True, indent=4)
    #if we are updating the current machine, we can just move the new json to the appropriate spot (no ssh needed)
    if host == "localhost":
        try:
            shutil.move(tmp_json_path,foreign_json)
            print("Successfully updated local autograding_TODO/autograding_worker.json")
            grade_items_logging.log_message(message="Successfully updated local autograding_TODO/autograding_worker.json")
        except Exception as e:
            grade_items_logging.log_message(message="ERROR: could not mv to local autograding_TODO/autograding_worker.json due to the following error: "+str(e))
            print("ERROR: could not mv to local autograding_worker.json due to the following error: {0}".format(e))
        finally:
            os.close(fd)
    #if we are updating a foreign machine, we must connect via ssh and use sftp to update it.
    else:
        try:
            ssh = paramiko.SSHClient()
            ssh.get_host_keys()
            ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())

            ssh.connect(hostname = host, username = user)
            sftp = ssh.open_sftp()

            sftp.put(tmp_json_path,foreign_json)

            sftp.close()
            print("Successfully forwarded autograding_worker.json to {0}".format(name))
            grade_items_logging.log_message(message="Successfully forwarded autograding_worker.json to {0}".format(name))
        except Exception as e:
            grade_items_logging.log_message(message="ERROR: could not sftp to foreign autograding_TODO/autograding_worker.json due to the following error: "+str(e))
            print("ERROR: could sftp to foreign autograding_TODO/autograding_worker.json due to the following error: {0}".format(e))
            success = False
        finally:
            os.close(fd)
            os.remove(tmp_json_path)
            sftp.close()
            ssh.close()

# ==================================================================================
def prepare_job(my_name,which_machine,which_untrusted,next_directory,next_to_grade):
    # verify the hwcron user is running this script
    if not int(os.getuid()) == int(HWCRON_UID):
        grade_items_logging.log_message(message="ERROR: must be run by hwcron")
        raise SystemExit("ERROR: the grade_item.py script must be run by the hwcron user")

    if which_machine == 'localhost':
        address = which_machine
    else:
        address = which_machine.split('@')[1]
    # prepare the zip files
    try:
        autograding_zip_tmp,submission_zip_tmp = grade_item.prepare_autograding_and_submission_zip(which_machine,which_untrusted,next_directory,next_to_grade)
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
        grade_items_logging.log_message(message="ERROR: failed preparing submission zip or accessing next to grade "+str(e))
        print("ERROR: failed preparing submission zip or accessing next to grade ", e)
        return False

    if address == "localhost":
        try:
            shutil.move(autograding_zip_tmp,autograding_zip)
            shutil.move(submission_zip_tmp,submission_zip)
            with open(todo_queue_file, 'w') as outfile:
                json.dump(queue_obj, outfile, sort_keys=True, indent=4)
        except Exception as e:
            grade_items_logging.log_message(message="ERROR: could not move files due to the following error: "+str(e))
            print("ERROR: could not move files due to the following error: {0}".format(e))
            return False
    else:
        try:
            user, host = which_machine.split("@")
            ssh = paramiko.SSHClient()
            ssh.get_host_keys()
            ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())

            ssh.connect(hostname = host, username = user)
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
            grade_items_logging.log_message(message="ERROR: could not move files due to the following error: "+str(e))
            print("Could not move files due to the following error: {0}".format(e))
            success = False
        finally:
            sftp.close()
            ssh.close()
            os.remove(autograding_zip_tmp)
            os.remove(submission_zip_tmp)
            return success
    return True


# ==================================================================================
# ==================================================================================
def unpack_job(which_machine,which_untrusted,next_directory,next_to_grade):
    # verify the hwcron user is running this script
    if not int(os.getuid()) == int(HWCRON_UID):
        grade_items_logging.log_message(message="ERROR: must be run by hwcron")
        raise SystemExit("ERROR: the grade_item.py script must be run by the hwcron user")

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
        user, host = which_machine.split("@")
        ssh = paramiko.SSHClient()
        ssh.get_host_keys()
        ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())

        try:
            ssh.connect(hostname = host, username = user)

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
        except FileNotFoundError:
            os.remove(local_results_zip)
            os.remove(local_done_queue_file)
            success = False
        #In this more general case, we do want to print what the error was.
        #TODO catch other types of exception as we identify them.
        except Exception as e:
            grade_items_logging.log_message(message="ERROR: Could not retrieve the file from the foreign machine "+str(e))
            print("ERROR: Could not retrieve the file from the foreign machine.\nERROR: {0}".format(e))
            os.remove(local_results_zip)
            os.remove(local_done_queue_file)
            success = False
        finally:
            os.close(fd1)
            os.close(fd2)
            sftp.close()
            ssh.close()
            if not success:
                return False
    # archive the results of grading
    try:
        grade_item.unpack_grading_results_zip(which_machine,which_untrusted,local_results_zip)
    except:
        grade_items_logging.log_message(jobname=next_to_grade,message="ERROR: Exception when unpacking zip")
        with contextlib.suppress(FileNotFoundError):
            os.remove(local_results_zip)

    with contextlib.suppress(FileNotFoundError):
        os.remove(local_done_queue_file)
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

    #TODO: breach which_machine into id, address, and passphrase.
    
    try:
        # prepare the job
        shipper_counter=0
        while not prepare_job(my_name,which_machine, which_untrusted, my_dir, queue_file):
            shipper_counter = 0
            time.sleep(1)
            if shipper_counter >= 10:
                prints(my_name, which_untrusted, "shipper prep loop: ",queue_file)
                shipper_counter=0

        # then wait for grading to be completed
        shipper_counter=0
        while not unpack_job(which_machine, which_untrusted, my_dir, queue_file):
            shipper_counter+=1
            time.sleep(1)
            if shipper_counter >= 10:
                print (my_name,which_untrusted,"shipper wait for grade: ",queue_file)
                shipper_counter=0

    except Exception as e:
        print (my_name, " ERROR attempting to grade item: ", queue_file, " exception=",str(e))
        grade_items_logging.log_message(message=str(my_name)+" ERROR attempting to grade item: " + queue_file + " exception " + repr(e))

    # note: not necessary to acquire lock for these statements, but
    # make sure you remove the queue file, then the grading file
    try:
        os.remove(queue_file)
    except Exception as e:
        print (my_name, " ERROR attempting to remove queue file: ", queue_file, " exception=",str(e))
        grade_items_logging.log_message(message=str(my_name)+" ERROR attempting to remove queue file: " + queue_file + " exception=" + str(e))
    try:
        os.remove(grading_file)
    except Exception as e:
        print (my_name, " ERROR attempting to remove grading file: ", grading_file, " exception=",str(e))
        grade_items_logging.log_message(message=str(my_name)+" ERROR attempting to remove grading file: " + grading_file + " exception=" + str(e))


# ==================================================================================
def get_job(my_name,which_machine,my_capabilities,which_untrusted,overall_lock):
    """
    Picks a job from the queue

    :param overall_lock: a lock on the directory containing all queue files
    """

    time_get_job_begin = dateutils.get_current_time()

    overall_lock.acquire()
    folder= INTERACTIVE_QUEUE

    # Grab all the files currently in the folder, sorted by creation
    # time, and put them in the queue to be graded
    files = glob.glob(os.path.join(folder, "*"))
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
        open(os.path.join(grading_file), "w").close()

    overall_lock.release()

    time_get_job_end = dateutils.get_current_time()

    time_delta = time_get_job_end-time_get_job_begin
    if time_delta > datetime.timedelta(milliseconds=100):
        print (my_name, " WARNING: submitty_autograding shipper get_job time ", time_delta)
        grade_items_logging.log_message(message=str(my_name)+" WARNING: submitty_autograding shipper get_job time "+str(time_delta))

    return my_job


# ==================================================================================
# ==================================================================================
def shipper_process(my_name, which_machine,my_capabilities,which_untrusted,overall_lock):
    """
    Each shipper process spins in a loop, looking for a job that
    matches the capabilities of this machine, and then oversees the
    autograding of that job.  Interactive jobs are prioritized over
    batch (regrade) jobs.  If no jobs are available, the shipper waits
    on an event editing one of the queues.
    """

    # ignore keyboard interrupts in the shipper processes
    signal.signal(signal.SIGINT, signal.SIG_IGN)

    counter=0

    while True:
        try:
            my_job = get_job(my_name,which_machine,my_capabilities,which_untrusted,overall_lock)
            if not my_job == "":
                counter=0
                grade_queue_file(my_name,which_machine,which_untrusted,os.path.join(INTERACTIVE_QUEUE,my_job))
                continue
            else:
                if counter == 0 or counter >= 10:
                    print (my_name,which_untrusted,"no available job")
                    counter=0
                counter+=1
                time.sleep(1)

        except Exception as e:
            my_message = "ERROR in get_job " + which_machine + " " + which_untrusted + " " + str(e)
            print (my_message)
            grade_items_logging.log_message(message=my_message)
            time.sleep(1)



# ==================================================================================
# ==================================================================================
def launch_shippers():

    # verify the hwcron user is running this script
    if not int(os.getuid()) == int(HWCRON_UID):
        raise SystemExit("ERROR: the grade_item.py script must be run by the hwcron user")

    grade_items_logging.log_message(message="grade_scheduler.py launched")

    # Clean up old files from previous shipping/autograding (any
    # partially completed work will be re-done)
    for file_path in glob.glob(os.path.join(INTERACTIVE_QUEUE, "GRADING_*")):
        grade_items_logging.log_message(message="Remove old queue file: " + file_path)
        os.remove(file_path)

    for file_path in glob.glob(os.path.join(SUBMITTY_DATA_DIR,"autograding_TODO","unstrusted*")):
        grade_items_logging.log_message(message="Remove autograding TODO file: " + file_path)
        os.remove(file_path)
    for file_path in glob.glob(os.path.join(SUBMITTY_DATA_DIR,"autograding_DONE","*")):
        grade_items_logging.log_message(message="Remove autograding DONE file: " + file_path)
        os.remove(file_path)

    # this lock will be used to edit the queue or new job event
    overall_lock = multiprocessing.Lock()

    # The names of the worker machines, the capabilities of each
    # worker machine, and the number of workers per machine are stored
    # in the autograding_workers json.
    try:
        autograding_workers_path = os.path.join(SUBMITTY_INSTALL_DIR, ".setup", "autograding_workers.json")
        with open(autograding_workers_path, 'r') as infile:
            autograding_workers = json.load(infile)
    except Exception as e:
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

    # Launch a shipper process for every work on the primary machine and each worker machine
    total_num_workers = 0
    processes = list()
    for name, machine in autograding_workers.items():
        try:
            which_machine=machine["address"]
            if which_machine != "localhost":
                if machine["username"] == "":
                    raise SystemExit("ERROR: empty username for worker machine '" + which_machine + "'")
                which_machine = "{0}@{1}".format(machine["username"], machine["address"])
            elif not machine["username"] == "":
                Raise('ERROR: username for primary (localhost) must be ""')
            num_workers_on_machine = machine["num_autograding_workers"]
            if num_workers_on_machine < 0:
                raise SystemExit("ERROR: num_workers_on_machine for '" + which_machine + "' must be non-negative.")
            my_capabilities = machine["capabilities"]
        except Exception as e:
            print("ERROR: autograding_workers.json entry for {0} contains an error: {1}".format(name, e))
            grade_items_logging.log_message(message="ERROR: autograding_workers.json entry for {0} contains an error: {1}".format(name,e))
            continue
        # launch the shipper threads
        for i in range(0,num_workers_on_machine):
            u = "untrusted" + str(i).zfill(2)
            p = multiprocessing.Process(target=shipper_process,args=(name,which_machine,my_capabilities,u,overall_lock))
            p.start()
            processes.append(p)
        total_num_workers += num_workers_on_machine

    # main monitoring loop
    try:
        while True:
            alive = 0
            for i in range(0,total_num_workers):
                if processes[i].is_alive:
                    alive = alive+1
                else:
                    grade_items_logging.log_message(message="ERROR: process "+str(i)+" is not alive")
            if alive != total_num_workers:
                grade_items_logging.log_message(message="ERROR: #shippers="+str(total_num_workers)+" != #alive="+str(alive))
            #print ("shippers= ",total_num_workers,"  alive=",alive)
            time.sleep(1)

    except KeyboardInterrupt:
        grade_items_logging.log_message(message="grade_scheduler.py keyboard interrupt")
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

    grade_items_logging.log_message(message="grade_scheduler.py terminated")


# ==================================================================================
if __name__ == "__main__":
    update_all_foreign_autograding_workers()
    launch_shippers()
