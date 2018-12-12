"""
"""

from abc import ABC, abstractmethod
import os
import subprocess

from . import DATA_DIR


class AbstractJob(ABC):
    def __init__(self, job_details):
        self.job_details = job_details

    @abstractmethod
    def run_job(self):
        pass

    def cleanup_job(self):
        pass


class BuildConfig(AbstractJob):
    def run_job(self):
        semester = self.job_details['semester']
        course = self.job_details['course']
        gradeable = self.job_details['gradeable']

        build_script = os.path.join(DATA_DIR, 'courses', semester, course, 'BUILD_{}.sh'.format(course))
        build_output = os.path.join(DATA_DIR, 'courses', semester, course, 'build_script_output.txt')

        try:
            with open(build_output, "w") as output_file:
                subprocess.call([build_script, gradeable, "--clean"], stdout=output_file, stderr=output_file)
        except PermissionError:
            print ("error, could not open "+output_file+" for writing")


class RunLichen(AbstractJob):
    def run_job(self):
        semester = self.job_details['semester']
        course = self.job_details['course']
        gradeable = self.job_details['gradeable']

        lichen_script = '/usr/local/submitty/sbin/run_lichen_plagiarism.py'
        lichen_output = os.path.join(DATA_DIR, 'courses', semester, course, 'lichen', 'lichen_job_output.txt')

        try:
            with open(lichen_output, "w") as output_file:
                subprocess.call([lichen_script, semester, course, gradeable], stdout=output_file, stderr=output_file)
        except PermissionError:
            print ("error, could not open "+output_file+" for writing")


class DeleteLichenResult(AbstractJob):
    def run_job(self):
        semester = self.job_details['semester']
        course = self.job_details['course']
        gradeable = self.job_details['gradeable']


        lichen_output = os.path.join(DATA_DIR, 'courses', semester, course, 'lichen', 'lichen_job_output.txt')

        with open(lichen_output, "w") as output_file:
            subprocess.call("rm"+ " /var/local/submitty/courses/" +semester+"/"+course+ "/lichen/config/lichen_"+semester+"_"+course+"_"+gradeable+".json", stdout=output_file, stderr=output_file, shell=True)
            subprocess.call("rm"+ " /var/local/submitty/courses/" +semester+"/"+course+ "/lichen/ranking/"+gradeable+".txt", stdout=output_file, stderr=output_file, shell=True)
            subprocess.call("rm"+ " -rf /var/local/submitty/courses/" +semester+"/"+course+ "/lichen/provided_code/"+gradeable, stdout=output_file, stderr=output_file, shell=True)
            subprocess.call("rm"+ " -rf /var/local/submitty/courses/" +semester+"/"+course+ "/lichen/tokenized/"+gradeable, stdout=output_file, stderr=output_file, shell=True)
            subprocess.call("rm"+ " -rf /var/local/submitty/courses/" +semester+"/"+course+ "/lichen/concatenated/"+gradeable, stdout=output_file, stderr=output_file, shell=True)
            subprocess.call("rm"+ " -rf /var/local/submitty/courses/" +semester+"/"+course+ "/lichen/hashes/"+gradeable, stdout=output_file, stderr=output_file, shell=True)
            subprocess.call("rm"+ " -rf /var/local/submitty/courses/" +semester+"/"+course+ "/lichen/matches/"+gradeable, stdout=output_file, stderr=output_file, shell=True)
            subprocess.call("echo"+ " Deleted lichen plagiarism results and saved config for "+gradeable, stdout=op, stderr=op,shell=True)


class SendEmail(AbstractJob):
    def run_job(self):
        email_type = self.job_details['email_type']
        semester = self.job_details['semester']
        course = self.job_details['course']

        email_script = '/usr/local/submitty/sbin/sendEmail.py'

        thread_title = self.job_details['thread_title']
        thread_content = self.job_details['thread_content']

        try:
            with open('email_job_logs.txt', "a") as output_file:
                subprocess.call([email_script, email_type, semester, course, thread_title, thread_content], stdout=output_file)
        except PermissionError:
            print ("error, could not open "+output_file+" for writing")
