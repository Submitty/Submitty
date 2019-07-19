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
import glob

def just_write_grade_history(json_file,assignment_deadline,submission_time,
                             seconds_late,queue_time,batch_regrade,grading_began,
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
    now = dateutils.get_current_time()
    datefile = datetime.strftime(now, "%Y%m%d")+".txt"
    autograding_log_file = os.path.join(log_path, datefile)
    easy_to_read_date = dateutils.write_submitty_date(now, True)
    batch_string = "BATCH" if is_batch else ""
    if elapsed_time == "":
        elapsed_time = -1
    elapsed_time_string = "" if elapsed_time < 0 else '{:9.3f}'.format(elapsed_time)
    time_unit = "" if elapsed_time < 0 else "sec"
    with open(autograding_log_file, 'a') as myfile:
        try:
            fcntl.flock(myfile,fcntl.LOCK_EX | fcntl.LOCK_NB)
            print("%s | %6s | %5s | %11s | %-75s | %-6s %9s %3s | %s"
                  % (easy_to_read_date, job_id, batch_string, which_untrusted,
                     jobname, timelabel, elapsed_time_string, time_unit, message),
                  file=myfile)
            fcntl.flock(myfile, fcntl.LOCK_UN)
        except:
            print("Could not gain a lock on the log file.")


def log_stack_trace(log_path, job_id="UNKNOWN", is_batch=False, which_untrusted="", jobname="", timelabel="", elapsed_time=-1, trace=""):
    now = dateutils.get_current_time()
    datefile = "stack_traces_{0}.txt".format(datetime.strftime(now, "%Y%m%d"))
    autograding_log_file = os.path.join(log_path, datefile)
    easy_to_read_date = dateutils.write_submitty_date(now, True)
    batch_string = "BATCH" if is_batch else ""
    if elapsed_time == "":
        elapsed_time = -1
    elapsed_time_string = "" if elapsed_time < 0 else '{:9.3f}'.format(elapsed_time)
    time_unit = "" if elapsed_time < 0 else "sec"
    with open(autograding_log_file, 'a') as myfile:
        try:
            fcntl.flock(myfile,fcntl.LOCK_EX | fcntl.LOCK_NB)
            print("%s | %6s | %5s | %11s | %-75s | %-6s %9s %3s |\n%s"
                  % (easy_to_read_date, job_id, batch_string, which_untrusted,
                     jobname, timelabel, elapsed_time_string, time_unit, trace),
                  file=myfile)
            fcntl.flock(myfile, fcntl.LOCK_UN)
        except:
            print("Could not gain a lock on the log file.")


# ==================================================================================
#
#  ARCHIVAL AND PERMISSIONS FUNCTIONS
#
# ==================================================================================

def prepare_directory_for_autograding(working_directory, user_id_of_runner, autograding_zip_file, submission_zip_file):
    # clean up old usage of this directory
    shutil.rmtree(working_directory,ignore_errors=True)
    os.mkdir(working_directory)

    tmp_autograding = os.path.join(working_directory,"TMP_AUTOGRADING")
    tmp_submission = os.path.join(working_directory,"TMP_SUBMISSION")
    tmp_work = os.path.join(working_directory,"TMP_WORK")
    tmp_logs = os.path.join(working_directory,"TMP_SUBMISSION","tmp_logs")

    os.mkdir(tmp_work)
    # Remove any and all containers left over from past runs.
    old_containers = subprocess.check_output(['docker', 'ps', '-aq', '-f', 'name={0}'.format(user_id_of_runner)]).split()

    for old_container in old_containers:
        subprocess.call(['docker', 'rm', '-f', old_container.decode('utf8')])

    # unzip autograding and submission folders
    try:
        unzip_this_file(autograding_zip_file,tmp_autograding)
        unzip_this_file(submission_zip_file,tmp_submission)
    except:
        raise

    with open(os.path.join(tmp_autograding, "complete_config.json"), 'r') as infile:
        complete_config_obj = json.load(infile)

    if complete_config_obj.get('one_part_only', False):
        allow_only_one_part(tmp_submission, 'submission', os.path.join(tmp_logs, "overall.txt"))


def archive_autograding_results(working_directory, queue_obj, log_path, stack_trace_log_path, owner_uid):

    tmp_autograding = os.path.join(working_directory,"TMP_AUTOGRADING")
    tmp_submission = os.path.join(working_directory,"TMP_SUBMISSION")
    tmp_work = os.path.join(working_directory,"TMP_WORK")
    tmp_logs = os.path.join(working_directory,"TMP_SUBMISSION","tmp_logs")
    tmp_results = os.path.join(working_directory,"TMP_RESULTS")
    submission_path = os.path.join(tmp_submission, "submission")

    # grab the submission time
    with open(os.path.join(tmp_submission, 'submission' ,".submit.timestamp"), 'r') as submission_time_file:
        submission_string = submission_time_file.read().rstrip()

    # remove the test_input directory, so we don't archive it!
    shutil.rmtree(os.path.join(tmp_work,"test_input"))


    history_file_tmp = os.path.join(tmp_submission,"history.json")
    history_file = os.path.join(tmp_results,"history.json")
    if os.path.isfile(history_file_tmp):
        shutil.move(history_file_tmp,history_file)
        # fix permissions
        ta_group_id = os.stat(tmp_results).st_gid
        os.chown(history_file,int(owner_uid),ta_group_id)
        add_permissions(history_file,stat.S_IRGRP)
    grading_finished = dateutils.get_current_time()


    try:
        shutil.copy(os.path.join(tmp_work, "grade.txt"), tmp_results)
    except:
        with open(os.path.join(tmp_logs,"overall.txt"),'a') as f:
            print ("\n\nERROR: Grading incomplete -- Could not copy ",os.path.join(tmp_work,"grade.txt"))
        log_message(log_path, job_id, is_batch_job, which_untrusted, item_name, message="ERROR: grade.txt does not exist")
        log_stack_trace(stack_trace_log_path, job_id, is_batch_job, which_untrusted, item_name, trace=traceback.format_exc())

    gradeable_deadline_string = gradeable_config_obj["date_due"]
    
    submission_datetime = dateutils.read_submitty_date(submission_string)
    gradeable_deadline_datetime = dateutils.read_submitty_date(gradeable_deadline_string)
    gradeable_deadline_longstring = dateutils.write_submitty_date(gradeable_deadline_datetime)
    submission_longstring = dateutils.write_submitty_date(submission_datetime)
    seconds_late = int((submission_datetime-gradeable_deadline_datetime).total_seconds())
    # note: negative = not late

    grading_finished_longstring = dateutils.write_submitty_date(grading_finished)

    with open(os.path.join(tmp_submission,".grading_began"), 'r') as f:
        grading_began_longstring = f.read()
    grading_began = dateutils.read_submitty_date(grading_began_longstring)

    gradingtime = (grading_finished - grading_began).total_seconds()

    queue_obj["gradingtime"]=gradingtime
    queue_obj["grade_result"]=grade_result
    queue_obj["which_untrusted"]=which_untrusted

    with open(os.path.join(tmp_results,"queue_file.json"),'w') as outfile:
        json.dump(queue_obj,outfile,sort_keys=True,indent=4,separators=(',', ': '))

    try:
        shutil.move(os.path.join(tmp_work, "results.json"), os.path.join(tmp_results, "results.json"))
    except:
        with open(os.path.join(tmp_logs,"overall.txt"),'a') as f:
            print ("\n\nERROR: Grading incomplete -- Could not open/write ",os.path.join(tmp_work,"results.json"))
            log_message(log_path, job_id,is_batch_job,which_untrusted,item_name,message="ERROR: results.json read/write error")
            log_stack_trace(stack_trace_log_path, job_id,is_batch_job,which_untrusted,item_name,trace=traceback.format_exc())

    autograding_utils.just_write_grade_history(history_file,
                                                 gradeable_deadline_longstring,
                                                 submission_longstring,
                                                 seconds_late,
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

    # save the logs!
    shutil.copytree(tmp_logs,os.path.join(tmp_results,"logs"))
    log_message(log_path, job_id,is_batch_job,which_untrusted,item_name,"grade:",gradingtime,grade_result)


# Only used here.
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
        print('Clean up multiple parts', file=log)
        for entry in sorted(os.listdir(path)):
            full_path = os.path.join(path, entry)
            if not os.path.isdir(full_path) or not entry.startswith('part'):
                continue
            count = len(os.listdir(full_path))
            print('{}: {}'.format(entry, count), file=log)
            if count > 0:
                clean_directories.append(full_path)

        if len(clean_directories) > 1:
            print("ERROR!  Student submitted to multiple parts in violation of instructions.\n"
                  "Removing files from all but first non empty part.", file=log)

            for i in range(1, len(clean_directories)):
                print("REMOVE: {}".format(clean_directories[i]), file=log)
                for entry in os.listdir(clean_directories[i]):
                    print("  -> {}".format(entry), file=log)
                shutil.rmtree(clean_directories[i])

# go through the testcase folder (e.g. test01/) and remove anything
# that matches the test input (avoid archiving copies of these files!)
def remove_test_input_files(overall_log,testcase_folder):
    test_input_path = os.path.join(tmp_autograding, "test_input")
    for path, subdirs, files in os.walk(test_input_path):
        for name in files:
            relative = path[len(test_input_path)+1:]
            my_file = os.path.join(testcase_folder, relative, name)
            if os.path.isfile(my_file):
                print ("removing (likely) stale test_input file: ", my_file, file=overall_log)
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


# copy files that match one of the patterns from the source directory
# to the target directory.  
def pattern_copy(what,patterns,source,target,tmp_logs):
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
def untrusted_grant_rwx_access(which_untrusted, my_dir):
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

  source_testcase = os.path.join(str(os.getcwd()), '..', source_testcase)
  destination_testcase = os.path.join(str(os.getcwd()), '..',destination_testcase)

  if not os.path.isdir(source_testcase):
    raise RuntimeError("ERROR: The directory {0} does not exist.".format(source_testcase))

  if not os.path.isdir(destination_testcase):
    raise RuntimeError("ERROR: The directory {0} does not exist.".format(destination_testcase))

  source = os.path.join(source_testcase, source_directory)
  target = os.path.join(destination_testcase,destination)

  # The target without the potential executable.
  target_base = '/'.join(target.split('/')[:-1])

  # If the source is a directory, we copy the entire thing into the
  # target.
  if os.path.isdir(source):
    # We must copy from directory to directory 
    copy_contents_into(job_id,source,target,tmp_logs, log_path, stack_trace_log_path)

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
