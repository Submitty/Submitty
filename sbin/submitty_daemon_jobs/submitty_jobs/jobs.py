"""
Module that contains all of the jobs that the Submitty Daemon can do
"""

from abc import ABC, abstractmethod
import os
from pathlib import Path
import shutil
import subprocess
import json
import stat
import urllib.parse

from . import INSTALL_DIR, DATA_DIR


class AbstractJob(ABC):
    """
    Abstract class that all jobs should extend from, creating a common
    interface that can be expected for all jobs
    """

    required_keys = []

    def __init__(self, job_details):
        self.job_details = job_details

    def has_required_keys(self):
        """
        Validates that the job_details contains all keys specified in
        the required_keys class variable, and that the values for them
        is not None
        """
        for key in self.required_keys:
            if key not in self.job_details or self.job_details[key] is None:
                return False
        return True

    def validate_job_details(self):
        """
        Checks to see if the passed in job details contain all
        necessary components to run the particular job.
        """
        return True

    @abstractmethod
    def run_job(self):
        pass

    def cleanup_job(self):
        pass


# pylint: disable=abstract-method
class CourseJob(AbstractJob):
    """
    Base class for jobs that involve operation for a course.
    This class validates that the job details includes a semester
    and course and that they are valid directories within Submitty
    """

    required_keys = [
        'semester',
        'course'
    ]

    def validate_job_details(self):
        for key in ['semester', 'course']:
            if key not in self.job_details or self.job_details[key] is None:
                return False
            if self.job_details[key] in ['', '.', '..']:
                return False
            if self.job_details[key] != os.path.basename(self.job_details[key]):
                return False
        test_path = Path(DATA_DIR, 'courses', self.job_details['semester'], self.job_details['course'])
        return test_path.exists()


# pylint: disable=abstract-method
class CourseGradeableJob(CourseJob):
    """
    Base class for jobs that involve a semester/course as well as a gradeable, validating
    that we have a gradeable within our job details, and that it's not empty nor just a
    dot or two dots.
    """

    required_keys = CourseJob.required_keys + ['gradeable']

    def validate_job_details(self):
        if not super().validate_job_details():
            return False
        if 'gradeable' not in self.job_details or self.job_details['gradeable'] is None:
            return False
        if self.job_details['gradeable'] != os.path.basename(self.job_details['gradeable']):
            return False
        self.job_details['gradeable'] = os.path.basename(self.job_details['gradeable'])
        return self.job_details['gradeable'] not in ['', '.', '..']


class BuildConfig(CourseGradeableJob):
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
            print("error, could not open "+output_file+" for writing")


class RunLichen(CourseGradeableJob):
    def run_job(self):
        semester = self.job_details['semester']
        course = self.job_details['course']
        gradeable = self.job_details['gradeable']

        lichen_script = Path(INSTALL_DIR, 'sbin', 'run_lichen_plagiarism.py')

        # create directory for logging
        logging_dir = os.path.join(DATA_DIR, 'courses', semester, course, 'lichen', 'logs', gradeable)
        if(not os.path.isdir(logging_dir)):
            os.makedirs(logging_dir)

        lichen_output = Path(DATA_DIR, 'courses', semester, course, 'lichen', 'logs', gradeable, 'lichen_job_output.txt')

        try:
            with lichen_output.open("w") as output_file:
                subprocess.call([str(lichen_script), semester, course, gradeable], stdout=output_file, stderr=output_file)
        except PermissionError:
            print("error, could not open "+output_file+" for writing")


class DeleteLichenResult(CourseGradeableJob):
    def run_job(self):
        semester = self.job_details['semester']
        course = self.job_details['course']
        gradeable = self.job_details['gradeable']

        lichen_dir = Path(DATA_DIR, 'courses', semester, course, 'lichen')
        if not lichen_dir.exists():
            return

        log_file = Path(DATA_DIR, 'courses', semester, course, 'lichen', 'logs', gradeable, 'lichen_job_output.txt')

        with log_file.open('w') as open_file:
            lichen_json = 'lichen_{}_{}_{}.json'.format(semester, course, gradeable)
            config = Path(lichen_dir, 'config', lichen_json)
            if config.exists():
                config.unlink()
            ranking = Path(lichen_dir, 'ranking', '{}.txt'.format(gradeable))
            if ranking.exists():
                ranking.unlink()
            for folder in ['provided_code', 'tokenized', 'concatenated', 'hashes', 'matches']:
                shutil.rmtree(str(Path(lichen_dir, folder, gradeable)), ignore_errors=True)
            msg = 'Deleted lichen plagiarism results and saved config for {}'.format(gradeable)
            open_file.write(msg) 

class BulkQRSplit(CourseJob):
    required_keys = CourseJob.required_keys + ['timestamp', 'g_id', 'filename']

    def add_permissions(self,item,perms):
        if os.getuid() == os.stat(item).st_uid:
            os.chmod(item,os.stat(item).st_mode | perms)

    def add_permissions_recursive(self,top_dir,root_perms,dir_perms,file_perms):
        for root, dirs, files in os.walk(top_dir):
            self.add_permissions(root,root_perms)
        for d in dirs:
            self.add_permissions(os.path.join(root, d),dir_perms)
        for f in files:
            self.add_permissions(os.path.join(root, f),file_perms)

    def run_job(self):
        semester = self.job_details['semester']
        course = self.job_details['course']
        timestamp = self.job_details['timestamp']
        gradeable_id = self.job_details['g_id']
        filename = self.job_details['filename']

        qr_prefix = urllib.parse.unquote(self.job_details['qr_prefix'])
        qr_suffix = urllib.parse.unquote(self.job_details['qr_suffix'])

        qr_script = Path(INSTALL_DIR, 'sbin', 'bulk_qr_split.py')
        #create paths
        try:
            with open("/usr/local/submitty/config/submitty.json", encoding='utf-8') as data_file:
                CONFIG = json.loads(data_file.read())

            current_path = os.path.dirname(os.path.realpath(__file__))
            uploads_path = os.path.join(CONFIG["submitty_data_dir"],"courses",semester,course,"uploads")
            bulk_path = os.path.join(CONFIG["submitty_data_dir"],"courses",semester,course,"uploads/bulk_pdf",gradeable_id,timestamp)
            split_path = os.path.join(CONFIG["submitty_data_dir"],"courses",semester,course,"uploads/split_pdf",gradeable_id,timestamp)
        except Exception as err:
            print("Failed while parsing args and creating paths")
            print(err)
            sys.exit(1)

        #copy file over to correct folders
        try:
            if not os.path.exists(split_path):
                os.makedirs(split_path)

            # adding write permissions for PHP
            self.add_permissions_recursive(uploads_path, stat.S_IWGRP | stat.S_IXGRP, stat.S_IWGRP | stat.S_IXGRP, stat.S_IWGRP)

            # copy over file to new directory
            if not os.path.isfile(os.path.join(split_path, filename)):
                shutil.copyfile(os.path.join(bulk_path, filename), os.path.join(split_path, filename))

            # move to copy folder
            os.chdir(split_path)
        except Exception as err:
            print("Failed while copying files")
            print(err)
            sys.exit(1)

        try:
            subprocess.call([str(qr_script), filename, split_path, qr_prefix, qr_suffix])
            
            os.chdir(current_path)
        except Exception as err:
            print("Failed to launch bulk_qr_split subprocess!")
            print(err)
            sys.exit(1)
        