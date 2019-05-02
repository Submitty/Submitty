import configparser
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
import socket
import zipfile
import sys
import traceback
from pwd import getpwnam

from submitty_utils import dateutils, glob
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


def touch(my_file):
    with open(my_file,'a') as tmp:
        os.utime(my_file, None)

                
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

def zip_my_directory(path,zipfilename):
    zipf = zipfile.ZipFile(zipfilename,'w',zipfile.ZIP_DEFLATED)
    for root,dirs,files in os.walk(path):
        for my_file in files:
            relpath = root[len(path)+1:]
            zipf.write(os.path.join(root,my_file),os.path.join(relpath,my_file))
    zipf.close()


def unzip_this_file(zipfilename,path):
    if not os.path.exists(zipfilename):
        raise RuntimeError("ERROR: zip file does not exist '", zipfilename, "'")
    zip_ref = zipfile.ZipFile(zipfilename,'r')
    zip_ref.extractall(path)
    zip_ref.close()


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
# ==================================================================================
# ==================================================================================

# go through the testcase folder (e.g. test01/) and remove anything
# that matches the test input (avoid archiving copies of these files!)
def remove_test_input_files(overall_log,test_input_path,testcase_folder):
    with open(overall_log,'a') as f:
        for path, subdirs, files in os.walk(test_input_path):
            for name in files:
                relative = path[len(test_input_path)+1:]
                my_file = os.path.join(testcase_folder, relative, name)
                if os.path.isfile(my_file):
                    print ("removing (likely) stale test_input file: ", my_file, file=f)
                    os.remove(my_file)


def grade_from_zip(my_autograding_zip_file,my_submission_zip_file,which_untrusted):

    os.chdir(SUBMITTY_DATA_DIR)
    tmp = os.path.join("/var/local/submitty/autograding_tmp/",which_untrusted,"tmp")

    if os.path.exists(tmp):
        untrusted_grant_rwx_access(which_untrusted, tmp)
        add_permissions_recursive(tmp,
                  stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                  stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                  stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)

    # Remove any and all containers left over from past runs.
    old_containers = subprocess.check_output(['docker', 'ps', '-aq', '-f', 'name={0}'.format(which_untrusted)]).split()

    for old_container in old_containers:
        subprocess.call(['docker', 'rm', '-f', old_container.decode('utf8')])

    # clean up old usage of this directory
    shutil.rmtree(tmp,ignore_errors=True)
    os.mkdir(tmp)

    which_machine=socket.gethostname()

    # unzip autograding and submission folders
    tmp_autograding = os.path.join(tmp,"TMP_AUTOGRADING")
    tmp_submission = os.path.join(tmp,"TMP_SUBMISSION")
    try:
        unzip_this_file(my_autograding_zip_file,tmp_autograding)
        unzip_this_file(my_submission_zip_file,tmp_submission)
    except:
        raise
    os.remove(my_autograding_zip_file)
    os.remove(my_submission_zip_file)

    tmp_logs = os.path.join(tmp,"TMP_SUBMISSION","tmp_logs")

    queue_file = os.path.join(tmp_submission,"queue_file.json")
    with open(queue_file, 'r') as infile:
        queue_obj = json.load(infile)

    queue_time_longstring = queue_obj["queue_time"]
    waittime = queue_obj["waittime"]
    is_batch_job = queue_obj["regrade"]
    job_id = queue_obj["job_id"]
    is_batch_job_string = "BATCH" if is_batch_job else "INTERACTIVE"
    revision = queue_obj.get("revision", None)

    partial_path = os.path.join(queue_obj["gradeable"],queue_obj["who"],str(queue_obj["version"]))
    item_name = os.path.join(queue_obj["semester"],queue_obj["course"],"submissions",partial_path)

    grade_items_logging.log_message(job_id,is_batch_job,which_untrusted,item_name,"wait:",waittime,"")

    with open(os.path.join(tmp_submission,".grading_began"), 'r') as f:
        grading_began_longstring = f.read()
    grading_began = dateutils.read_submitty_date(grading_began_longstring)

    submission_path = os.path.join(tmp_submission, "submission")
    checkout_path = os.path.join(tmp_submission, "checkout")

    provided_code_path = os.path.join(tmp_autograding, "provided_code")
    test_input_path = os.path.join(tmp_autograding, "test_input")
    test_output_path = os.path.join(tmp_autograding, "test_output")
    custom_validation_code_path = os.path.join(tmp_autograding, "custom_validation_code")
    bin_path = os.path.join(tmp_autograding, "bin")
    form_json_config = os.path.join(tmp_autograding, "form.json")
    complete_config = os.path.join(tmp_autograding, "complete_config.json")

    with open(form_json_config, 'r') as infile:
        gradeable_config_obj = json.load(infile)
    gradeable_deadline_string = gradeable_config_obj["date_due"]

    with open(complete_config, 'r') as infile:
        complete_config_obj = json.load(infile)

    is_vcs = gradeable_config_obj["upload_type"] == "repository"
    checkout_subdirectory = complete_config_obj["autograding"].get("use_checkout_subdirectory","")
    checkout_subdir_path = os.path.join(checkout_path, checkout_subdirectory)

    if complete_config_obj.get('one_part_only', False):
        allow_only_one_part(submission_path, os.path.join(tmp_logs, "overall.txt"))
        if is_vcs:
            with open(os.path.join(tmp_logs, "overall.txt"), 'a') as f:
                print("WARNING:  ONE_PART_ONLY OPTION DOES NOT MAKE SENSE WITH VCS SUBMISSION", file=f)


    # --------------------------------------------------------------------
    # START DOCKER

    # NOTE: DOCKER SUPPORT PRELIMINARY -- NEEDS MORE SECURITY BEFORE DEPLOYED ON LIVE SERVER
    complete_config = os.path.join(tmp_autograding,"complete_config.json")
    with open(complete_config, 'r') as infile:
        complete_config_obj = json.load(infile)

    # Save ourselves if autograding_method is None.
    autograding_method = complete_config_obj.get("autograding_method", "")
    USE_DOCKER = True if autograding_method == "docker" else False

    # --------------------------------------------------------------------
    # COMPILE THE SUBMITTED CODE

    with open(os.path.join(tmp_logs, "overall.txt"), 'a') as f:
        print("====================================\nCOMPILATION STARTS", file=f)
    
    # copy submitted files to the tmp compilation directory
    tmp_compilation = os.path.join(tmp,"TMP_COMPILATION")
    os.mkdir(tmp_compilation)
    os.chdir(tmp_compilation)

    submission_path = os.path.join(tmp_submission,"submission")
    checkout_path = os.path.join(tmp_submission,"checkout")

    provided_code_path = os.path.join(tmp_autograding,"provided_code")
    test_input_path = os.path.join(tmp_autograding,"test_input")
    test_output_path = os.path.join(tmp_autograding,"test_output")
    custom_validation_code_path = os.path.join(tmp_autograding,"custom_validation_code")
    bin_path = os.path.join(tmp_autograding,"bin")
    form_json_config = os.path.join(tmp_autograding,"form.json")


    with open(form_json_config, 'r') as infile:
        gradeable_config_obj = json.load(infile)
    gradeable_deadline_string = gradeable_config_obj["date_due"]
    
    patterns_submission_to_compilation = complete_config_obj["autograding"]["submission_to_compilation"]

    add_permissions(tmp_logs,stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR)

    if USE_DOCKER:
        print("!!!!!!!!!!!!!!!!!!USING DOCKER!!!!!!!!!!!!!!!!!!!!!!!!")

    with open(complete_config, 'r') as infile:
        config = json.load(infile)
        my_testcases = config['testcases']

    # grab the submission time
    with open(os.path.join(submission_path,".submit.timestamp"), 'r') as submission_time_file:
        submission_string = submission_time_file.read().rstrip()

    with open(os.path.join(tmp_logs,"compilation_log.txt"), 'w') as logfile:
        # we start counting from one.
        executable_path_list = list()
        for testcase_num in range(1, len(my_testcases)+1):
            testcase_folder = os.path.join(tmp_compilation, "test{:02}".format(testcase_num))

            if 'type' in my_testcases[testcase_num-1]:
                if my_testcases[testcase_num-1]['type'] != 'FileCheck' and my_testcases[testcase_num-1]['type'] != 'Compilation':
                    continue

                if my_testcases[testcase_num-1]['type'] == 'Compilation':
                    if 'executable_name' in my_testcases[testcase_num-1]:
                        provided_executable_list = my_testcases[testcase_num-1]['executable_name']
                        if not isinstance(provided_executable_list, (list,)):
                            provided_executable_list = list([provided_executable_list])
                        for executable_name in provided_executable_list:
                            if executable_name.strip() == '':
                                continue
                            executable_path = os.path.join(testcase_folder, executable_name)
                            executable_path_list.append((executable_path, executable_name))
            else:
                continue

            os.makedirs(testcase_folder)
            
            pattern_copy("submission_to_compilation",patterns_submission_to_compilation,submission_path,testcase_folder,tmp_logs)

            if is_vcs:
                pattern_copy("checkout_to_compilation",patterns_submission_to_compilation,checkout_subdir_path,testcase_folder,tmp_logs)

            # copy any instructor provided code files to tmp compilation directory
            copy_contents_into(job_id,provided_code_path,testcase_folder,tmp_logs)
            
            # copy compile.out to the current directory
            shutil.copy (os.path.join(bin_path,"compile.out"),os.path.join(testcase_folder,"my_compile.out"))
            add_permissions(os.path.join(testcase_folder,"my_compile.out"), stat.S_IXUSR | stat.S_IXGRP |stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)
            #untrusted_grant_rwx_access(which_untrusted, tmp_compilation)          
            untrusted_grant_rwx_access(which_untrusted, testcase_folder)
            add_permissions_recursive(testcase_folder,
                      stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                      stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                      stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)

            if USE_DOCKER:
                try:
                    #There can be only one container for a compilation step, so grab its container image
                    #TODO: set default in load_config_json.cpp
                    if my_testcases[testcase_num-1]['type'] == 'FileCheck':
                        print("performing filecheck in default ubuntu:custom container")
                        container_image = "ubuntu:custom"
                    else:
                        container_image = my_testcases[testcase_num-1]["containers"][0]["container_image"]
                        print('creating a compilation container with image {0}'.format(container_image))
                    untrusted_uid = str(getpwnam(which_untrusted).pw_uid)

                    compilation_container = None
                    compilation_container = subprocess.check_output(['docker', 'create', '-i', '-u', untrusted_uid, '--network', 'none',
                                               '-v', testcase_folder + ':' + testcase_folder,
                                               '-w', testcase_folder,
                                               container_image,
                                               #The command to be run.
                                               os.path.join(testcase_folder, 'my_compile.out'), 
                                               queue_obj['gradeable'],
                                               queue_obj['who'], 
                                               str(queue_obj['version']), 
                                               submission_string, 
                                               '--testcase', str(testcase_num)
                                               ]).decode('utf8').strip()
                    print("starting container")
                    compile_success = subprocess.call(['docker', 'start', '-i', compilation_container],
                                                   stdout=logfile,
                                                   cwd=testcase_folder)
                except Exception as e:
                    print('An error occurred when compiling with docker.')
                    grade_items_logging.log_stack_trace(job_id,is_batch_job,which_untrusted,item_name,trace=traceback.format_exc())
                finally:
                    if compilation_container != None:
                        subprocess.call(['docker', 'rm', '-f', compilation_container])
                        print("cleaned up compilation container.")
            else:
                compile_success = subprocess.call([os.path.join(SUBMITTY_INSTALL_DIR, "sbin", "untrusted_execute"),
                                                   which_untrusted,
                                                   os.path.join(testcase_folder,"my_compile.out"),
                                                   queue_obj["gradeable"],
                                                   queue_obj["who"],
                                                   str(queue_obj["version"]),
                                                   submission_string,
                                                   '--testcase', str(testcase_num)],
                                                   stdout=logfile, 
                                                   cwd=testcase_folder)
            # remove the compilation program
            untrusted_grant_rwx_access(which_untrusted, testcase_folder)
            os.remove(os.path.join(testcase_folder,"my_compile.out"))

    if compile_success == 0:
        print (which_machine,which_untrusted,"COMPILATION OK")
    else:
        print (which_machine,which_untrusted,"COMPILATION FAILURE")
        grade_items_logging.log_message(job_id,is_batch_job,which_untrusted,item_name,message="COMPILATION FAILURE")
    add_permissions_recursive(tmp_compilation,
                      stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                      stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                      stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)


    # return to the main tmp directory
    os.chdir(tmp)


    # --------------------------------------------------------------------
    # make the runner directory

    with open(os.path.join(tmp_logs,"overall.txt"),'a') as f:
        print ("====================================\nRUNNER STARTS", file=f)
        
    tmp_work = os.path.join(tmp,"TMP_WORK")
    tmp_work_test_input = os.path.join(tmp_work, "test_input")
    tmp_work_submission = os.path.join(tmp_work, "submitted_files")
    tmp_work_compiled = os.path.join(tmp_work, "compiled_files")
    tmp_work_checkout = os.path.join(tmp_work, "checkout")
    
    os.mkdir(tmp_work)

    os.mkdir(tmp_work_test_input)
    os.mkdir(tmp_work_submission)
    os.mkdir(tmp_work_compiled)
    os.mkdir(tmp_work_checkout)

    os.chdir(tmp_work)

    # move all executable files from the compilation directory to the main tmp directory
    # Note: Must preserve the directory structure of compiled files (esp for Java)

    patterns_submission_to_runner = complete_config_obj["autograding"]["submission_to_runner"]

    pattern_copy("submission_to_runner",patterns_submission_to_runner,submission_path,tmp_work_submission,tmp_logs)
    if is_vcs:
        pattern_copy("checkout_to_runner",patterns_submission_to_runner,checkout_subdir_path,tmp_work_checkout,tmp_logs)

    # move the compiled files into the tmp_work_compiled directory
    for path, name in executable_path_list:
        if not os.path.isfile(path): 
            continue
        target_path = os.path.join(tmp_work_compiled, name)
        if not os.path.exists(target_path):
            os.makedirs(os.path.dirname(target_path), exist_ok=True)
        shutil.copy(path, target_path)
        print('copied over {0}'.format(target_path))
        with open(os.path.join(tmp_logs,"overall.txt"),'a') as f:
            print('grade_item: copied over {0}'.format(target_path), file=f)

    patterns_compilation_to_runner = complete_config_obj["autograding"]["compilation_to_runner"]
    #copy into the actual tmp_work directory for archiving/validating
    pattern_copy("compilation_to_runner",patterns_compilation_to_runner,tmp_compilation,tmp_work,tmp_logs)
    #copy into tmp_work_compiled, which is provided to each testcase
    # TODO change this as our methodology for declaring testcase dependencies becomes more robust
    pattern_copy("compilation_to_runner",patterns_compilation_to_runner,tmp_compilation,tmp_work_compiled,tmp_logs)

    # copy input files to tmp_work directory
    copy_contents_into(job_id,test_input_path,tmp_work_test_input,tmp_logs)

    subprocess.call(['ls', '-lR', '.'], stdout=open(tmp_logs + "/overall.txt", 'a'))

    # copy runner.out to the current directory
    shutil.copy (os.path.join(bin_path,"run.out"),os.path.join(tmp_work,"my_runner.out"))

    #set the appropriate permissions for the newly created directories 
    #TODO replaces commented out code below

    add_permissions(os.path.join(tmp_work,"my_runner.out"), stat.S_IXUSR | stat.S_IXGRP |stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)
    add_permissions(tmp_work_submission, stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)
    add_permissions(tmp_work_compiled, stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)
    add_permissions(tmp_work_checkout, stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)

    #TODO this is how permissions used to be set. It was removed because of the way it interacts with the sticky bit.
    ## give the untrusted user read/write/execute permissions on the tmp directory & files
    # os.system('ls -al {0}'.format(tmp_work))
    # add_permissions_recursive(tmp_work,
    #                           stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
    #                           stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
    #                           stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)

    ##################################################################################################
    #call grade_item_main_runner.py
    runner_success = grade_item_main_runner.executeTestcases(complete_config_obj, tmp_logs, tmp_work, queue_obj, submission_string, 
                                                                                    item_name, USE_DOCKER, None, which_untrusted,
                                                                                    job_id, grading_began)
    ##################################################################################################

    if runner_success == 0:
        print (which_machine,which_untrusted, "RUNNER OK")
    else:
        print (which_machine,which_untrusted, "RUNNER FAILURE")
        grade_items_logging.log_message(job_id, is_batch_job, which_untrusted, item_name, message="RUNNER FAILURE")

    add_permissions_recursive(tmp_work,
                          stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                          stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                          stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH) 
    add_permissions_recursive(tmp_compilation,
                          stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                          stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                          stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH) 

    # --------------------------------------------------------------------
    # RUN VALIDATOR
    with open(os.path.join(tmp_logs,"overall.txt"),'a') as f:
        print ("====================================\nVALIDATION STARTS", file=f)

    # copy results files from compilation...
    patterns_submission_to_validation = complete_config_obj["autograding"]["submission_to_validation"]
    pattern_copy("submission_to_validation",patterns_submission_to_validation,submission_path,tmp_work,tmp_logs)
    if is_vcs:
        pattern_copy("checkout_to_validation",patterns_submission_to_validation,checkout_subdir_path,tmp_work,tmp_logs)
    patterns_compilation_to_validation = complete_config_obj["autograding"]["compilation_to_validation"]
    pattern_copy("compilation_to_validation",patterns_compilation_to_validation,tmp_compilation,tmp_work,tmp_logs)

    # remove the compilation directory
    shutil.rmtree(tmp_compilation)

    # copy output files to tmp_work directory
    copy_contents_into(job_id,test_output_path,tmp_work,tmp_logs)

    # copy any instructor custom validation code into the tmp work directory
    copy_contents_into(job_id,custom_validation_code_path,tmp_work,tmp_logs)

    subprocess.call(['ls', '-lR', '.'], stdout=open(tmp_logs + "/overall.txt", 'a'))

    # copy validator.out to the current directory
    shutil.copy (os.path.join(bin_path,"validate.out"),os.path.join(tmp_work,"my_validator.out"))

    # give the untrusted user read/write/execute permissions on the tmp directory & files
    add_permissions_recursive(tmp_work,
                              stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                              stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                              stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)

    add_permissions(os.path.join(tmp_work,"my_validator.out"), stat.S_IXUSR | stat.S_IXGRP |stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)

    #todo remove prints.
    print("VALIDATING")
    # validator the validator.out as the untrusted user
    with open(os.path.join(tmp_logs,"validator_log.txt"), 'w') as logfile:
        if USE_DOCKER:
            # WIP: This option file facilitated testing...
            #USE_DOCKER = os.path.isfile("/tmp/use_docker")
            #use_docker_string="grading begins, using DOCKER" if USE_DOCKER else "grading begins (not using docker)"
            #grade_items_logging.log_message(job_id,is_batch_job,which_untrusted,submission_path,message=use_docker_string)
            container = subprocess.check_output(['docker', 'run', '-t', '-d',
                                                 '-v', tmp + ':' + tmp,
                                                 'ubuntu:custom']).decode('utf8').strip()
            dockerlaunch_done=dateutils.get_current_time()
            dockerlaunch_time = (dockerlaunch_done-grading_began).total_seconds()
            grade_items_logging.log_message(job_id,is_batch_job,which_untrusted,item_name,"dcct:",dockerlaunch_time,"docker container created")

            validator_success = subprocess.call(['docker', 'exec', '-w', tmp_work, container,
                                                 os.path.join(tmp_work, 'my_validator.out'), queue_obj['gradeable'],
                                                 queue_obj['who'], str(queue_obj['version']), submission_string], stdout=logfile)
        else:
            validator_success = subprocess.call([os.path.join(SUBMITTY_INSTALL_DIR,"sbin","untrusted_execute"),
                                                 which_untrusted,
                                                 os.path.join(tmp_work,"my_validator.out"),
                                                 queue_obj["gradeable"],
                                                 queue_obj["who"],
                                                 str(queue_obj["version"]),
                                                 submission_string],
                                                stdout=logfile)

    if validator_success == 0:
        print (which_machine,which_untrusted,"VALIDATOR OK")
    else:
        print (which_machine,which_untrusted,"VALIDATOR FAILURE")
        grade_items_logging.log_message(job_id,is_batch_job,which_untrusted,item_name,message="VALIDATION FAILURE")

    untrusted_grant_rwx_access(which_untrusted,tmp_work)

    # grab the result of autograding
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
            grade_items_logging.log_message(job_id,is_batch_job,which_untrusted,item_name,message="ERROR: grade.txt does not exist")
            grade_items_logging.log_stack_trace(job_id,is_batch_job,which_untrusted,item_name,trace=traceback.format_exc())

    # --------------------------------------------------------------------
    # MAKE RESULTS DIRECTORY & COPY ALL THE FILES THERE
    tmp_results = os.path.join(tmp,"TMP_RESULTS")

    with open(os.path.join(tmp_logs,"overall.txt"),'a') as f:
        print ("====================================\nARCHIVING STARTS", file=f)

    subprocess.call(['ls', '-lR', '.'], stdout=open(tmp_logs + "/overall.txt", 'a'))

    os.makedirs(os.path.join(tmp_results,"details"))

    # remove the test_input directory, so we don't archive it!
    shutil.rmtree(os.path.join(tmp_work,"test_input"))

    # loop over the test case directories, and remove any files that are also in the test_input folder
    for testcase_num in range(1, len(my_testcases)+1):
        testcase_folder = os.path.join(tmp_work, "test{:02}".format(testcase_num))
        remove_test_input_files(os.path.join(tmp_logs,"overall.txt"),test_input_path,testcase_folder)

    patterns_work_to_details = complete_config_obj["autograding"]["work_to_details"]
    pattern_copy("work_to_details",patterns_work_to_details,tmp_work,os.path.join(tmp_results,"details"),tmp_logs)

    if ("work_to_public" in complete_config_obj["autograding"] and
        len(complete_config_obj["autograding"]["work_to_public"]) > 0):
        # create the directory
        os.makedirs(os.path.join(tmp_results,"results_public"))
        # copy the files
        patterns_work_to_public = complete_config_obj["autograding"]["work_to_public"]
        pattern_copy("work_to_public",patterns_work_to_public,tmp_work,os.path.join(tmp_results,"results_public"),tmp_logs)

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

    # -------------------------------------------------------------
    # create/append to the results history

    # grab the submission time
    with open (os.path.join(submission_path,".submit.timestamp")) as submission_time_file:
        submission_string = submission_time_file.read().rstrip()
    submission_datetime = dateutils.read_submitty_date(submission_string)

    gradeable_deadline_datetime = dateutils.read_submitty_date(gradeable_deadline_string)
    gradeable_deadline_longstring = dateutils.write_submitty_date(gradeable_deadline_datetime)
    submission_longstring = dateutils.write_submitty_date(submission_datetime)
    
    seconds_late = int((submission_datetime-gradeable_deadline_datetime).total_seconds())
    # note: negative = not late

    grading_finished_longstring = dateutils.write_submitty_date(grading_finished)

    gradingtime = (grading_finished-grading_began).total_seconds()

    with open(os.path.join(tmp_submission,"queue_file.json"), 'r') as infile:
        queue_obj = json.load(infile)
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
                                                 queue_time_longstring,
                                                 is_batch_job_string,
                                                 grading_began_longstring,
                                                 int(waittime),
                                                 grading_finished_longstring,
                                                 int(gradingtime),
                                                 grade_result,
                                                 revision)

    os.chdir(SUBMITTY_DATA_DIR)

    if USE_DOCKER:
        with open(os.path.join(tmp_logs,"overall_log.txt"), 'w') as logfile:
            chmod_success = subprocess.call(['docker', 'exec', '-w', tmp_work, container,
                                             'chmod', '-R', 'ugo+rwx', '.'], stdout=logfile)

    with open(os.path.join(tmp_logs,"overall.txt"),'a') as f:
        f.write("FINISHED GRADING!\n")

    # save the logs!
    shutil.copytree(tmp_logs,os.path.join(tmp_results,"logs"))

    # zip up results folder
    filehandle, my_results_zip_file=tempfile.mkstemp()
    zip_my_directory(tmp_results,my_results_zip_file)
    os.close(filehandle)
    shutil.rmtree(tmp_autograding)
    shutil.rmtree(tmp_submission)
    shutil.rmtree(tmp_results)
    shutil.rmtree(tmp_work)
    shutil.rmtree(tmp)

    # WIP: extra logging for testing
    #grade_items_logging.log_message(job_id,is_batch_job,which_untrusted,submission_path,message="done grading")

    # --------------------------------------------------------------------
    # CLEAN UP DOCKER
    if USE_DOCKER:
        subprocess.call(['docker', 'rm', '-f', container])
        dockerdestroy_done=dateutils.get_current_time()
        dockerdestroy_time = (dockerdestroy_done-grading_finished).total_seconds()
        grade_items_logging.log_message(job_id,is_batch_job,which_untrusted,item_name,"ddt:",dockerdestroy_time,"docker container destroyed")
        
    grade_items_logging.log_message(job_id,is_batch_job,which_untrusted,item_name,"grade:",gradingtime,grade_result)

    return my_results_zip_file


# ==================================================================================
# ==================================================================================

if __name__ == "__main__":
    raise SystemExit('ERROR: Do not call this script directly')

