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

# these variables will be replaced by INSTALL_SUBMITTY.sh
SUBMITTY_INSTALL_DIR = "__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__"
SUBMITTY_DATA_DIR = "__INSTALL__FILLIN__SUBMITTY_DATA_DIR__"

INTERACTIVE_QUEUE = os.path.join(SUBMITTY_DATA_DIR, "to_be_graded_interactive")
BATCH_QUEUE = os.path.join(SUBMITTY_DATA_DIR, "to_be_graded_batch")


# ==================================================================================
def parse_args():
    parser = argparse.ArgumentParser()
    parser.add_argument("next_directory")
    parser.add_argument("next_to_grade")
    parser.add_argument("which_untrusted")
    return parser.parse_args()


def get_submission_path(args):
    queue_file = os.path.join(args.next_directory,args.next_to_grade)
    if not os.path.isfile(queue_file):
        raise SystemExit("ERROR: the file does not exist",queue_file)
    with open(queue_file, 'r') as infile:
        obj = json.load(infile)
    return obj


def untrusted_execute():
    print("foo")


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


# ==================================================================================
def main():

    args = parse_args()
    obj = get_submission_path(args)
    submission_path = os.path.join(SUBMITTY_DATA_DIR,"courses",obj["semester"],obj["course"],
                                   "submissions",obj["gradeable"],obj["who"],obj["version"])

    if not os.path.isdir(submission_path):
        raise SystemExit("ERROR: the submission directory does not exist",submission_path)
    print ("GRADE THIS", submission_path)

    test_code_path = os.path.join(SUBMITTY_DATA_DIR,"courses",obj["semester"],obj["course"],
                                  "test_code",obj["gradeable"])
    test_input_path = os.path.join(SUBMITTY_DATA_DIR,"courses",obj["semester"],obj["course"],
                                   "test_input",obj["gradeable"])
    test_output_path = os.path.join(SUBMITTY_DATA_DIR,"courses",obj["semester"],obj["course"],
                                    "test_output",obj["gradeable"])

    bin_path = os.path.join(SUBMITTY_DATA_DIR,"courses",obj["semester"],obj["course"],"bin")

    #checkout_path="$SUBMITTY_DATA_DIR/courses/$semester/$course/checkout/$gradeable/$who/$version"

    results_path = os.path.join(SUBMITTY_DATA_DIR,"courses",obj["semester"],obj["course"],
                                "results",obj["gradeable"],obj["who"],obj["version"])

    # grab a copy of the current results_history.json file (if it exists)
    global_results_history_file_location=os.path.join(results_path,"results_history.json")
    #FIXME

    # --------------------------------------------------------------------
    # MAKE TEMPORARY DIRECTORY & COPY THE NECESSARY FILES THERE
    tmp=tempfile.mkdtemp()

    #print ("my tmp directory ", tmp)

    # grab the submission time
    with open (os.path.join(submission_path,".submit.timestamp")) as submission_time_file:
        submission_time=submission_time_file.read()

    # switch to tmp directory
    os.chdir(tmp)

    # make the logs directory
    tmp_logs = os.path.join(tmp,"tmp_logs")
    os.makedirs(tmp_logs)

    # --------------------------------------------------------------------
    # COMPILE THE SUBMITTED CODE

    # copy submitted files to the tmp compilation directory
    tmp_compilation = os.path.join(tmp,"TMP_COMPILATION")
    shutil.copytree(submission_path,tmp_compilation)
    os.chdir(tmp_compilation)

    # get info from the gradeable config file
    json_config=os.path.join(SUBMITTY_DATA_DIR,"courses",obj["semester"],obj["course"],
                             "config","form","form_"+obj["gradeable"]+".json")
    with open(json_config, 'r') as infile:
        gradeable_config_obj = json.load(infile)

    gradeable_upload_type=gradeable_config_obj["upload_type"]
    #print ("UPLOAD TYPE ",gradeable_upload_type)
    #FIXME:  deal with svn/git/whatever

    gradeable_deadline = gradeable_config_obj["date_due"]

    # copy any instructor provided code files to tmp compilation directory
    if os.path.isdir(test_code_path) :
        for item in os.listdir(test_code_path):
            if os.path.isdir(item):
                shutil.copytree(os.path.join(test_code_path,item),tmp_compilation)
            else:
                shutil.copy(os.path.join(test_code_path,item),tmp_compilation)

    # FIXME:  delete any submitted .out or .exe executable files

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
                                           obj["version"],
                                           submission_time],
                                          stdout=logfile)

    if compile_success == 0:
        print ("NEW COMPILATION OK")
    else:
        print ("NEW COMPILATION FAILURE")

    # remove the compilation program
    os.remove(os.path.join(tmp_compilation,"my_compile.out"))

    # return to the main tmp directory
    os.chdir(tmp)


    # --------------------------------------------------------------------
    # make the runner directory
    tmp_work=os.path.join(tmp,"TMP_WORK")
    os.makedirs(tmp_work)
    os.chdir(tmp_work)

    # move all executable files from the compilation directory to the main tmp directory
    # Note: Must preserve the directory structure of compiled files (esp for Java)

    #FIXME INCOMPLETE COPY
    for file in glob.glob(os.path.join(tmp_compilation,"*.out")):
        #print ("FILE ",file)
        shutil.copy(file,tmp_work)
    for file in glob.glob(os.path.join(tmp_compilation,"*.py")):
        #print ("FILE ",file)
        shutil.copy(file,tmp_work)


    # remove the compilation directory
    #shutil.rmtree(tmp_compilation)

    # copy input files to tmp_work directory
    if os.path.isdir(test_input_path) :
        for item in os.listdir(test_input_path):
            #print ("copy input ", item)
            if os.path.isdir(item):
                shutil.copytree(os.path.join(test_input_path,item),tmp_work)
            else:
                shutil.copy(os.path.join(test_input_path,item),tmp_work)

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
                                          obj["version"],
                                          submission_time],
                                          stdout=logfile)




    if runner_success == 0:
        print ("NEW RUNNER OK")
    else:
        print ("NEW RUNNER FAILURE")


    #$SUBMITTY_INSTALL_DIR/bin/untrusted_execute  "${ARGUMENT_UNTRUSTED_USER}"  /usr/bin/find $tmp -user "${ARGUMENT_UNTRUSTED_USER}" -exec /bin/chmod o+r {} \;   >>  results_log_runner.txt 2>&1

    subprocess.call([os.path.join(SUBMITTY_INSTALL_DIR,"bin","untrusted_execute"),
                     args.which_untrusted,
                     "/usr/bin/find",
                     tmp_work,
                     "-user",
                     args.which_untrusted,
                     "-exec",
                     "/bin/chmod",
                     "o+r",
                     "{}",
                     ";"])

    #print ("finishing runner")


    # --------------------------------------------------------------------
    # RUN VALIDATOR

    # copy results files from compilation...
    for filename in glob.glob(os.path.join(tmp_compilation,"test*.txt")):
        shutil.copy(filename,tmp_work)

    # copy output files to tmp_work directory
    if os.path.isdir(test_output_path) :
        for item in os.listdir(test_output_path):
            #print ("copy output ", item)
            if os.path.isdir(item):
                shutil.copytree(os.path.join(test_output_path,item),tmp_work)
            else:
                shutil.copy(os.path.join(test_output_path,item),tmp_work)

    # copy validator.out to the current directory
    shutil.copy (os.path.join(bin_path,obj["gradeable"],"validate.out"),os.path.join(tmp_work,"my_validator.out"))

    #print ("going to change more permissions")

    # give the untrusted user read/write/execute permissions on the tmp directory & files
    add_permissions_recursive(tmp_work,
                              stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                              stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                              stat.S_IROTH)

    add_permissions(os.path.join(tmp_work,"my_validator.out"),stat.S_IROTH | stat.S_IXOTH)

    #print ("going to run the validator")

    # validator the validator.out as the untrusted user
    with open(os.path.join(tmp_logs,"validator_log.txt"), 'w') as logfile:
        validator_success = subprocess.call([os.path.join(SUBMITTY_INSTALL_DIR,"bin","untrusted_execute"),
                                             args.which_untrusted,
                                             os.path.join(tmp_work,"my_validator.out"),
                                             obj["gradeable"],
                                             obj["who"],
                                             obj["version"],
                                             submission_time],
                                            stdout=logfile)

    if validator_success == 0:
        print ("NEW VALIDATOR OK")
    else:
        print ("NEW VALIDATOR FAILURE")

    subprocess.call([os.path.join(SUBMITTY_INSTALL_DIR,"bin","untrusted_execute"),
                     args.which_untrusted,
                     "/usr/bin/find",
                     tmp_work,
                     "-user",
                     args.which_untrusted,
                     "-exec",
                     "/bin/chmod",
                     "o+r",
                     "{}",
                     ";"])


    # --------------------------------------------------------------------
    # MAKE RESULTS DIRECTORY & COPY ALL THE FILES THERE

    os.chdir(bin_path)

    # save the old results path!
    if os.path.isdir(os.path.join(results_path,"OLD")):
        shutil.move(os.path.join(results_path,"OLD"),
                    os.path.join(tmp,"OLD_RESULTS"))

    # clean out all of the old files if this is a re-run
    shutil.rmtree(results_path,ignore_errors=True)

    # Make directory structure in results if it doesn't exist
    os.makedirs(results_path)

    # bring back the old results!
    if os.path.isdir(os.path.join(tmp,"OLD_RESULTS")):
        shutil.move(os.path.join(tmp,"OLD_RESULTS"),
                    os.path.join(results_path,"OLD"))



    shutil.copytree(tmp_logs,os.path.join(results_path,"logs"))
    shutil.copy(os.path.join(tmp_work,"results.json"),results_path)
    shutil.copy(os.path.join(tmp_work,"grade.txt"),results_path)
    os.makedirs(os.path.join(results_path,"details"))
    for filename in glob.glob(os.path.join(tmp_work,"test*.txt")):
        shutil.copy(filename,os.path.join(results_path,"details"))
    for filename in glob.glob(os.path.join(tmp_work,"test*_diff.json")):
        shutil.copy(filename,os.path.join(results_path,"details"))

    #print ("wrote to ",results_path)

    print ("RESULTS PATH ", results_path)
    #subprocess.call(["ls","-la",results_path])
    #if (os.path.isdir(os.path.join(results_path,"OLD"))):
    #    subprocess.call(["ls","-la",results_path+"/OLD"])


    if os.path.isdir(os.path.join(results_path,"OLD")):
        print ("OLD EXISTS: ",os.path.join(results_path,"OLD"))
        m = filecmp.cmp(os.path.join(results_path,"OLD","results.json"),
                        os.path.join(results_path,"results.json"))
        if not m: print ("********************************************************** OOPS!  results.json does not match")
        m = filecmp.cmp(os.path.join(results_path,"OLD","grade.txt"),
                        os.path.join(results_path,"grade.txt"))
        if not m: print ("********************************************************** OOPS!  grade.txt does not match")

    #else:
    #print ("NO OLD: ",results_path+"_OLD")


    # --------------------------------------------------------------------
    # REMOVE TEMP DIRECTORY
    shutil.rmtree(tmp)

# ==================================================================================
if __name__ == "__main__":
    main()


