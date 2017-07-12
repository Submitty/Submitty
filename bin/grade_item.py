#!/usr/bin/env python3

import argparse
import json
import sys
import glob
import os
import tempfile
import shutil
import subprocess
import stat
import filecmp
import datetime
import time


# these variables will be replaced by INSTALL_SUBMITTY.sh
SUBMITTY_INSTALL_DIR = "__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__"
SUBMITTY_DATA_DIR = "__INSTALL__FILLIN__SUBMITTY_DATA_DIR__"
HWCRON_UID = "__INSTALL__FILLIN__HWCRON_UID__"
INTERACTIVE_QUEUE = os.path.join(SUBMITTY_DATA_DIR, "to_be_graded_interactive")
BATCH_QUEUE = os.path.join(SUBMITTY_DATA_DIR, "to_be_graded_batch")


# ==================================================================================
def parse_args():
    parser = argparse.ArgumentParser()
    parser.add_argument("next_directory")
    parser.add_argument("next_to_grade")
    parser.add_argument("which_untrusted")
    return parser.parse_args()


def get_queue_time(args):
    t = time.ctime(os.path.getctime(os.path.join(args.next_directory,args.next_to_grade)))
    print ("QUEUE TIME ",t)
    return datetime.datetime.strptime(time.ctime(), "%a %b %d %H:%M:%S %Y")

def get_current_time():
    t = time.ctime(os.path.getctime(os.path.join(args.next_directory,args.next_to_grade)))
    return datetime.datetime.strptime(time.ctime(), "%a %b %d %H:%M:%S %Y")

def get_submission_path(args):
    queue_file = os.path.join(args.next_directory,args.next_to_grade)
    if not os.path.isfile(queue_file):
        raise SystemExit("ERROR: the file does not exist",queue_file)
    with open(queue_file, 'r') as infile:
        obj = json.load(infile)
    return obj


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
def copy_contents_into(source,target):
    if not os.path.isdir(target):
        raise SystemExit("ERROR: the target directory does not exist '", target, "'")
    if os.path.isdir(source):
        for item in os.listdir(source):
            if os.path.isdir(os.path.join(source,item)):
                if os.path.isdir(os.path.join(target,item)):
                    # recurse
                    copy_contents_into(os.path.join(source,item),os.path.join(target,item))
                elif os.path.isfile(os.path.join(target,item)):
                    raise SystemExit("ERROR: the target subpath is a file not a directory '", os.path.join(target,item), "'")
                else:
                    # copy entire subtree
                    shutil.copytree(os.path.join(source,item),os.path.join(target,item))
            else:
                shutil.copy(os.path.join(source,item),target)

    
# copy files that match one of the patterns from the source directory
# to the target directory.  
def pattern_copy(what,patterns,source,target,tmp_logs):
    with open(os.path.join(tmp_logs,"overall.txt"),'a') as f:
        print (what," pattern copy ", patterns, " from ", source, " -> ", target, file=f)
        for pattern in patterns:
            for my_file in glob.glob(os.path.join(source,pattern),recursive=True):
                # grab the matched name
                relpath=os.path.relpath(my_file,source)
                # make the necessary directories leading to the file
                os.makedirs(os.path.join(target,os.path.dirname(relpath)),exist_ok=True)
                # copy the file
                shutil.copy(my_file,os.path.join(target,relpath))
                print ("    COPY ",my_file,
                       " -> ",os.path.join(target,relpath), file=f)
            

# give permissions to all created files to the hwcron user
def untrusted_grant_read_access(args,my_dir):
    subprocess.call([os.path.join(SUBMITTY_INSTALL_DIR,"bin","untrusted_execute"),
                     args.which_untrusted,
                     "/usr/bin/find",
                     my_dir,
                     "-user",
                     args.which_untrusted,
                     "-exec",
                     "/bin/chmod",
                     "o+r",
                     "{}",
                     ";"])


# ==================================================================================
# ==================================================================================
def main():

    # verify the hwcron user is running this script
    if not int(os.getuid()) == int(HWCRON_UID):
        raise SystemExit("ERROR: the grade_item.py script must be run by the hwcron user")

    # --------------------------------------------------------
    # figure out what we're supposed to grade & error checking
    args = parse_args()
    obj = get_submission_path(args)
    submission_path = os.path.join(SUBMITTY_DATA_DIR,"courses",obj["semester"],obj["course"],
                                   "submissions",obj["gradeable"],obj["who"],str(obj["version"]))
    if not os.path.isdir(submission_path):
        raise SystemExit("ERROR: the submission directory does not exist",submission_path)
    print ("GRADE THIS", submission_path)

    # --------------------------------------------------------
    # various paths
    test_code_path = os.path.join(SUBMITTY_DATA_DIR,"courses",obj["semester"],obj["course"],"test_code",obj["gradeable"])
    test_input_path = os.path.join(SUBMITTY_DATA_DIR,"courses",obj["semester"],obj["course"],"test_input",obj["gradeable"])
    test_output_path = os.path.join(SUBMITTY_DATA_DIR,"courses",obj["semester"],obj["course"],"test_output",obj["gradeable"])
    bin_path = os.path.join(SUBMITTY_DATA_DIR,"courses",obj["semester"],obj["course"],"bin")

    #checkout_path="$SUBMITTY_DATA_DIR/courses/$semester/$course/checkout/$gradeable/$who/$version"

    results_path = os.path.join(SUBMITTY_DATA_DIR,"courses",obj["semester"],obj["course"],"results",obj["gradeable"],obj["who"],str(obj["version"]))

    # grab a copy of the current history.json file (if it exists)
    history_file=os.path.join(results_path,"history.json")
    history_file_tmp=tempfile.mkdtemp()
    if os.path.isfile(history_file):
        shutil.copy(history_file,history_file_tmp)

    # --------------------------------------------------------------------
    # MAKE TEMPORARY DIRECTORY & COPY THE NECESSARY FILES THERE
    tmp=tempfile.mkdtemp()

    # switch to tmp directory
    os.chdir(tmp)

    # make the logs directory
    tmp_logs = os.path.join(tmp,"tmp_logs")
    os.makedirs(tmp_logs)

    # grab the submission time
    with open (os.path.join(submission_path,".submit.timestamp")) as submission_time_file:
        submission_time=submission_time_file.read().rstrip()
    
    
    # --------------------------------------------------------------------
    # COMPILE THE SUBMITTED CODE

    with open(os.path.join(tmp_logs,"overall.txt"),'a') as f:
        print ("====================================\nCOMPILATION STARTS", file=f)
    
    # copy submitted files to the tmp compilation directory
    tmp_compilation = os.path.join(tmp,"TMP_COMPILATION")
    os.mkdir(tmp_compilation)
    os.chdir(tmp_compilation)
    
    # get info from the gradeable config file
    json_config=os.path.join(SUBMITTY_DATA_DIR,"courses",obj["semester"],obj["course"],"config","form","form_"+obj["gradeable"]+".json")
    with open(json_config, 'r') as infile:
        gradeable_config_obj = json.load(infile)

    # get info from the gradeable config file
    complete_config=os.path.join(SUBMITTY_DATA_DIR,"courses",obj["semester"],obj["course"],"config","complete_config","complete_config_"+obj["gradeable"]+".json")
    with open(complete_config, 'r') as infile:
        complete_config_obj = json.load(infile)

    gradeable_upload_type=gradeable_config_obj["upload_type"]
    #print ("UPLOAD TYPE ",gradeable_upload_type)
    #FIXME:  deal with svn/git/whatever

    gradeable_deadline_string = gradeable_config_obj["date_due"]
    gradeable_deadline_datetime=datetime.datetime.strptime(str(gradeable_deadline_string),'%Y-%m-%d %H:%M:%S')
    #gradeable_deadline_datetime.replace(tzinfo=datetime.timezone.utc)
    
    patterns_submission_to_compilation = complete_config_obj["autograding"]["submission_to_compilation"]
    pattern_copy("submission_to_compilation",patterns_submission_to_compilation,submission_path,tmp_compilation,tmp_logs)
    
    # copy any instructor provided code files to tmp compilation directory
    copy_contents_into(test_code_path,tmp_compilation)

    subprocess.call(['ls', '-la', tmp_compilation], stdout=open(tmp_logs + "/overall.txt", 'a'))
    
    # copy compile.out to the current directory
    shutil.copy (os.path.join(bin_path,obj["gradeable"],"compile.out"),os.path.join(tmp_compilation,"my_compile.out"))

    # give the untrusted user read/write/execute permissions on the tmp directory & files
    add_permissions_recursive(tmp_compilation,
                              stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                              stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                              stat.S_IROTH | stat.S_IXOTH)

    add_permissions(tmp,stat.S_IROTH | stat.S_IXOTH)
    add_permissions(tmp_logs,stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR)

    with open(os.path.join(tmp_logs,"compilation_log.txt"), 'w') as logfile:
        compile_success = subprocess.call([os.path.join(SUBMITTY_INSTALL_DIR,"bin","untrusted_execute"),
                                           args.which_untrusted,
                                           os.path.join(tmp_compilation,"my_compile.out"),
                                           obj["gradeable"],
                                           obj["who"],
                                           str(obj["version"]),
                                           submission_time],
                                          stdout=logfile)

    if compile_success == 0:
        print ("NEW COMPILATION OK")
    else:
        print ("NEW COMPILATION FAILURE")

    untrusted_grant_read_access(args,tmp_compilation)
        
    # remove the compilation program
    os.remove(os.path.join(tmp_compilation,"my_compile.out"))

    # return to the main tmp directory
    os.chdir(tmp)


    # --------------------------------------------------------------------
    # make the runner directory

    with open(os.path.join(tmp_logs,"overall.txt"),'a') as f:
        print ("====================================\nRUNNER STARTS", file=f)
        
    tmp_work=os.path.join(tmp,"TMP_WORK")
    os.makedirs(tmp_work)
    os.chdir(tmp_work)

    # move all executable files from the compilation directory to the main tmp directory
    # Note: Must preserve the directory structure of compiled files (esp for Java)

    patterns_submission_to_runner = complete_config_obj["autograding"]["submission_to_runner"]
    pattern_copy("submission_to_runner",patterns_submission_to_runner,submission_path,tmp_work,tmp_logs)

    patterns_compilation_to_runner = complete_config_obj["autograding"]["compilation_to_runner"]
    pattern_copy("compilation_to_runner",patterns_compilation_to_runner,tmp_compilation,tmp_work,tmp_logs)
        
    # copy input files to tmp_work directory
    copy_contents_into(test_input_path,tmp_work)

    subprocess.call(['ls', '-la', tmp_work], stdout=open(tmp_logs + "/overall.txt", 'a'))
    
    # copy runner.out to the current directory
    shutil.copy (os.path.join(bin_path,obj["gradeable"],"run.out"),os.path.join(tmp_work,"my_runner.out"))

    # give the untrusted user read/write/execute permissions on the tmp directory & files
    add_permissions_recursive(tmp_work,
                              stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                              stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                              stat.S_IROTH | stat.S_IXOTH)

    # run the run.out as the untrusted user
    with open(os.path.join(tmp_logs,"runner_log.txt"), 'w') as logfile:
        runner_success = subprocess.call([os.path.join(SUBMITTY_INSTALL_DIR,"bin","untrusted_execute"),
                                          args.which_untrusted,
                                          os.path.join(tmp_work,"my_runner.out"),
                                          obj["gradeable"],
                                          obj["who"],
                                          str(obj["version"]),
                                          submission_time],
                                          stdout=logfile)




    if runner_success == 0:
        print ("NEW RUNNER OK")
    else:
        print ("NEW RUNNER FAILURE")

    untrusted_grant_read_access(args,tmp_work)

    # --------------------------------------------------------------------
    # RUN VALIDATOR

    with open(os.path.join(tmp_logs,"overall.txt"),'a') as f:
        print ("====================================\nVALIDATION STARTS", file=f)

    # copy results files from compilation...
    patterns_submission_to_validation = complete_config_obj["autograding"]["submission_to_validation"]
    pattern_copy("submission_to_validation",patterns_submission_to_validation,submission_path,tmp_work,tmp_logs)
    patterns_compilation_to_validation = complete_config_obj["autograding"]["compilation_to_validation"]
    pattern_copy("compilation_to_validation",patterns_compilation_to_validation,tmp_compilation,tmp_work,tmp_logs)
    
    # remove the compilation directory
    shutil.rmtree(tmp_compilation)

    # copy output files to tmp_work directory
    copy_contents_into(test_output_path,tmp_work)

    subprocess.call(['ls', '-la', tmp_work], stdout=open(tmp_logs + "/overall.txt", 'a'))
        
    # copy validator.out to the current directory
    shutil.copy (os.path.join(bin_path,obj["gradeable"],"validate.out"),os.path.join(tmp_work,"my_validator.out"))

    # give the untrusted user read/write/execute permissions on the tmp directory & files
    add_permissions_recursive(tmp_work,
                              stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                              stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                              stat.S_IROTH)

    add_permissions(os.path.join(tmp_work,"my_validator.out"),stat.S_IROTH | stat.S_IXOTH)

    # validator the validator.out as the untrusted user
    with open(os.path.join(tmp_logs,"validator_log.txt"), 'w') as logfile:
        validator_success = subprocess.call([os.path.join(SUBMITTY_INSTALL_DIR,"bin","untrusted_execute"),
                                             args.which_untrusted,
                                             os.path.join(tmp_work,"my_validator.out"),
                                             obj["gradeable"],
                                             obj["who"],
                                             str(obj["version"]),
                                             submission_time],
                                            stdout=logfile)

    if validator_success == 0:
        print ("NEW VALIDATOR OK")
    else:
        print ("NEW VALIDATOR FAILURE")


    untrusted_grant_read_access(args,tmp_work)

    # --------------------------------------------------------------------
    # MAKE RESULTS DIRECTORY & COPY ALL THE FILES THERE

    with open(os.path.join(tmp_logs,"overall.txt"),'a') as f:
        print ("====================================\nARCHIVING STARTS", file=f)

    subprocess.call(['ls', '-la', tmp_work], stdout=open(tmp_logs + "/overall.txt", 'a'))
        
    os.chdir(bin_path)

    # save the old results path!
    if os.path.isdir(os.path.join(results_path,"OLD")):
        shutil.move(os.path.join(results_path,"OLD"),
                    os.path.join(tmp,"OLD_RESULTS"))

    # clean out all of the old files if this is a re-run
    shutil.rmtree(results_path,ignore_errors=True)

    # create the directory (and the full path if it doesn't already exist)
    os.makedirs(results_path)

    # bring back the old results!
    if os.path.isdir(os.path.join(tmp,"OLD_RESULTS")):
        shutil.move(os.path.join(tmp,"OLD_RESULTS"),
                    os.path.join(results_path,"OLD"))

    shutil.copytree(tmp_logs,os.path.join(results_path,"logs"))
    shutil.copy(os.path.join(tmp_work,"results.json"),results_path)
    shutil.copy(os.path.join(tmp_work,"grade.txt"),results_path)
    os.makedirs(os.path.join(results_path,"details"))

    patterns_work_to_details = complete_config_obj["autograding"]["work_to_details"]
    pattern_copy("work_to_details",patterns_work_to_details,tmp_work,os.path.join(results_path,"details"),tmp_logs)

    if os.path.isfile(history_file_tmp):
        shutil.move(history_file_tmp,history_file)
        # fix permissions
        ta_group_id=os.stat(results_path).st_gid
        #os.chown(history_file,HWCRON_UID,ta_group_id)
        #os.chmod(history_file,0640)
        
    # -------------------------------------------------------------
    # create/append to the results history

    submission_datetime=datetime.datetime.strptime(str(submission_time),'%Y-%m-%d %H:%M:%S')

    #    submission_datetime.replace(tzinfo=datetime.timezone.utc)
    
    print ("DEADLINE |",str(gradeable_deadline_string),"| |",str(submission_time),"|")

    
    gradeable_deadline_longstring = datetime.datetime.strftime(gradeable_deadline_datetime, "%a %b  %d %H:%M:%S %Z %Y")
    submission_longstring = datetime.datetime.strftime(submission_datetime, "%a %b  %d %H:%M:%S %Z %Y")

    print ("BEFgradeable_deadline_datetime   ", gradeable_deadline_datetime)
    print ("BEFgradeable_deadline longstring ", gradeable_deadline_longstring)
    print ("BEFsubmission longstring ", submission_longstring)
        
    gradeable_deadline_longstring = gradeable_deadline_longstring.replace("  20","  EST  20")
    submission_longstring = submission_longstring.replace("  20","  EST  20")


    
    print ("AFTgradeable_deadline_datetime   ", gradeable_deadline_datetime)
    print ("AFTgradeable_deadline longstring ", gradeable_deadline_longstring)
    print ("AFTsubmission longstring ", submission_longstring)
    print ("submission_datetime   ", submission_datetime)

    
    deadline_seconds = gradeable_deadline_datetime.toordinal()
    submission_seconds = submission_datetime.toordinal()

    seconds_late = submission_seconds-deadline_seconds
    print ("LATE ",seconds_late)
    # note: negative = not late


    queue_time = get_queue_time(args)
    queue_time_longstring = datetime.datetime.strftime(queue_time, "%a %b  %d %H:%M:%S %Z %Y")
    queue_time_longstring = queue_time_longstring.replace("  20","  EST  20")
    print ("qtlong",queue_time_longstring)
    
    subprocess.call([os.path.join(SUBMITTY_INSTALL_DIR,"bin","write_grade_history.py"),
                     history_file,
                     gradeable_deadline_longstring,
                     submission_longstring,
                     str(seconds_late),
                     queue_time_longstring,
                     "False",# "$IS_BATCH_JOB" \
                     "",#"`date -d @$STARTTIME`" \
                     "0", #"$WAITTIME" \
                     "",#"`date -d @$ENDTIME`" \
                     "0",#"$ELAPSED" \
                     "0"])#"$global_grade_result"

    # TEMPORARY ERROR CHECKING
    if os.path.isdir(os.path.join(results_path,"OLD")):
        if not filecmp.cmp(os.path.join(results_path,"OLD","results.json"),
                           os.path.join(results_path,"results.json")):
            print ("********************************************************** OOPS!  results.json does not match")
            touch (os.path.join(results_path,"MISMATCH_RESULTS_JSON"))
        if not filecmp.cmp(os.path.join(results_path,"OLD","grade.txt"),
                           os.path.join(results_path,"grade.txt")):
            print ("********************************************************** OOPS!  grade.txt does not match")
            touch (os.path.join(results_path,"MISMATCH_GRADE_TXT"))


    with open(os.path.join(tmp_logs,"overall.txt"),'a') as f:
        f.write("finished")
            
            
    # --------------------------------------------------------------------
    # REMOVE TEMP DIRECTORY
    shutil.rmtree(tmp)

    
# ==================================================================================
if __name__ == "__main__":
    main()
