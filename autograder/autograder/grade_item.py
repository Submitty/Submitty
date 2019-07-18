import configparser
import glob
import json
import os
import tempfile
import shutil
import subprocess
import stat
import time
import dateutil
import dateutil.parser
import urllib.parse
import string
import random
import zipfile
import sys
import traceback
from pwd import getpwnam

from submitty_utils import dateutils
from . import grade_items_logging, grade_item_main_runner, write_grade_history, CONFIG_PATH

with open(os.path.join(CONFIG_PATH, 'submitty.json')) as open_file:
    OPEN_JSON = json.load(open_file)
SUBMITTY_INSTALL_DIR = OPEN_JSON['submitty_install_dir']
SUBMITTY_DATA_DIR = OPEN_JSON['submitty_data_dir']

with open(os.path.join(CONFIG_PATH, 'submitty_users.json')) as open_file:
    OPEN_JSON = json.load(open_file)
DAEMON_UID = OPEN_JSON['daemon_uid']

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
def copy_contents_into(job_id,source,target,tmp_logs):
    if not os.path.isdir(target):
        grade_items_logging.log_message(job_id,message="ERROR: the target directory does not exist " + target)
        raise RuntimeError("ERROR: the target directory does not exist '", target, "'")
    if os.path.isdir(source):
        for item in os.listdir(source):
            if os.path.isdir(os.path.join(source,item)):
                if os.path.isdir(os.path.join(target,item)):
                    # recurse
                    copy_contents_into(job_id,os.path.join(source,item),os.path.join(target,item),tmp_logs)
                elif os.path.isfile(os.path.join(target,item)):
                    grade_items_logging.log_message(job_id,message="ERROR: the target subpath is a file not a directory '" + os.path.join(target,item) + "'")
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
                    grade_items_logging.log_stack_trace(job_id=job_id,trace=traceback.format_exc())
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
def untrusted_grant_rwx_access(which_untrusted,my_dir):
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

# ==================================================================================
# ==================================================================================

def prepare_for_autograding(my_directory, which_untrusted, autograding_zip_file, submission_zip_file)
    if os.path.exists(directory):
        untrusted_grant_rwx_access(which_untrusted, directory)
        add_permissions_recursive(directory,
                stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)
    # clean up old usage of this directory
    shutil.rmtree(directory,ignore_errors=True)
    os.mkdir(directory)
    tmp_autograding = os.path.join(my_directory,"TMP_AUTOGRADING")
    tmp_submission = os.path.join(my_directory,"TMP_SUBMISSION")
    tmp_work = os.path.join(my_directory,"TMP_WORK")
    os.mkdir(tmp_work)
    # Remove any and all containers left over from past runs.
    old_containers = subprocess.check_output(['docker', 'ps', '-aq', '-f', 'name={0}'.format(which_untrusted)]).split()

    for old_container in old_containers:
        subprocess.call(['docker', 'rm', '-f', old_container.decode('utf8')])

    # unzip autograding and submission folders
    try:
        unzip_this_file(autograding_zip_file,tmp_autograding)
        unzip_this_file(submission_zip_file,tmp_submission)
    except:
        raise
    finally:
        os.remove(autograding_zip_file)
        os.remove(submission_zip_file)

def grade_from_zip(my_autograding_zip_file, my_submission_zip_file, which_untrusted):

    os.chdir(SUBMITTY_DATA_DIR)
    tmp = os.path.join("/var/local/submitty/autograding_tmp/",which_untrusted,"tmp")
    
    prepare_for_autograding(tmp)

    tmp_autograding = os.path.join(tmp,"TMP_AUTOGRADING")
    tmp_submission = os.path.join(tmp,"TMP_SUBMISSION")
    tmp_work = os.path.join(tmp,"TMP_WORK")
    tmp_logs = os.path.join(tmp,"TMP_SUBMISSION","tmp_logs")
    submission_path = os.path.join(tmp_submission, "submission")

    with open(os.path.join(tmp_submission,"queue_file.json"), 'r') as infile:
        queue_obj = json.load(infile)
    waittime = queue_obj["waittime"]
    is_batch_job = queue_obj["regrade"]
    job_id = queue_obj["job_id"]
    
    item_name = os.path.join(queue_obj["semester"], queue_obj["course"], "submissions", 
                             queue_obj["gradeable"],queue_obj["who"],str(queue_obj["version"]))
    grade_items_logging.log_message(job_id,is_batch_job,which_untrusted,item_name,"wait:",waittime,"")

    with open(os.path.join(tmp_autograding, "complete_config.json"), 'r') as infile:
        complete_config_obj = json.load(infile)

    if complete_config_obj.get('one_part_only', False):
        allow_only_one_part(tmp_submission, 'submission', os.path.join(tmp_logs, "overall.txt"))
        
    # grab the submission time
    with open(os.path.join(tmp_submission, 'submission' ,".submit.timestamp"), 'r') as submission_time_file:
        submission_string = submission_time_file.read().rstrip()

    with open(os.path.join(tmp_autograding, "form.json"), 'r') as infile:
        gradeable_config_obj = json.load(infile)
    is_vcs = gradeable_config_obj["upload_type"] == "repository"

    # LOAD THE TESTCASES
    testcases = list()
    for t in complete_config_obj['testcases']:
        testcase_folder = os.path.join(tmp_work, "test{:02}".format(testcase_num))
        tmp_test = testcase.testcase(testcase_folder, queue_obj, complete_config_obj, t, tmp_work,
                                     which_untrusted, is_vcs, job_id, is_batch_job, tmp, testcases, submission_string)
        testcases.append( )

    with open(os.path.join(tmp_logs, "overall.txt"), 'a') as overall_log:
        os.chdir(tmp_work)

        # COMPILE THE SUBMITTED CODE
        print("====================================\nCOMPILATION STARTS", file=overall_log)
        for testcase in testcases:
            if testcase.type != 'Execution':
                testcase.execute()
                subprocess.call(['ls', '-lR', self.directory], stdout=overall_log)

        # EXECUTE
        print ("====================================\nRUNNER STARTS", file=overall_log)
        for testcase in testcases:
            if testcase.type == 'Execution':
                testcase.execute()
                subprocess.call(['ls', '-lR', self.directory], stdout=overall_log)
        # VALIDATE
        for testcase in testcases:
            print ("====================================\nVALIDATION STARTS", file=overall_log)
            testcase.validate()
            subprocess.call(['ls', '-lR', self.directory], stdout=overall_log)

        os.chdir(tmp)

        # ARCHIVE
        print ("====================================\nARCHIVING STARTS", file=overall_log)
        for testcase in testcases:
            testcase.archive_results(overall_log)
        subprocess.call(['ls', '-lR', '.'], stdout=open(tmp_logs + "/overall.txt", 'a'))
    
        # remove the test_input directory, so we don't archive it!
        shutil.rmtree(os.path.join(tmp_work,"test_input"))

        tmp_results = os.path.join(tmp,"TMP_RESULTS")

        history_file_tmp = os.path.join(tmp_submission,"history.json")
        history_file = os.path.join(tmp_results,"history.json")
        if os.path.isfile(history_file_tmp):
            shutil.move(history_file_tmp,history_file)
            # fix permissions
            ta_group_id = os.stat(tmp_results).st_gid
            os.chown(history_file,int(DAEMON_UID),ta_group_id)
            add_permissions(history_file,stat.S_IRGRP)
        grading_finished = dateutils.get_current_time()


        try:
            shutil.copy(os.path.join(tmp_work,"grade.txt"),tmp_results)
        except:
            with open(os.path.join(tmp_logs,"overall.txt"),'a') as f:
                print ("\n\nERROR: Grading incomplete -- Could not copy ",os.path.join(tmp_work,"grade.txt"))
            grade_items_logging.log_message(job_id,is_batch_job,which_untrusted,item_name,message="ERROR: grade.txt does not exist")
            grade_items_logging.log_stack_trace(job_id,is_batch_job,which_untrusted,item_name,trace=traceback.format_exc())

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

        gradingtime = (grading_finished-grading_began).total_seconds()

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
                grade_items_logging.log_message(job_id,is_batch_job,which_untrusted,item_name,message="ERROR: results.json read/write error")
                grade_items_logging.log_stack_trace(job_id,is_batch_job,which_untrusted,item_name,trace=traceback.format_exc())

        write_grade_history.just_write_grade_history(history_file,
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

        # zip up results folder
        filehandle, my_results_zip_file=tempfile.mkstemp()
        zip_my_directory(tmp_results,my_results_zip_file)
        os.close(filehandle)
        shutil.rmtree(tmp)

        grade_items_logging.log_message(job_id,is_batch_job,which_untrusted,item_name,"grade:",gradingtime,grade_result)
        return my_results_zip_file
# ==================================================================================
# ==================================================================================

if __name__ == "__main__":
    raise SystemExit('ERROR: Do not call this script directly')

