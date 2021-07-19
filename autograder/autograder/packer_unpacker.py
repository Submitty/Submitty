import json
import os
import tempfile
import shutil
import time
import dateutil
import dateutil.parser
import string
import random
import zipfile
import traceback

from submitty_utils import dateutils
from . import insert_database_version_data, autograding_utils


# ==================================================================================
def get_queue_time(next_directory, next_to_grade):
    t = time.ctime(os.path.getctime(os.path.join(next_directory, next_to_grade)))
    t = dateutil.parser.parse(t)
    t = dateutils.get_timezone().localize(t)
    return t


def load_queue_file_obj(config, job_id, next_directory, next_to_grade):
    queue_file = os.path.join(next_directory, next_to_grade)
    if not os.path.isfile(queue_file):
        config.logger.log_message(f"ERROR: the file does not exist {queue_file}", job_id=job_id)
        raise RuntimeError("ERROR: the file does not exist", queue_file)
    with open(queue_file, 'r') as infile:
        obj = json.load(infile)
    return obj


def get_vcs_info(config, top_dir, semester, course, gradeable, userid,  teamid):
    # Top level directory for this course
    course_dir = os.path.join(top_dir, 'courses', semester, course)
    form_json_file = os.path.join(course_dir, 'config', 'form', f'form_{gradeable}.json')
    with open(form_json_file, 'r') as fj:
        form_json = json.load(fj)
    course_json_path = os.path.join(course_dir, 'config', 'config.json')
    with open(course_json_path, 'r') as open_file:
        course_json = json.load(open_file)
    is_vcs = form_json["upload_type"] == "repository"
    # PHP reads " as a character around the string, while Python reads it as part of the string
    # so we have to strip out the " in python
    vcs_type = course_json['course_details']['vcs_type']
    vcs_base_url = course_json['course_details']['vcs_base_url']

    vcs_url = config.submitty['vcs_url']
    if vcs_url is None or len(vcs_url) == 0:
        vcs_url = config.submitty['submission_url'].rstrip('/') + '/{$vcs_type}'

    if len(vcs_base_url) == 0:
        vcs_base_url = "/".join([vcs_url, semester, course]).rstrip('/') + "/"
    vcs_base_url = vcs_base_url.replace(
        config.submitty['submission_url'],
        os.path.join(config.submitty['submitty_data_dir'], 'vcs')
    )
    vcs_base_url = vcs_base_url.replace('{$vcs_type}', vcs_type)
    vcs_subdirectory = form_json["subdirectory"] if is_vcs else ''
    vcs_subdirectory = vcs_subdirectory.replace("{$vcs_type}", vcs_type)
    vcs_subdirectory = vcs_subdirectory.replace("{$gradeable_id}", gradeable)
    vcs_subdirectory = vcs_subdirectory.replace("{$user_id}", userid)
    vcs_subdirectory = vcs_subdirectory.replace("{$team_id}", teamid)
    return is_vcs, vcs_type, vcs_base_url, vcs_subdirectory


def copytree_if_exists(config, job_id, source, target):
    # target must not exist!
    if os.path.exists(target):
        raise RuntimeError("ERROR: the target directory already exists", target)
    # source might exist
    if not os.path.isdir(source):
        os.mkdir(target)
    else:
        try:
            # Symlinks should generally not be needed/used in student
            # repositories, but they appeared accidentally/unintentionally.
            #
            # We will not fail on a dangling/broken symlink.  And we will also
            # not copy / unroll the symlink -- which could be a security risk.
            #
            # Note: Broken symlinks or symlinks to files the user does
            # not have permission to read will not be copied to the
            # worker machine for autograding and will be 'missing'
            # from the list of files.  It would be nice to pass a
            # warning message of the broken symlink back to the
            # student.
            shutil.copytree(source, target, symlinks=True, ignore_dangling_symlinks=True)
        except Exception as error:
            config.logger.log_message(
                f"ERROR: '{str(error)} attempting to copytree_if_exists: {source}->{target}",
                job_id=job_id,
            )


def unzip_queue_file(zipfilename):
    # be sure the zip file is ok, and contains the queue file
    if not os.path.exists(zipfilename):
        raise RuntimeError("ERROR: zip file does not exist", zipfilename)
    zip_ref = zipfile.ZipFile(zipfilename, 'r')
    queue_file_name = "queue_file.json"
    names = zip_ref.namelist()

    # Verify that the queue file is in the zip file
    if queue_file_name not in names:
        raise RuntimeError("ERROR: zip file does not contain queue file", zipfilename)

    # remember the current directory
    cur_dir = os.getcwd()
    # create a temporary directory and go to it
    tmp_dir = tempfile.mkdtemp()
    os.chdir(tmp_dir)
    # extract the queue file
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
def prepare_autograding_and_submission_zip(
    config,
    machine_name: str,
    which_machine,
    which_untrusted,
    next_directory,
    next_to_grade
):
    os.chdir(config.submitty['submitty_data_dir'])

    # generate a random id to be used to track this job in the autograding logs
    job_id = ''.join(random.choice(string.ascii_letters + string.digits) for _ in range(6))

    # --------------------------------------------------------
    # figure out what we're supposed to grade & error checking
    obj = load_queue_file_obj(config, job_id, next_directory, next_to_grade)
    # The top level course directory for this class
    course_dir = os.path.join(
        config.submitty['submitty_data_dir'],
        'courses',
        obj["semester"],
        obj["course"]
    )
    if "generate_output" not in obj:
        partial_path = os.path.join(obj["gradeable"], obj["who"], str(obj["version"]))
        item_name = os.path.join(obj["semester"], obj["course"], "submissions", partial_path)
        submission_path = os.path.join(config.submitty['submitty_data_dir'], "courses", item_name)
        if not os.path.isdir(submission_path):
            config.logger.log_message(
                f"ERROR: the submission directory does not exist: {submission_path}",
                job_id=job_id
            )
            raise RuntimeError("ERROR: the submission directory does not exist", submission_path)
        print(which_machine, which_untrusted, "prepare zip", submission_path)
        is_vcs, vcs_type, vcs_base_url, vcs_subdirectory = get_vcs_info(
            config,
            config.submitty['submitty_data_dir'],
            obj["semester"],
            obj["course"],
            obj["gradeable"],
            obj["who"],
            obj["team"]
        )

    elif obj["generate_output"]:
        item_name = os.path.join(
            obj["semester"],
            obj["course"],
            "generated_output",
            obj["gradeable"]
        )

    is_batch_job = "regrade" in obj and obj["regrade"]

    queue_time = get_queue_time(next_directory, next_to_grade)
    grading_began = dateutils.get_current_time()
    waittime = (grading_began-queue_time).total_seconds()
    config.logger.log_message(
        "",
        job_id=job_id,
        is_batch=is_batch_job,
        which_untrusted="zip",
        jobname=item_name,
        timelabel="wait:",
        elapsed_time=waittime,
    )

    # --------------------------------------------------------
    # various paths

    provided_code_path = os.path.join(course_dir, "provided_code", obj["gradeable"])
    instructor_solution_path = os.path.join(course_dir, "instructor_solution", obj["gradeable"])
    test_input_path = os.path.join(course_dir, "test_input", obj["gradeable"])
    test_output_path = os.path.join(course_dir, "test_output", obj["gradeable"])
    bin_path = os.path.join(course_dir, "bin", obj["gradeable"])
    form_json_config = os.path.join(course_dir, "config", "form", f"form_{obj['gradeable']}.json")
    custom_validation_code_path = os.path.join(
        course_dir,
        "custom_validation_code",
        obj["gradeable"]
    )
    generated_output_path = os.path.join(
        course_dir,
        "generated_output",
        obj["gradeable"],
        "random_output"
    )
    complete_config = os.path.join(
        course_dir,
        "config",
        "complete_config",
        f"complete_config_{obj['gradeable']}.json"
    )

    if not os.path.exists(form_json_config):
        config.logger.log_message(
            f"ERROR: the form json file does not exist: {form_json_config}",
            job_id=job_id
        )
        raise RuntimeError(f"ERROR: the form json file does not exist: {form_json_config}")

    if not os.path.exists(complete_config):
        config.logger.log_message(
            f"ERROR: the complete config file does not exist {complete_config}",
            job_id=job_id
        )
        raise RuntimeError(f"ERROR: the complete config file does not exist {complete_config}")

    # --------------------------------------------------------------------
    # MAKE TEMPORARY DIRECTORY & COPY THE NECESSARY FILES THERE
    tmp = tempfile.mkdtemp()
    tmp_autograding = os.path.join(tmp, "TMP_AUTOGRADING")
    os.mkdir(tmp_autograding)
    tmp_submission = os.path.join(tmp, "TMP_SUBMISSION")
    os.mkdir(tmp_submission)

    copytree_if_exists(config, job_id, provided_code_path,
                       os.path.join(tmp_autograding, "provided_code"))
    copytree_if_exists(config, job_id, test_input_path,
                       os.path.join(tmp_autograding, "test_input"))
    copytree_if_exists(config, job_id, test_output_path,
                       os.path.join(tmp_autograding, "test_output"))
    copytree_if_exists(config, job_id, generated_output_path,
                       os.path.join(tmp_autograding, "generated_output"))
    copytree_if_exists(config, job_id, bin_path, os.path.join(tmp_autograding, "bin"))
    copytree_if_exists(config, job_id, instructor_solution_path,
                       os.path.join(tmp_autograding, "instructor_solution"))
    copytree_if_exists(config, job_id, custom_validation_code_path,
                       os.path.join(tmp_autograding, "custom_validation_code"))

    # Copy the default submitty_router into bin.
    router_path = os.path.join(
        config.submitty['submitty_install_dir'],
        'src',
        'grading',
        'python',
        'submitty_router.py'
    )
    shutil.copy(router_path, os.path.join(tmp_autograding, "bin"))
    shutil.copy(form_json_config, os.path.join(tmp_autograding, "form.json"))
    shutil.copy(complete_config, os.path.join(tmp_autograding, "complete_config.json"))

    if "generate_output" not in obj:
        checkout_path = os.path.join(course_dir, "checkout", partial_path)
        results_path = os.path.join(course_dir, "results", partial_path)
    elif obj["generate_output"]:
        results_path = os.path.join(course_dir, "generated_output", obj["gradeable"])

    # grab a copy of the current history.json file (if it exists)
    history_file = os.path.join(results_path, "history.json")
    if os.path.isfile(history_file):
        shutil.copy(history_file, os.path.join(tmp_submission, "history.json"))

    # switch to tmp directory
    os.chdir(tmp)

    # make the logs directory
    tmp_logs = os.path.join(tmp, "TMP_SUBMISSION", "tmp_logs")
    os.makedirs(tmp_logs)
    # 'touch' a file in the logs folder
    open(os.path.join(tmp_logs, "overall.txt"), 'a')

    # --------------------------------------------------------------------
    # CONFIRM WE HAVE A CHECKOUT OF THE STUDENT'S REPO
    if "generate_output" not in obj:
        if is_vcs:
            # there should be a checkout log file in the results directory
            # move that file to the tmp logs directory.
            vcs_checkout_logfile = os.path.join(results_path, "logs", "vcs_checkout.txt")
            if os.path.isfile(vcs_checkout_logfile):
                shutil.move(vcs_checkout_logfile, tmp_logs)
            else:
                config.logger.log_message(
                    message=f"ERROR: missing vcs_checkout.txt logfile {str(vcs_checkout_logfile)}",
                    job_id=job_id
                )

    if "generate_output" not in obj:
        copytree_if_exists(config, job_id, submission_path,
                           os.path.join(tmp_submission, "submission"))
        copytree_if_exists(config, job_id, checkout_path,
                           os.path.join(tmp_submission, "checkout"))
    obj["queue_time"] = dateutils.write_submitty_date(queue_time)
    obj["regrade"] = is_batch_job
    obj["waittime"] = waittime
    obj["job_id"] = job_id
    obj["which_machine"] = machine_name

    with open(os.path.join(tmp_submission, "queue_file.json"), 'w') as outfile:
        json.dump(obj, outfile, sort_keys=True, indent=4, separators=(',', ': '))

    user_assignment_settings_json = os.path.join(
        config.submitty['submitty_data_dir'], "courses", obj["semester"], obj["course"],
        "submissions", obj["gradeable"], obj["who"], "user_assignment_settings.json")

    if os.path.exists(user_assignment_settings_json):
        shutil.copy(
            user_assignment_settings_json,
            os.path.join(tmp_submission, "user_assignment_settings.json")
        )

    grading_began_longstring = dateutils.write_submitty_date(grading_began)
    with open(os.path.join(tmp_submission, ".grading_began"), 'w') as f:
        print(grading_began_longstring, file=f)

    # zip up autograding & submission folders
    filehandle1, my_autograding_zip_file = tempfile.mkstemp()
    filehandle2, my_submission_zip_file = tempfile.mkstemp()
    autograding_utils.zip_my_directory(tmp_autograding, my_autograding_zip_file)
    autograding_utils.zip_my_directory(tmp_submission, my_submission_zip_file)
    os.close(filehandle1)
    os.close(filehandle2)
    # cleanup
    shutil.rmtree(tmp_autograding)
    shutil.rmtree(tmp_submission)
    shutil.rmtree(tmp)

    return (my_autograding_zip_file, my_submission_zip_file)


# ==================================================================================
# ==================================================================================
def unpack_grading_results_zip(config, which_machine, which_untrusted, my_results_zip_file):
    os.chdir(config.submitty['submitty_data_dir'])

    queue_obj = unzip_queue_file(my_results_zip_file)

    if queue_obj is None:
        return False

    job_id = queue_obj["job_id"]
    course_dir = os.path.join(
        config.submitty['submitty_data_dir'],
        "courses",
        queue_obj["semester"],
        queue_obj["course"]
    )
    if "generate_output" not in queue_obj:
        partial_path = os.path.join(
            queue_obj["gradeable"],
            queue_obj["who"],
            str(queue_obj["version"])
        )
        item_name = os.path.join(
            queue_obj["semester"],
            queue_obj["course"],
            "submissions",
            partial_path
        )
        results_path = os.path.join(course_dir, "results", partial_path)
        results_public_path = os.path.join(course_dir, "results_public", partial_path)
    elif queue_obj["generate_output"]:
        item_name = os.path.join(
            queue_obj["semester"],
            queue_obj["course"],
            "generated_output",
            queue_obj["gradeable"]
        )
        results_path = os.path.join(course_dir, "generated_output", queue_obj["gradeable"])
        results_public_path = os.path.join(course_dir, "generated_output", queue_obj["gradeable"])

    # clean out all of the old files if this is a re-run
    shutil.rmtree(results_path, ignore_errors=True)
    shutil.rmtree(results_public_path, ignore_errors=True)
    # create the directory (and the full path if it doesn't already exist)
    os.makedirs(results_path)

    # unzip the file & clean up
    autograding_utils.unzip_this_file(my_results_zip_file, results_path)

    # if there are files for the public results folder, create the directory and move them out
    if (os.path.isdir(os.path.join(os.path.join(results_path, "results_public")))):
        os.makedirs(results_public_path, exist_ok=True)
        os.rename(os.path.join(results_path, "results_public"),
                  os.path.join(results_public_path, "details"))

    os.remove(my_results_zip_file)

    if "generate_output" not in queue_obj:
        # add information to the database
        try:
            insert_database_version_data.insert_into_database(
                config,
                queue_obj["semester"],
                queue_obj["course"],
                queue_obj["gradeable"],
                queue_obj["user"],
                queue_obj["team"],
                queue_obj["who"],
                True if queue_obj["is_team"] else False,
                str(queue_obj["version"])
            )
        except Exception:
            config.logger.log_message(
                message="ERROR: Could not score into database",
                job_id=job_id,
            )
            config.logger.log_stack_trace(
                trace=traceback.format_exc(),
                job_id=job_id,
            )
            return False

    if "generate_output" not in queue_obj:
        is_batch_job = queue_obj["regrade"]
        gradingtime = queue_obj["gradingtime"]
        grade_result = queue_obj["grade_result"]

        print(f'{which_machine} {which_untrusted} unzip {item_name} in {int(gradingtime)} seconds')

        config.logger.log_message(
            grade_result,
            job_id=job_id,
            is_batch=is_batch_job,
            which_untrusted="unzip",
            jobname=item_name,
            timelabel="grade:",
            elapsed_time=gradingtime,
        )
    else:
        is_batch_job = queue_obj["regrade"]
        config.logger.log_message(
            "Generated Output Successfully",
            job_id=job_id, is_batch=is_batch_job,
        )
    return True


# ==================================================================================
# ==================================================================================
