import json
import os
import tempfile
import shutil
import subprocess

from . import CONFIG_PATH, autograding_utils, testcase

with open(os.path.join(CONFIG_PATH, 'submitty.json')) as open_file:
    OPEN_JSON = json.load(open_file)
AUTOGRADING_LOG_PATH = OPEN_JSON['autograding_log_path']
AUTOGRADING_STACKTRACE_PATH = os.path.join(OPEN_JSON['autograding_log_path'], 'stack_traces')
SUBMITTY_INSTALL_DIR = OPEN_JSON['submitty_install_dir']

with open(os.path.join(CONFIG_PATH, 'submitty_users.json')) as open_file:
    OPEN_JSON = json.load(open_file)
DAEMON_UID = OPEN_JSON['daemon_uid']
# ==================================================================================
# ==================================================================================

def grade_from_zip(working_directory, which_untrusted, autograding_zip_file, submission_zip_file):

    os.chdir(SUBMITTY_DATA_DIR)

    autograding_utils.prepare_directory_for_autograding(working_directory, which_untrusted, 
                                                        autograding_zip_file, submission_zip_file, False)

    os.remove(autograding_zip_file)
    os.remove(submission_zip_file)

    tmp_autograding = os.path.join(working_directory,"TMP_AUTOGRADING")
    tmp_submission = os.path.join(working_directory,"TMP_SUBMISSION")
    tmp_work = os.path.join(working_directory,"TMP_WORK")
    tmp_logs = os.path.join(working_directory,"TMP_SUBMISSION","tmp_logs")
    tmp_results = os.path.join(working_directory,"TMP_RESULTS")
    submission_path = os.path.join(tmp_submission, "submission")

    with open(os.path.join(tmp_submission,"queue_file.json"), 'r') as infile:
        queue_obj = json.load(infile)
    waittime = queue_obj["waittime"]
    is_batch_job = queue_obj["regrade"]
    job_id = queue_obj["job_id"]
    
    item_name = os.path.join(queue_obj["semester"], queue_obj["course"], "submissions", 
                             queue_obj["gradeable"],queue_obj["who"],str(queue_obj["version"]))
    autograding_utils.log_message(AUTOGRADING_LOG_PATH, job_id, is_batch_job, which_untrusted, item_name, "wait:", waittime, "")

    with open(os.path.join(tmp_autograding, "complete_config.json"), 'r') as infile:
        complete_config_obj = json.load(infile)

    # grab the submission time
    with open(os.path.join(tmp_submission, 'submission' ,".submit.timestamp"), 'r') as submission_time_file:
        submission_string = submission_time_file.read().rstrip()

    with open(os.path.join(tmp_autograding, "form.json"), 'r') as infile:
        gradeable_config_obj = json.load(infile)
    is_vcs = gradeable_config_obj["upload_type"] == "repository"

    # LOAD THE TESTCASES
    testcases = list()
    testcase_num = 1
    for t in complete_config_obj['testcases']:
        testcase_folder = os.path.join("test{:02}".format(testcase_num))
        tmp_test = Testcase.Testcase(testcase_num, testcase_folder, queue_obj, complete_config_obj, t,
                                     which_untrusted, is_vcs, job_id, is_batch_job, working_directory, testcases,
                                     submission_string, AUTOGRADING_LOG_PATH, AUTOGRADING_STACKTRACE_PATH, False)
        testcases.append( tmp_test )
        testcase_num += 1

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

                killall_success = subprocess.call([os.path.join(SUBMITTY_INSTALL_DIR, "sbin", "untrusted_execute"),
                                                   which_untrusted,
                                                   os.path.join(SUBMITTY_INSTALL_DIR, "sbin", "killall.py")],
                                                   stdout=logfile)
      
                print ("LOGGING END my_runner.out",file=logfile)
                if killall_success != 0:
                    self.log_message('RUNNER ERROR: had to kill {} process(es)'.format(killall_success))
                else:
                    print ("KILLALL COMPLETE my_runner.out",file=logfile)

        # VALIDATE
        for testcase in testcases:
            print ("====================================\nVALIDATION STARTS", file=overall_log)
            testcase.validate()
            subprocess.call(['ls', '-lR', self.directory], stdout=overall_log)

        os.chdir(working_directory)

        # ARCHIVE
        print ("====================================\nARCHIVING STARTS", file=overall_log)
        for testcase in testcases:
            testcase.archive_results(overall_log)
        subprocess.call(['ls', '-lR', '.'], stdout=open(tmp_logs + "/overall.txt", 'a'))

    
    autograding_utils.archive_autograding_results(working_directory, queue_obj, AUTOGRADING_LOG_PATH,
                                                  AUTOGRADING_STACKTRACE_PATH, DAEMON_UID)
    
    # zip up results folder
    filehandle, my_results_zip_file = tempfile.mkstemp()
    autograding_utils.zip_my_directory(tmp_results, my_results_zip_file)
    os.close(filehandle)
    shutil.rmtree(working_directory)
    return output_zip_file

if __name__ == "__main__":
    raise SystemExit('ERROR: Do not call this script directly')

