"""
Module that contains all of the jobs that the Submitty Daemon can do
"""

from abc import ABC, abstractmethod
import os
from pathlib import Path
import shutil
import subprocess

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


class SendEmail(CourseJob):
    def run_job(self):
        email_type = self.job_details['email_type']
        semester = self.job_details['semester']
        course = self.job_details['course']

        email_script = str(Path(INSTALL_DIR, 'sbin', 'sendEmail.py'))

        thread_title = self.job_details['thread_title']
        thread_content = self.job_details['thread_content']

        try:
            with open('email_job_logs.txt', "a") as output_file:
                subprocess.call([email_script, email_type, semester, course, thread_title, thread_content], stdout=output_file)
        except PermissionError:
            print ("error, could not open "+output_file+" for writing")
