"""
Module that contains all of the jobs that the Submitty Daemon can do
"""

from abc import ABC, abstractmethod
import os
import json
from pathlib import Path
import shutil
import subprocess
import stat
import traceback
import datetime
import mimetypes
import docker
import requests
from urllib.parse import unquote
from tempfile import TemporaryDirectory
from . import bulk_qr_split
from . import bulk_upload_split
from . import generate_pdf_images
from . import INSTALL_DIR, DATA_DIR
from . import write_to_log as logger
from . import VERIFIED_ADMIN_USER


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

    def cleanup_job(self):  # noqa: B027
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

    def cleanup_job(self):
        pass


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


class RunAutoRainbowGrades(CourseJob):
    def run_job(self):

        semester = self.job_details['semester']
        course = self.job_details['course']

        path = os.path.join(INSTALL_DIR, 'sbin', 'auto_rainbow_grades.py')
        debug_output = os.path.join(DATA_DIR, 'courses', semester, course, 'rainbow_grades', 'auto_debug_output.txt')

        try:
            with open(debug_output, "w") as file:
                subprocess.call(['python3', path, semester, course], stdout=file, stderr=file)
        except PermissionError:
            print("error, could not open "+file+" for writing")


class BuildConfig(CourseGradeableJob):
    def run_job(self):
        semester = self.job_details['semester']
        course = self.job_details['course']
        gradeable = self.job_details['gradeable']

        build_script = os.path.join(DATA_DIR, 'courses', semester,
                                    course, f'BUILD_{course}.sh')
        build_output = os.path.join(DATA_DIR, 'courses', semester,
                                    course, 'build', gradeable,
                                    'build_script_output.txt')

        try:
            res = subprocess.run([build_script, gradeable, "--clean"],
                                 stdout=subprocess.PIPE,
                                 stderr=subprocess.STDOUT)
            with open(build_output, "w") as output_file:
                output_file.write(res.stdout.decode("ascii"))
        except PermissionError:
            print("error, could not open "+output_file+" for writing")


class RunGenerateRepos(CourseGradeableJob):
    def run_job(self):
        semester = self.job_details['semester']
        course = self.job_details['course']
        gradeable = self.job_details['gradeable']
        subdirectory = self.job_details['subdirectory']

        gen_script = os.path.join(INSTALL_DIR, 'bin', 'generate_repos.py')

        today = datetime.datetime.now()
        log_path = os.path.join(DATA_DIR, "logs", "vcs_generation")
        datestring = "{:04d}{:02d}{:02d}.txt".format(today.year, today.month, today.day)
        log_file_path = os.path.join(log_path, datestring)
        current_time = today.strftime("%m/%d/%Y, %H:%M:%S")
        try:
            with open(log_file_path, "a") as output_file:
                print("At time: "+current_time, file=output_file)
                output_file.flush()
                subprocess.run([
                    "sudo",
                    gen_script,
                    "--non-interactive",
                    semester,
                    course,
                    gradeable,
                    "--subdirectory",
                    subdirectory
                ], stdout=output_file, stderr=output_file)
        except PermissionError:
            print("error, could not open " + output_file + " for writing")


class RunLichen(CourseGradeableJob):
    def run_job(self):
        semester = self.job_details['semester']
        course = self.job_details['course']
        gradeable = self.job_details['gradeable']
        # We cast to an int to prevent malicious json files from containing invalid path components
        config_id = int(self.job_details['config_id'])
        config_data = self.job_details['config_data']

        # error checking
        # prevent backwards crawling
        if '..' in semester or '..' in course or '..' in gradeable:
            print('Error: Invalid path component ".." in string')
            return

        # paths
        lichen_dir = os.path.join(DATA_DIR, 'courses', semester, course, 'lichen')
        config_path = os.path.join(lichen_dir, gradeable, str(config_id))
        data_path = os.path.join(DATA_DIR, 'courses')

        with open(os.path.join(config_path, 'config.json'), 'w') as file:
            json.dump(config_data, file, indent=4)

        # run Lichen
        subprocess.call(['/usr/local/submitty/Lichen/bin/run_lichen.sh', config_path, data_path])


class DeleteLichenResult(CourseGradeableJob):
    def run_job(self):
        semester = self.job_details['semester']
        course = self.job_details['course']
        gradeable = self.job_details['gradeable']
        config_id = int(self.job_details['config_id'])

        lichen_dir = os.path.join(DATA_DIR, 'courses', semester, course, 'lichen')

        # error checking
        # prevent against backwards crawling
        if '..' in semester or '..' in course or '..' in gradeable:
            print('invalid path component ".." in string')
            return

        if not os.path.isdir(lichen_dir):
            return

        # delete the config directory
        shutil.rmtree(os.path.join(lichen_dir, gradeable, str(config_id)), ignore_errors=True)

        # if there are no other configs in this gradeable directory, remove it
        if len(os.listdir(os.path.join(lichen_dir, gradeable))) == 0:
            shutil.rmtree(os.path.join(lichen_dir, gradeable))


class BulkUpload(CourseJob):
    required_keys = CourseJob.required_keys + ['timestamp', 'g_id', 'filename', 'is_qr']

    def add_permissions(self, item, perms):
        if os.getuid() == os.stat(item).st_uid:
            os.chmod(item, os.stat(item).st_mode | perms)

    def add_permissions_recursive(self, top_dir, root_perms, dir_perms, file_perms):
        for root, dirs, files in os.walk(top_dir):
            self.add_permissions(root, root_perms)
            for d in dirs:
                self.add_permissions(os.path.join(root, d), dir_perms)
            for f in files:
                self.add_permissions(os.path.join(root, f), file_perms)

    def run_job(self):
        semester = self.job_details['semester']
        course = self.job_details['course']
        timestamp = self.job_details['timestamp']
        gradeable_id = self.job_details['g_id']
        filename = self.job_details['filename']
        is_qr = self.job_details['is_qr']

        if is_qr and ('qr_prefix' not in self.job_details or 'qr_suffix' not in self.job_details):
            msg = "did not pass in qr prefix or suffix"
            print(msg)
            return

        use_ocr = False
        if is_qr:
            qr_prefix = unquote(unquote(self.job_details['qr_prefix']))
            qr_suffix = unquote(unquote(self.job_details['qr_suffix']))
            use_ocr = self.job_details['use_ocr']
        else:
            if 'num' not in self.job_details:
                msg = "Did not pass in the number to divide " + filename + " by"
                print(msg)
                return
            num = self.job_details['num']

        today = datetime.datetime.now()
        log_path = os.path.join(DATA_DIR, "logs", "bulk_uploads")
        log_file_path = os.path.join(log_path, "{:04d}{:02d}{:02d}.txt".format(today.year, today.month, today.day))

        pid = os.getpid()
        log_msg = "Process " + str(pid) + ": Starting to split " + filename + " on " + timestamp + ". "
        if is_qr:
            log_msg += "QR bulk upload job, QR Prefx: \'" + qr_prefix + "\', QR Suffix: \'" + qr_suffix + "\'"
        else:
            log_msg += "Normal bulk upload job, pages per PDF: " + str(num)

        logger.write_to_log(log_file_path, log_msg)
        # create paths
        try:
            current_path = os.path.dirname(os.path.realpath(__file__))
            bulk_path = os.path.join(DATA_DIR, "courses", semester, course, "uploads/bulk_pdf", gradeable_id, timestamp)
            split_path = os.path.join(DATA_DIR, "courses", semester, course, "uploads/split_pdf", gradeable_id, timestamp)
        except Exception:
            msg = "Process " + str(pid) + ": Failed while parsing args and creating paths"
            print(msg)
            traceback.print_exc()
            logger.write_to_log(log_file_path, msg + "\n" + traceback.format_exc())

        # copy file over to correct folders
        try:
            if not os.path.exists(split_path):
                # if the directory has been made by another job continue as normal
                try:
                    os.makedirs(split_path)
                except Exception:
                    pass

            # copy over file to new directory
            if not os.path.isfile(os.path.join(split_path, filename)):
                shutil.copyfile(os.path.join(bulk_path, filename), os.path.join(split_path, filename))

            # reset permissions just in case, group needs read/write
            # access so submitty_php can view & delete pdfs when they are
            # assigned to a student and/or deleted
            self.add_permissions_recursive(split_path,
                                           stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR |   stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP |   stat.S_ISGID,  # noqa: E222
                                           stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR |   stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP |   stat.S_ISGID,  # noqa: E222
                                           stat.S_IRUSR | stat.S_IWUSR |                  stat.S_IRGRP | stat.S_IWGRP)  # noqa: E222

            # move to copy folder
            os.chdir(split_path)
        except Exception:
            msg = "Process " + str(pid) + ": Failed while copying files"
            print(msg)
            traceback.print_exc()
            logger.write_to_log(log_file_path, msg + "\n" + traceback.format_exc())
            return

        try:
            if is_qr:
                bulk_qr_split.main([filename, split_path, qr_prefix, qr_suffix, log_file_path, use_ocr])
            else:
                bulk_upload_split.main([filename, split_path, num, log_file_path])
        except Exception:
            msg = "Failed to launch bulk_split subprocess!"
            print(msg)
            traceback.print_exc()
            logger.write_to_log(log_file_path, msg + "\n" + traceback.format_exc())
            return

        os.chdir(split_path)
        # if the original file has been deleted, continue as normal
        try:
            os.remove(filename)
        except Exception:
            pass

        os.chdir(current_path)


class GeneratePdfImages(AbstractJob):
    def run_job(self):
        pdf_file_path = self.job_details['pdf_file_path']
        # optionally get redactions
        redactions = self.job_details.get('redactions', [])
        generate_pdf_images.main(pdf_file_path, [generate_pdf_images.Redaction(**r) for r in redactions])

    def cleanup_job(self):
        pass


# pylint: disable=abstract-method
class CreateCourse(AbstractJob):
    def validate_job_details(self):
        for key in ['semester', 'course', 'head_instructor', 'group_name']:
            if key not in self.job_details or self.job_details[key] is None:
                return False
            if self.job_details[key] in ['', '.', '..']:
                return False
            if self.job_details[key] != os.path.basename(self.job_details[key]):
                return False
        return True

    def run_job(self):
        semester = self.job_details['semester']
        course = self.job_details['course']
        head_instructor = self.job_details['head_instructor']
        base_group = self.job_details['group_name']

        log_file_path = Path(DATA_DIR, 'logs', 'course_creation', '{}_{}_{}_{}.txt'.format(
            semester, course, head_instructor, base_group
        ))

        with log_file_path.open("w") as output_file:
            subprocess.run(["sudo", "/usr/local/submitty/sbin/create_course.sh", semester, course, head_instructor, base_group], stdout=output_file, stderr=output_file)
            subprocess.run(["sudo", "/usr/local/submitty/sbin/adduser_course.py", head_instructor, semester, course], stdout=output_file, stderr=output_file)
            if VERIFIED_ADMIN_USER != "":
                subprocess.run(["sudo", "/usr/local/submitty/sbin/adduser_course.py", VERIFIED_ADMIN_USER, semester, course], stdout=output_file, stderr=output_file)

    def cleanup_job(self):
        pass


class UpdateDockerImages(AbstractJob):
    def run_job(self):
        today = datetime.datetime.now()
        log_path = os.path.join(DATA_DIR, "logs", "docker")
        log_file_path = os.path.join(log_path, "{:04d}{:02d}{:02d}.txt".format(today.year, today.month, today.day))
        flag = os.O_EXCL | os.O_WRONLY
        if not os.path.exists(log_file_path):
            flag = flag | os.O_CREAT
        log_fd = os.open(log_file_path, flag)
        script_path = os.path.join(INSTALL_DIR, 'sbin', 'shipper_utils', 'update_and_install_workers.py')
        with os.fdopen(log_fd, 'a') as output_file:
            subprocess.run(["python3", script_path, "--docker_images"], stdout=output_file, stderr=output_file)

        log_msg = "[Last ran on: {:04d}-{:02d}-{:02d} {:02d}:{:02d}:{:02d}]\n".format(today.year, today.month, today.day, today.hour, today.minute, today.second)
        logger.write_to_log(log_file_path, log_msg)

    def cleanup_job(self):
        pass


class UpdateSystemInfo(AbstractJob):
    def run_job(self):
        today = datetime.datetime.now()
        log_folder = os.path.join(DATA_DIR, "logs", "sysinfo")
        log_file = os.path.join(log_folder, f"{today.strftime('%Y%m%d')}.txt")

        flag = os.O_EXCL | os.O_WRONLY
        if not os.path.exists(log_file):
            flag = flag | os.O_CREAT
        log = os.open(log_file, flag)

        script = os.path.join(INSTALL_DIR, "sbin", "shipper_utils", "get_sysinfo.py")
        with os.fdopen(log, 'a') as output_file:
            subprocess.run(["python3", script, "--workers", "service", "disk", "sysload"],
                           stdout=output_file, stderr=output_file)

        log_msg = f"[Last ran on: {today.isoformat()}]\n"
        logger.write_to_log(log_file, log_msg)

    def cleanup_job(self):
        pass


class DocToPDF(AbstractJob):
    def run_job(self):
        log_dir = os.path.join(DATA_DIR, "logs", "doc_to_pdf")
        today = datetime.datetime.now()
        log_file = os.path.join(log_dir, "{:04d}{:02d}{:02d}.txt".format(today.year, today.month, today.day))
        log = open(log_file, 'a')
        log.write('\n')

        try:
            term = self.job_details['term']
            course = self.job_details['course']
            gradeable = self.job_details['gradeable']
            user = self.job_details['user']
            version = self.job_details['version']

            course_dir = os.path.join(DATA_DIR, 'courses', term, course)
            submissions_dir = os.path.join(course_dir, 'submissions')
            submissions_processed_dir = os.path.join(course_dir, 'submissions_processed')

            submissions_path = os.path.join(submissions_dir, gradeable, user, str(version))
            submissions_processed_path = os.path.join(submissions_processed_dir, gradeable, user, str(version), 'pdf')

            if not os.path.isdir(submissions_processed_path):
                os.makedirs(submissions_processed_path)

            DOC_MIME_TYPES = ['application/vnd.openxmlformats-officedocument.wordprocessingml.document']

            doc_files = []

            for root, _, files in os.walk(submissions_path):
                for file in files:
                    file_path = os.path.join(root, file)
                    mimetype, _ = mimetypes.guess_type(file_path)
                    if mimetype in DOC_MIME_TYPES:
                        doc_files.append(file_path)

            with TemporaryDirectory() as tmpdir:
                os.mkdir(os.path.join(tmpdir, 'doc_files'))

                for i in range(len(doc_files)):
                    numfolder = os.path.join(tmpdir, 'doc_files', str(i))
                    os.mkdir(numfolder)
                    shutil.copyfile(doc_files[i], os.path.join(numfolder, os.path.basename(doc_files[i])))

                client = docker.from_env(timeout=60)
                container = client.containers.run(
                    image='submitty/libreoffice-writer:latest',
                    command=['/bin/bash', '-c', f'for x in /app/doc_files/*/; \
                            do libreoffice --headless --convert-to pdf --outdir "$x"out "$x"*; done; \
                            chown -R {os.getuid()}:{os.getgid()} /app/doc_files'],
                    volumes={tmpdir: {'bind': '/app', 'mode': 'rw'}},
                    stdout=True,
                    stderr=True,
                    detach=True
                )

                try:
                    container.wait(timeout=8)
                except requests.exceptions.ConnectionError:
                    log.write("Container timed out...\n")

                log.write("Output from libreoffice container:\n----------------------------------\n" + container.logs().decode('utf-8'))
                container.stop()
                container.remove()

                stat_parent = os.stat(submissions_processed_path)
                for i in range(len(doc_files)):
                    dest = os.path.join(submissions_processed_path, os.path.relpath(doc_files[i], submissions_path) + '.pdf')
                    os.makedirs(os.path.dirname(dest), 0o2755, exist_ok=True)
                    out_dir = os.path.join(tmpdir, 'doc_files', str(i), 'out')
                    if not os.path.isdir(out_dir):
                        log.write(f"Failed to generate output for '{doc_files[i]}'\n")
                        continue
                    out_contents = os.listdir(out_dir)
                    if len(out_contents) != 1:
                        log.write(f"Failed to generate output for '{doc_files[i]}'\n")
                        continue
                    src = os.path.join(out_dir, out_contents[0])
                    os.rename(src, dest)
                    os.chown(dest, stat_parent.st_uid, stat_parent.st_gid)
                    os.chmod(dest, 0o644)
        except Exception as e:
            log.write(f"ERROR: {e}\n")
        finally:
            log.close()
            log_msg = f"[Last ran on: {today.isoformat()}]\n"
            logger.write_to_log(log_file, log_msg)
