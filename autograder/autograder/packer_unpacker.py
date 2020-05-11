import configparser
import json
import os
import tempfile
import shutil
import subprocess
import time
import dateutil
import dateutil.parser
import urllib.parse
import string
import random
import socket
import zipfile

from submitty_utils import dateutils

from . import insert_database_version_data, autograding_utils, CONFIG_PATH

with open(os.path.join(CONFIG_PATH, 'submitty.json')) as open_file:
    OPEN_JSON = json.load(open_file)
SUBMISSION_URL = OPEN_JSON['submission_url']
AUTOGRADING_LOG_PATH = OPEN_JSON['autograding_log_path']
VCS_URL = OPEN_JSON['vcs_url']
if VCS_URL is None or len(VCS_URL) == 0:
    VCS_URL = SUBMISSION_URL.rstrip('/') + '/{$vcs_type}'
SUBMITTY_INSTALL_DIR = OPEN_JSON['submitty_install_dir']
SUBMITTY_DATA_DIR = OPEN_JSON['submitty_data_dir']

with open(os.path.join(CONFIG_PATH, 'submitty_users.json')) as open_file:
    OPEN_JSON = json.load(open_file)
DAEMON_UID = OPEN_JSON['daemon_uid']


# ==================================================================================
def get_queue_time(next_directory,next_to_grade):
    t = time.ctime(os.path.getctime(os.path.join(next_directory,next_to_grade)))
    t = dateutil.parser.parse(t)
    t = dateutils.get_timezone().localize(t)
    return t


def load_queue_file_obj(job_id,next_directory,next_to_grade):
    queue_file = os.path.join(next_directory,next_to_grade)
    if not os.path.isfile(queue_file):
        autograding_utils.log_message(AUTOGRADING_LOG_PATH, job_id,message="ERROR: the file does not exist " + queue_file)
        raise RuntimeError("ERROR: the file does not exist",queue_file)
    with open(queue_file, 'r') as infile:
        obj = json.load(infile)
    return obj


def get_vcs_info(top_dir, semester, course, gradeable, userid,  teamid):
    form_json_file = os.path.join(top_dir, 'courses', semester, course, 'config', 'form', 'form_'+gradeable+'.json')
    with open(form_json_file, 'r') as fj:
        form_json = json.load(fj)
    course_json_path = os.path.join(top_dir, 'courses', semester, course, 'config', 'config.json')
    with open(course_json_path, 'r') as open_file:
        course_json = json.load(open_file)
    is_vcs = form_json["upload_type"] == "repository"
    # PHP reads " as a character around the string, while Python reads it as part of the string
    # so we have to strip out the " in python
    vcs_type = course_json['course_details']['vcs_type']
    vcs_base_url = course_json['course_details']['vcs_base_url']
    if len(vcs_base_url) == 0:
        vcs_base_url = "/".join([VCS_URL, semester, course]).rstrip('/') + "/"
    vcs_base_url = vcs_base_url.replace(SUBMISSION_URL, os.path.join(SUBMITTY_DATA_DIR, 'vcs'))
    vcs_base_url = vcs_base_url.replace('{$vcs_type}', vcs_type)
    vcs_subdirectory = form_json["subdirectory"] if is_vcs else ''
    vcs_subdirectory = vcs_subdirectory.replace("{$vcs_type}", vcs_type)
    vcs_subdirectory = vcs_subdirectory.replace("{$gradeable_id}", gradeable)
    vcs_subdirectory = vcs_subdirectory.replace("{$user_id}", userid)
    vcs_subdirectory = vcs_subdirectory.replace("{$team_id}", teamid)
    return is_vcs, vcs_type, vcs_base_url, vcs_subdirectory


def copytree_if_exists(source,target):
    # target must not exist!
    if os.path.exists(target):
        raise RuntimeError("ERROR: the target directory already exists", target)
    # source might exist
    if not os.path.isdir(source):
        os.mkdir(target)
    else:
        shutil.copytree(source,target)

def unzip_queue_file(zipfilename):
    # be sure the zip file is ok, and contains the queue file
    if not os.path.exists(zipfilename):
        raise RuntimeError("ERROR: zip file does not exist", zipfilename)
    zip_ref = zipfile.ZipFile(zipfilename,'r')
    names = zip_ref.namelist()
    if 'failure.txt' in names:
        return None
    if not 'queue_file.json' in names:
        raise RuntimeError("ERROR: zip file does not contain queue file", zipfilename)
    # remember the current directory
    cur_dir = os.getcwd()
    # create a temporary directory and go to it
    tmp_dir = tempfile.mkdtemp()
    os.chdir(tmp_dir)
    # extract the queue file
    queue_file_name = "queue_file.json"
    zip_ref.extract(queue_file_name)
    # read it into a json object
    with open(queue_file_name) as f:
        queue_obj = json.load(f)
    # clean up the file & tmp directory, return to the original directory
    os.remove(queue_file_name)
    os.chdir(cur_dir)
    os.rmdir(tmp_dir)
    return queue_obj


# ==================================================================================
# ==================================================================================
def prepare_autograding_and_submission_zip(which_machine,which_untrusted,next_directory,next_to_grade):
    os.chdir(SUBMITTY_DATA_DIR)

    # generate a random id to be used to track this job in the autograding logs
    job_id = ''.join(random.choice(string.ascii_letters + string.digits) for _ in range(6))

    # --------------------------------------------------------
    # figure out what we're supposed to grade & error checking
    obj = load_queue_file_obj(job_id,next_directory,next_to_grade)
    if "generate_output" not in obj:
        partial_path = os.path.join(obj["gradeable"],obj["who"],str(obj["version"]))
        item_name = os.path.join(obj["semester"],obj["course"],"submissions",partial_path)
        submission_path = os.path.join(SUBMITTY_DATA_DIR,"courses",item_name)
        if not os.path.isdir(submission_path):
            autograding_utils.log_message(AUTOGRADING_LOG_PATH, job_id, message="ERROR: the submission directory does not exist " + submission_path)
            raise RuntimeError("ERROR: the submission directory does not exist", submission_path)
        print(which_machine,which_untrusted,"prepare zip",submission_path)
        is_vcs,vcs_type,vcs_base_url,vcs_subdirectory = get_vcs_info(SUBMITTY_DATA_DIR,obj["semester"],obj["course"],obj["gradeable"],obj["who"],obj["team"])
    elif obj["generate_output"]:
        item_name = os.path.join(obj["semester"],obj["course"],"generated_output",obj["gradeable"])

    is_batch_job = "regrade" in obj and obj["regrade"]
    is_batch_job_string = "BATCH" if is_batch_job else "INTERACTIVE"

    queue_time = get_queue_time(next_directory,next_to_grade)
    queue_time_longstring = dateutils.write_submitty_date(queue_time)
    grading_began = dateutils.get_current_time()
    waittime = (grading_began-queue_time).total_seconds()
    autograding_utils.log_message(AUTOGRADING_LOG_PATH, job_id,is_batch_job,"zip",item_name,"wait:",waittime,"")

    # --------------------------------------------------------
    # various paths
    provided_code_path = os.path.join(SUBMITTY_DATA_DIR,"courses",obj["semester"],obj["course"],"provided_code",obj["gradeable"])
    instructor_solution_path = os.path.join(SUBMITTY_DATA_DIR,"courses",obj["semester"],obj["course"],"instructor_solution",obj["gradeable"])
    test_input_path = os.path.join(SUBMITTY_DATA_DIR,"courses",obj["semester"],obj["course"],"test_input",obj["gradeable"])
    test_output_path = os.path.join(SUBMITTY_DATA_DIR,"courses",obj["semester"],obj["course"],"test_output",obj["gradeable"])
    generated_output_path = os.path.join(SUBMITTY_DATA_DIR,"courses",obj["semester"],obj["course"],"generated_output",obj["gradeable"],"random_output")
    custom_validation_code_path = os.path.join(SUBMITTY_DATA_DIR,"courses",obj["semester"],obj["course"],"custom_validation_code",obj["gradeable"])
    bin_path = os.path.join(SUBMITTY_DATA_DIR,"courses",obj["semester"],obj["course"],"bin",obj["gradeable"])
    form_json_config = os.path.join(SUBMITTY_DATA_DIR,"courses",obj["semester"],obj["course"],"config","form","form_"+obj["gradeable"]+".json")
    complete_config = os.path.join(SUBMITTY_DATA_DIR,"courses",obj["semester"],obj["course"],"config","complete_config","complete_config_"+obj["gradeable"]+".json")

    if not os.path.exists(form_json_config):
        autograding_utils.log_message(AUTOGRADING_LOG_PATH, job_id,message="ERROR: the form json file does not exist " + form_json_config)
        raise RuntimeError("ERROR: the form json file does not exist ",form_json_config)
    if not os.path.exists(complete_config):
        autograding_utils.log_message(AUTOGRADING_LOG_PATH, job_id,message="ERROR: the complete config file does not exist " + complete_config)
        raise RuntimeError("ERROR: the complete config file does not exist ",complete_config)

    # --------------------------------------------------------------------
    # MAKE TEMPORARY DIRECTORY & COPY THE NECESSARY FILES THERE
    tmp = tempfile.mkdtemp()
    tmp_autograding = os.path.join(tmp,"TMP_AUTOGRADING")
    os.mkdir(tmp_autograding)
    tmp_submission = os.path.join(tmp,"TMP_SUBMISSION")
    os.mkdir(tmp_submission)

    copytree_if_exists(provided_code_path, os.path.join(tmp_autograding,"provided_code"))
    copytree_if_exists(instructor_solution_path, os.path.join(tmp_autograding,"instructor_solution"))
    copytree_if_exists(test_input_path, os.path.join(tmp_autograding,"test_input"))
    copytree_if_exists(test_output_path, os.path.join(tmp_autograding,"test_output"))
    copytree_if_exists(generated_output_path, os.path.join(tmp_autograding,"generated_output"))
    copytree_if_exists(custom_validation_code_path, os.path.join(tmp_autograding,"custom_validation_code"))
    copytree_if_exists(bin_path, os.path.join(tmp_autograding,"bin"))
    # Copy the default submitty_router into bin.
    router_path = os.path.join(SUBMITTY_INSTALL_DIR, "src", 'grading','python','submitty_router.py')
    shutil.copy(router_path, os.path.join(tmp_autograding,"bin"))


    shutil.copy(form_json_config,os.path.join(tmp_autograding,"form.json"))
    shutil.copy(complete_config,os.path.join(tmp_autograding,"complete_config.json"))
    
    if "generate_output" not in obj:
        checkout_path = os.path.join(SUBMITTY_DATA_DIR,"courses",obj["semester"],obj["course"],"checkout",partial_path)
        results_path = os.path.join(SUBMITTY_DATA_DIR,"courses",obj["semester"],obj["course"],"results",partial_path)
    elif obj["generate_output"]:
        results_path = os.path.join(SUBMITTY_DATA_DIR, "courses", obj["semester"], obj["course"], "generated_output", obj["gradeable"])

    # grab a copy of the current history.json file (if it exists)
    history_file = os.path.join(results_path,"history.json")
    history_file_tmp = ""
    if os.path.isfile(history_file):
        shutil.copy(history_file,os.path.join(tmp_submission,"history.json"))
    # get info from the gradeable config file
    with open(complete_config, 'r') as infile:
        complete_config_obj = json.load(infile)
    if 'generate_output' not in obj:
        checkout_subdirectory = complete_config_obj["autograding"].get("use_checkout_subdirectory","")
        checkout_subdir_path = os.path.join(checkout_path,checkout_subdirectory)
    queue_file = os.path.join(next_directory,next_to_grade)

    # switch to tmp directory
    os.chdir(tmp)

    # make the logs directory
    tmp_logs = os.path.join(tmp,"TMP_SUBMISSION","tmp_logs")
    os.makedirs(tmp_logs)
    # 'touch' a file in the logs folder
    open(os.path.join(tmp_logs,"overall.txt"), 'a')

    # --------------------------------------------------------------------
    # CONFIRM WE HAVE A CHECKOUT OF THE STUDENT'S REPO
    if "generate_output" not in obj:
        if is_vcs:
            # there should be a checkout log file in the results directory
            # move that file to the tmp logs directory..
            vcs_checkout_logfile = os.path.join(results_path,"logs","vcs_checkout.txt")
            if os.path.isfile(vcs_checkout_logfile):
                shutil.move(vcs_checkout_logfile,tmp_logs)
            else:
                autograding_utils.log_message(AUTOGRADING_LOG_PATH, job_id, message=" ERROR: missing vcs_checkout.txt logfile "+str(vcs_checkout_logfile))

    if "generate_output" not in obj:
        copytree_if_exists(submission_path,os.path.join(tmp_submission,"submission"))
        copytree_if_exists(checkout_path,os.path.join(tmp_submission,"checkout"))
    obj["queue_time"] = queue_time_longstring
    obj["regrade"] = is_batch_job
    obj["waittime"] = waittime
    obj["job_id"] = job_id

    with open(os.path.join(tmp_submission,"queue_file.json"),'w') as outfile:
        json.dump(obj,outfile,sort_keys=True,indent=4,separators=(',', ': '))

    user_assignment_access_json = os.path.join(
        SUBMITTY_DATA_DIR,"courses",obj["semester"],obj["course"],
        "submissions",obj["gradeable"],obj["who"],"user_assignment_access.json")
    user_assignment_settings_json = os.path.join(
        SUBMITTY_DATA_DIR,"courses",obj["semester"],obj["course"],
        "submissions",obj["gradeable"],obj["who"],"user_assignment_settings.json")

    if os.path.exists(user_assignment_access_json):
        shutil.copy(user_assignment_access_json,  os.path.join(tmp_submission,"user_assignment_access.json"))
    if os.path.exists(user_assignment_settings_json):
        shutil.copy(user_assignment_settings_json,os.path.join(tmp_submission,"user_assignment_settings.json"))

    grading_began_longstring = dateutils.write_submitty_date(grading_began)
    with open(os.path.join(tmp_submission,".grading_began"), 'w') as f:
        print (grading_began_longstring,file=f)

    # zip up autograding & submission folders
    filehandle1, my_autograding_zip_file =tempfile.mkstemp()
    filehandle2, my_submission_zip_file =tempfile.mkstemp()
    autograding_utils.zip_my_directory(tmp_autograding,my_autograding_zip_file)
    autograding_utils.zip_my_directory(tmp_submission,my_submission_zip_file)
    os.close(filehandle1)
    os.close(filehandle2)
    # cleanup
    shutil.rmtree(tmp_autograding)
    shutil.rmtree(tmp_submission)
    shutil.rmtree(tmp)

    return (my_autograding_zip_file,my_submission_zip_file)


# ==================================================================================
# ==================================================================================
def unpack_grading_results_zip(which_machine,which_untrusted,my_results_zip_file):
    os.chdir(SUBMITTY_DATA_DIR)

    queue_obj = unzip_queue_file(my_results_zip_file)

    if queue_obj is None:
        return False

    job_id = queue_obj["job_id"]    
    if "generate_output" not in queue_obj:    
        partial_path = os.path.join(queue_obj["gradeable"],queue_obj["who"],str(queue_obj["version"]))
        item_name = os.path.join(queue_obj["semester"],queue_obj["course"],"submissions",partial_path)
        results_path = os.path.join(SUBMITTY_DATA_DIR,"courses",queue_obj["semester"],queue_obj["course"],"results",partial_path)
        results_public_path = os.path.join(SUBMITTY_DATA_DIR,"courses",queue_obj["semester"],queue_obj["course"],"results_public",partial_path)
    elif queue_obj["generate_output"]:
        item_name = os.path.join(queue_obj["semester"],queue_obj["course"],"generated_output",queue_obj["gradeable"])
        results_path = os.path.join(SUBMITTY_DATA_DIR,"courses",queue_obj["semester"],queue_obj["course"], "generated_output",queue_obj["gradeable"])
        results_public_path = os.path.join(SUBMITTY_DATA_DIR,"courses",queue_obj["semester"],queue_obj["course"], "generated_output",queue_obj["gradeable"])


    # clean out all of the old files if this is a re-run
    shutil.rmtree(results_path,ignore_errors=True)
    shutil.rmtree(results_public_path,ignore_errors=True)
    # create the directory (and the full path if it doesn't already exist)
    os.makedirs(results_path)

    # unzip the file & clean up
    autograding_utils.unzip_this_file(my_results_zip_file,results_path)

    # if there are files for the public results folder, create the directory and move them out
    if (os.path.isdir(os.path.join(os.path.join(results_path,"results_public")))):
        os.makedirs(results_public_path,exist_ok=True)
        os.rename(os.path.join(results_path,"results_public"),
                  os.path.join(results_public_path,"details"))

    os.remove(my_results_zip_file)

    if "generate_output" not in queue_obj:
        # add information to the database
        insert_database_version_data.insert_to_database(
            queue_obj["semester"],
            queue_obj["course"],
            queue_obj["gradeable"],
            queue_obj["user"],
            queue_obj["team"],
            queue_obj["who"],
            True if queue_obj["is_team"] else False,
            str(queue_obj["version"]))
    
    if "generate_output" not in queue_obj:
        submission_path = os.path.join(SUBMITTY_DATA_DIR,"courses",item_name)

        is_batch_job = queue_obj["regrade"]
        gradingtime = queue_obj["gradingtime"]
        grade_result = queue_obj["grade_result"]

        print (which_machine,which_untrusted,"unzip",item_name, " in ", int(gradingtime), " seconds")

        autograding_utils.log_message(AUTOGRADING_LOG_PATH, job_id,is_batch_job,"unzip",item_name,"grade:",gradingtime,grade_result)
    else:
        is_batch_job = queue_obj["regrade"]
        autograding_utils.log_message(AUTOGRADING_LOG_PATH, job_id,is_batch_job,message="Generated Output Successfully")
    return True


# ==================================================================================
# ==================================================================================
