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
import re
import docker
import requests
from urllib.parse import unquote, quote_plus
from tempfile import TemporaryDirectory

from . import regenerate_bulk_images
from . import bulk_qr_split
from . import bulk_upload_split
from . import generate_pdf_images
from . import INSTALL_DIR, DATA_DIR, CONFIG_PATH
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
        source = self.job_details['source']

        path = os.path.join(INSTALL_DIR, 'sbin', 'auto_rainbow_grades.py')
        debug_output = os.path.join(DATA_DIR, 'courses', semester, course, 'rainbow_grades', 'auto_debug_output.txt')

        try:
            with open(debug_output, "w") as file:
                subprocess.call(['python3', path, semester, course, source], stdout=file, stderr=file)
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


class SyncCourseRepo(CourseJob):
    _BRANCH_PATTERN = re.compile(r'^[A-Za-z0-9._/-]+$')
    _SUBDIR_PATTERN = re.compile(r'^[A-Za-z0-9._/-]*$')
    _GRADEABLE_ID_PATTERN = re.compile(r'^[A-Za-z0-9_-]+$')
    _SYLLABUS_BUCKETS = {
        'homework', 'assignment', 'problem-set',
        'quiz', 'test', 'exam',
        'exercise', 'lecture-exercise', 'reading', 'lab', 'recitation', 'worksheet',
        'project',
        'participation', 'note',
        'none (for practice only)',
    }

    @staticmethod
    def _write_sync_status(status_path, status, message, **kwargs):
        payload = {
            'status': status,
            'message': message,
            'last_updated': datetime.datetime.now(datetime.timezone.utc).isoformat(),
        }
        payload.update(kwargs)
        os.makedirs(os.path.dirname(status_path), exist_ok=True)
        with open(status_path, 'w', encoding='utf-8') as status_file:
            json.dump(payload, status_file, indent=4)

    @staticmethod
    def _safe_read_json(path):
        with open(path, 'r', encoding='utf-8') as infile:
            return json.load(infile)

    @staticmethod
    def _validate_branch(branch):
        if branch == '':
            return False
        if '..' in branch or branch.startswith('/'):
            return False
        return SyncCourseRepo._BRANCH_PATTERN.match(branch) is not None

    @staticmethod
    def _validate_subdirectory(subdirectory):
        if '..' in subdirectory:
            return False
        return SyncCourseRepo._SUBDIR_PATTERN.match(subdirectory) is not None

    @staticmethod
    def _write_json_atomic(path, obj):
        tmp_path = f"{path}.tmp"
        with open(tmp_path, 'w', encoding='utf-8') as outfile:
            json.dump(obj, outfile, indent=4)
            outfile.write('\n')
        os.replace(tmp_path, path)

    @staticmethod
    def _to_bool(value, default=False):
        if isinstance(value, bool):
            return value
        if isinstance(value, (int, float)):
            return value != 0
        if isinstance(value, str):
            lowered = value.strip().lower()
            if lowered in {'1', 'true', 'yes', 'y', 'on'}:
                return True
            if lowered in {'0', 'false', 'no', 'n', 'off'}:
                return False
        return default

    @staticmethod
    def _to_int(value, default=0):
        try:
            return int(value)
        except Exception:
            return default

    @staticmethod
    def _to_float(value, default=0.0):
        try:
            return float(value)
        except Exception:
            return default

    @staticmethod
    def _parse_datetime(value, fallback):
        if value is None or value == '':
            return fallback
        if isinstance(value, datetime.datetime):
            parsed = value
        elif isinstance(value, (int, float)):
            parsed = datetime.datetime.fromtimestamp(value, tz=datetime.timezone.utc)
        elif isinstance(value, str):
            string_value = value.strip()
            if string_value.endswith('Z'):
                string_value = string_value[:-1] + '+00:00'
            parsed = None
            try:
                parsed = datetime.datetime.fromisoformat(string_value)
            except ValueError:
                for fmt in ("%Y-%m-%d %H:%M:%S%z", "%Y-%m-%d %H:%M:%S", "%Y-%m-%dT%H:%M:%S%z", "%Y-%m-%dT%H:%M:%S"):
                    try:
                        parsed = datetime.datetime.strptime(string_value, fmt)
                        break
                    except ValueError:
                        continue
            if parsed is None:
                raise ValueError(f"Invalid datetime value '{value}'")
        else:
            raise ValueError(f"Unsupported datetime value type '{type(value)}'")

        if parsed.tzinfo is None:
            parsed = parsed.replace(tzinfo=datetime.timezone.utc)
        return parsed

    @staticmethod
    def _json_or_dict(value):
        if value is None:
            return {}
        if isinstance(value, dict):
            return value
        return {}

    @staticmethod
    def _write_form_config(course_path, gradeable):
        due_date = gradeable['submission_due_date'].strftime("%Y-%m-%d %H:%M:%S%z")
        form_json = {
            'gradeable_id': gradeable['id'],
            'config_path': gradeable['autograding_config_path'],
            'date_due': due_date if gradeable['has_due_date'] else None,
            'upload_type': "repository" if gradeable['vcs'] else "upload file",
            'subdirectory': gradeable['vcs_subdirectory'],
            'using_subdirectory': gradeable['using_subdirectory'],
            'vcs_partial_path': gradeable['vcs_partial_path'],
        }
        form_dir = os.path.join(course_path, 'config', 'form')
        os.makedirs(form_dir, exist_ok=True)
        form_path = os.path.join(form_dir, f"form_{gradeable['id']}.json")
        SyncCourseRepo._write_json_atomic(form_path, form_json)

    @staticmethod
    def _queue_gradeable_rebuild(semester, course, gradeable_id):
        queue_file = os.path.join(DATA_DIR, 'daemon_job_queue', f"{semester}__{course}__{gradeable_id}.json")
        payload = {
            "job": "BuildConfig",
            "semester": semester,
            "course": course,
            "gradeable": gradeable_id,
        }
        SyncCourseRepo._write_json_atomic(queue_file, payload)

    @staticmethod
    def _resolve_autograding_path(manifest, gradeable_dir, repo_content_root):
        repo_content_root = os.path.realpath(repo_content_root)
        explicit_path = manifest.get('autograding_config_path')
        if isinstance(explicit_path, str) and explicit_path.strip() != '':
            candidate = explicit_path.strip()
            if not os.path.isabs(candidate):
                candidate = os.path.normpath(os.path.join(gradeable_dir, candidate))
            candidate = os.path.realpath(candidate)
            if os.path.commonpath([repo_content_root, candidate]) != repo_content_root:
                raise RuntimeError("autograding_config_path escapes repository root")
            return candidate

        default_candidates = [
            os.path.join(gradeable_dir, 'autograder'),
            os.path.join(gradeable_dir, 'config'),
            gradeable_dir,
        ]
        for candidate in default_candidates:
            if os.path.isdir(candidate):
                candidate = os.path.realpath(candidate)
                if os.path.commonpath([repo_content_root, candidate]) != repo_content_root:
                    continue
                return candidate
        raise RuntimeError("No autograding config directory found for gradeable")

    def _normalize_gradeable_manifest(self, manifest, directory_name, gradeable_dir, repo_content_root):
        if not isinstance(manifest, dict):
            raise RuntimeError("gradeable.json must be a JSON object")

        now = datetime.datetime.now(datetime.timezone.utc)
        tonight = now.replace(hour=23, minute=59, second=59, microsecond=0)
        if (tonight - now).total_seconds() < 12 * 60 * 60:
            tonight = tonight + datetime.timedelta(days=1)

        settings = self._json_or_dict(manifest.get('settings'))
        dates = self._json_or_dict(manifest.get('dates'))

        def get_value(key, default=None):
            return settings.get(key, manifest.get(key, default))

        def get_date(key, fallback):
            return self._parse_datetime(dates.get(key, manifest.get(key, None)), fallback)

        gradeable_id = str(manifest.get('id', directory_name)).strip()
        if gradeable_id == '':
            raise RuntimeError("Gradeable id cannot be blank")
        if not self._GRADEABLE_ID_PATTERN.match(gradeable_id):
            raise RuntimeError("Gradeable id must contain only letters, numbers, underscore, or hyphen")

        gradeable_type = str(get_value('type', 'electronic_file')).strip().lower()
        if gradeable_type not in {'electronic_file', 'electronic', '0'}:
            raise RuntimeError("Only electronic_file gradeables are currently supported by course repository sync")

        syllabus_bucket = str(get_value('syllabus_bucket', 'homework'))
        if syllabus_bucket not in self._SYLLABUS_BUCKETS:
            syllabus_bucket = 'homework'

        ta_view_start_date = get_date('ta_view_start_date', tonight)
        grade_start_date = get_date('grade_start_date', tonight + datetime.timedelta(days=10))
        grade_due_date = get_date('grade_due_date', tonight + datetime.timedelta(days=14))
        grade_released_date = get_date('grade_released_date', tonight + datetime.timedelta(days=14))
        submission_open_date = get_date('submission_open_date', tonight)
        submission_due_date = get_date('submission_due_date', tonight + datetime.timedelta(days=7))
        team_lock_date = get_date('team_lock_date', tonight + datetime.timedelta(days=7))
        grade_inquiry_start_date = get_date('grade_inquiry_start_date', tonight + datetime.timedelta(days=15))
        grade_inquiry_due_date = get_date('grade_inquiry_due_date', tonight + datetime.timedelta(days=21))

        # Ensure database constraints are satisfied.
        if grade_start_date < ta_view_start_date:
            grade_start_date = ta_view_start_date
        if grade_due_date < grade_start_date:
            grade_due_date = grade_start_date
        if grade_released_date < grade_due_date:
            grade_released_date = grade_due_date
        if submission_due_date < submission_open_date:
            submission_due_date = submission_open_date
        if team_lock_date > submission_due_date:
            team_lock_date = submission_due_date
        if grade_inquiry_due_date < grade_inquiry_start_date:
            grade_inquiry_due_date = grade_inquiry_start_date

        vcs = self._to_bool(get_value('vcs', False), False)
        vcs_partial_path = str(get_value('vcs_partial_path', f"{gradeable_id}/{{$user_id}}")).strip() if vcs else ''
        vcs_host_type_default = 0 if vcs else -1
        vcs_host_type = self._to_int(get_value('vcs_host_type', vcs_host_type_default), vcs_host_type_default)
        using_subdirectory = self._to_bool(get_value('using_subdirectory', False), False)
        vcs_subdirectory = str(get_value('vcs_subdirectory', '')).strip() if using_subdirectory else ''

        grade_inquiry_allowed = self._to_bool(get_value('grade_inquiry_allowed', False), False)
        grade_inquiry_per_component_allowed = self._to_bool(get_value('grade_inquiry_per_component_allowed', False), False)
        if not grade_inquiry_allowed and grade_inquiry_per_component_allowed:
            grade_inquiry_per_component_allowed = False

        autograding_config_path = self._resolve_autograding_path(manifest, gradeable_dir, repo_content_root)
        if not os.path.isfile(os.path.join(autograding_config_path, 'config.json')):
            raise RuntimeError(f"Autograding config directory '{autograding_config_path}' is missing config.json")

        grader_assignment_method = self._to_int(get_value('grader_assignment_method', 1), 1)
        if grader_assignment_method not in {0, 1, 2}:
            grader_assignment_method = 1

        min_grading_group = self._to_int(get_value('min_grading_group', 1), 1)
        if min_grading_group < 1:
            min_grading_group = 1
        if min_grading_group > 4:
            min_grading_group = 4

        instructor_blind = self._to_int(get_value('instructor_blind', 1), 1)
        limited_access_blind = self._to_int(get_value('limited_access_blind', 1), 1)
        peer_blind = self._to_int(get_value('peer_blind', 3), 3)
        for blind_key, blind_val in [('instructor_blind', instructor_blind), ('limited_access_blind', limited_access_blind), ('peer_blind', peer_blind)]:
            if blind_val not in {1, 2, 3}:
                if blind_key == 'peer_blind':
                    peer_blind = 3
                else:
                    if blind_key == 'instructor_blind':
                        instructor_blind = 1
                    else:
                        limited_access_blind = 1

        return {
            'id': gradeable_id,
            'title': str(get_value('title', gradeable_id)),
            'instructions_url': str(get_value('instructions_url', '')),
            'ta_instructions': str(get_value('ta_instructions', '')),
            'syllabus_bucket': syllabus_bucket,
            'grader_assignment_method': grader_assignment_method,
            'min_grading_group': min_grading_group,
            'allow_custom_marks': self._to_bool(get_value('allow_custom_marks', True), True),
            'ta_view_start_date': ta_view_start_date,
            'grade_start_date': grade_start_date,
            'grade_due_date': grade_due_date,
            'grade_released_date': grade_released_date,
            'submission_open_date': submission_open_date,
            'submission_due_date': submission_due_date,
            'team_lock_date': team_lock_date,
            'grade_inquiry_start_date': grade_inquiry_start_date,
            'grade_inquiry_due_date': grade_inquiry_due_date,
            'vcs': vcs,
            'using_subdirectory': using_subdirectory,
            'vcs_subdirectory': vcs_subdirectory,
            'vcs_partial_path': vcs_partial_path,
            'vcs_host_type': vcs_host_type,
            'team_assignment': self._to_bool(get_value('team_assignment', False), False),
            'team_size_max': max(1, self._to_int(get_value('team_size_max', 1), 1)),
            'ta_grading': self._to_bool(get_value('ta_grading', True), True),
            'student_view': self._to_bool(get_value('student_view', True), True),
            'student_view_after_grades': self._to_bool(get_value('student_view_after_grades', False), False),
            'student_download': self._to_bool(get_value('student_download', True), True),
            'student_submit': self._to_bool(get_value('student_submit', True), True),
            'has_due_date': self._to_bool(get_value('has_due_date', True), True),
            'has_release_date': self._to_bool(get_value('has_release_date', True), True),
            'late_days': max(0, self._to_int(get_value('late_days', 0), 0)),
            'late_submission_allowed': self._to_bool(get_value('late_submission_allowed', True), True),
            'precision': max(0.0, self._to_float(get_value('precision', 0.5), 0.5)),
            'instructor_blind': instructor_blind,
            'limited_access_blind': limited_access_blind,
            'peer_blind': peer_blind,
            'grade_inquiry_allowed': grade_inquiry_allowed,
            'grade_inquiry_per_component_allowed': grade_inquiry_per_component_allowed,
            'discussion_thread_ids': json.dumps([]),
            'discussion_based': False,
            'hidden_files': '',
            'autograding_config_path': autograding_config_path,
        }

    def _sync_gradeables(self, semester, course, course_path, gradeables_root, repo_content_root, warnings):
        try:
            from sqlalchemy import create_engine, text
        except Exception as exc:
            raise RuntimeError(f"Missing SQLAlchemy dependency required for gradeable sync: {exc}") from exc

        if not os.path.isdir(gradeables_root):
            return {'created': [], 'updated': [], 'synced': []}

        database_config = self._safe_read_json(os.path.join(CONFIG_PATH, 'database.json'))
        db_host = database_config['database_host']
        db_port = database_config['database_port']
        db_user = database_config['database_course_user']
        db_pass = database_config['database_course_password']
        db_name = f"submitty_{semester}_{course}"

        if os.path.isdir(db_host):
            conn_string = f"postgresql://{quote_plus(db_user)}:{quote_plus(db_pass)}@/{db_name}?host={quote_plus(db_host)}"
        else:
            conn_string = f"postgresql://{quote_plus(db_user)}:{quote_plus(db_pass)}@{db_host}:{db_port}/{db_name}"

        engine = create_engine(conn_string)
        created = []
        updated = []
        synced = []
        touched = set()

        insert_gradeable_sql = text("""
            INSERT INTO gradeable(
                g_id, g_title, g_instructions_url, g_overall_ta_instructions, g_gradeable_type,
                g_grader_assignment_method, g_ta_view_start_date, g_grade_start_date, g_grade_due_date,
                g_grade_released_date, g_min_grading_group, g_syllabus_bucket, g_allow_custom_marks
            ) VALUES (
                :id, :title, :instructions_url, :ta_instructions, 0,
                :grader_assignment_method, :ta_view_start_date, :grade_start_date, :grade_due_date,
                :grade_released_date, :min_grading_group, :syllabus_bucket, :allow_custom_marks
            )
        """)

        update_gradeable_sql = text("""
            UPDATE gradeable SET
                g_title=:title,
                g_instructions_url=:instructions_url,
                g_overall_ta_instructions=:ta_instructions,
                g_gradeable_type=0,
                g_grader_assignment_method=:grader_assignment_method,
                g_ta_view_start_date=:ta_view_start_date,
                g_grade_start_date=:grade_start_date,
                g_grade_due_date=:grade_due_date,
                g_grade_released_date=:grade_released_date,
                g_min_grading_group=:min_grading_group,
                g_syllabus_bucket=:syllabus_bucket,
                g_allow_custom_marks=:allow_custom_marks
            WHERE g_id=:id
        """)

        upsert_electronic_sql = text("""
            INSERT INTO electronic_gradeable(
                g_id, eg_config_path, eg_is_repository, eg_vcs_partial_path, eg_vcs_host_type,
                eg_team_assignment, eg_max_team_size, eg_team_lock_date, eg_use_ta_grading, eg_student_download,
                eg_student_view, eg_student_view_after_grades, eg_student_submit, eg_submission_open_date,
                eg_submission_due_date, eg_has_due_date, eg_late_days, eg_allow_late_submission, eg_precision,
                eg_grade_inquiry_allowed, eg_grade_inquiry_per_component_allowed, eg_grade_inquiry_due_date,
                eg_thread_ids, eg_has_discussion, eg_limited_access_blind, eg_peer_blind, eg_grade_inquiry_start_date,
                eg_hidden_files, eg_depends_on, eg_depends_on_points, eg_has_release_date, eg_vcs_subdirectory,
                eg_using_subdirectory, eg_instructor_blind, eg_release_notifications_sent
            ) VALUES (
                :id, :autograding_config_path, :vcs, :vcs_partial_path, :vcs_host_type,
                :team_assignment, :team_size_max, :team_lock_date, :ta_grading, :student_download,
                :student_view, :student_view_after_grades, :student_submit, :submission_open_date,
                :submission_due_date, :has_due_date, :late_days, :late_submission_allowed, :precision,
                :grade_inquiry_allowed, :grade_inquiry_per_component_allowed, :grade_inquiry_due_date,
                CAST(:discussion_thread_ids AS json), :discussion_based, :limited_access_blind, :peer_blind, :grade_inquiry_start_date,
                :hidden_files, NULL, NULL, :has_release_date, :vcs_subdirectory,
                :using_subdirectory, :instructor_blind, FALSE
            )
            ON CONFLICT (g_id) DO UPDATE SET
                eg_config_path=EXCLUDED.eg_config_path,
                eg_is_repository=EXCLUDED.eg_is_repository,
                eg_vcs_partial_path=EXCLUDED.eg_vcs_partial_path,
                eg_vcs_host_type=EXCLUDED.eg_vcs_host_type,
                eg_team_assignment=EXCLUDED.eg_team_assignment,
                eg_max_team_size=EXCLUDED.eg_max_team_size,
                eg_team_lock_date=EXCLUDED.eg_team_lock_date,
                eg_use_ta_grading=EXCLUDED.eg_use_ta_grading,
                eg_student_download=EXCLUDED.eg_student_download,
                eg_student_view=EXCLUDED.eg_student_view,
                eg_student_view_after_grades=EXCLUDED.eg_student_view_after_grades,
                eg_student_submit=EXCLUDED.eg_student_submit,
                eg_submission_open_date=EXCLUDED.eg_submission_open_date,
                eg_submission_due_date=EXCLUDED.eg_submission_due_date,
                eg_has_due_date=EXCLUDED.eg_has_due_date,
                eg_late_days=EXCLUDED.eg_late_days,
                eg_allow_late_submission=EXCLUDED.eg_allow_late_submission,
                eg_precision=EXCLUDED.eg_precision,
                eg_grade_inquiry_allowed=EXCLUDED.eg_grade_inquiry_allowed,
                eg_grade_inquiry_per_component_allowed=EXCLUDED.eg_grade_inquiry_per_component_allowed,
                eg_grade_inquiry_due_date=EXCLUDED.eg_grade_inquiry_due_date,
                eg_thread_ids=EXCLUDED.eg_thread_ids,
                eg_has_discussion=EXCLUDED.eg_has_discussion,
                eg_limited_access_blind=EXCLUDED.eg_limited_access_blind,
                eg_peer_blind=EXCLUDED.eg_peer_blind,
                eg_grade_inquiry_start_date=EXCLUDED.eg_grade_inquiry_start_date,
                eg_hidden_files=EXCLUDED.eg_hidden_files,
                eg_has_release_date=EXCLUDED.eg_has_release_date,
                eg_vcs_subdirectory=EXCLUDED.eg_vcs_subdirectory,
                eg_using_subdirectory=EXCLUDED.eg_using_subdirectory,
                eg_instructor_blind=EXCLUDED.eg_instructor_blind
        """)

        upsert_peer_panel_sql = text("""
            INSERT INTO peer_grading_panel(g_id, autograding, rubric, files, solution_notes, discussion)
            VALUES (:id, TRUE, TRUE, TRUE, TRUE, TRUE)
            ON CONFLICT (g_id) DO NOTHING
        """)

        ensure_component_sql = text("""
            INSERT INTO gradeable_component(
                g_id, gc_title, gc_ta_comment, gc_student_comment, gc_lower_clamp, gc_default,
                gc_max_value, gc_upper_clamp, gc_is_text, gc_is_peer, gc_order, gc_page,
                gc_is_itempool_linked, gc_itempool
            )
            SELECT :id, '', '', '', 0, 0, 0, 0, FALSE, FALSE, 0, 0, FALSE, ''
            WHERE NOT EXISTS (SELECT 1 FROM gradeable_component WHERE g_id = :id)
        """)

        insert_anon_sql = text("""
            INSERT INTO gradeable_anon(user_id, g_id, anon_id)
            SELECT u.user_id, :id, substr(md5(u.user_id || ':' || :id), 1, 32)
            FROM users AS u
            WHERE NOT EXISTS (
                SELECT 1
                FROM gradeable_anon AS ga
                WHERE ga.user_id = u.user_id AND ga.g_id = :id
            )
            ON CONFLICT DO NOTHING
        """)

        try:
            with engine.begin() as conn:
                existing_gradeables = {row[0] for row in conn.execute(text("SELECT g_id FROM gradeable"))}
                for entry in sorted(os.scandir(gradeables_root), key=lambda item: item.name):
                    if not entry.is_dir():
                        continue

                    manifest_path = os.path.join(entry.path, 'gradeable.json')
                    if not os.path.isfile(manifest_path):
                        warnings.append(f"gradeables/{entry.name} is missing gradeable.json")
                        continue

                    try:
                        manifest = self._safe_read_json(manifest_path)
                        gradeable = self._normalize_gradeable_manifest(
                            manifest,
                            entry.name,
                            entry.path,
                            repo_content_root,
                        )
                    except Exception as exc:
                        warnings.append(f"gradeables/{entry.name} could not be processed: {exc}")
                        continue

                    if gradeable['id'] in touched:
                        warnings.append(f"Duplicate gradeable id '{gradeable['id']}' encountered; skipping duplicate entry.")
                        continue
                    touched.add(gradeable['id'])

                    exists = gradeable['id'] in existing_gradeables
                    if exists:
                        conn.execute(update_gradeable_sql, gradeable)
                    else:
                        conn.execute(insert_gradeable_sql, gradeable)
                        existing_gradeables.add(gradeable['id'])

                    conn.execute(upsert_electronic_sql, gradeable)
                    conn.execute(upsert_peer_panel_sql, gradeable)
                    conn.execute(ensure_component_sql, gradeable)
                    conn.execute(insert_anon_sql, gradeable)

                    self._write_form_config(course_path, gradeable)
                    self._queue_gradeable_rebuild(semester, course, gradeable['id'])

                    if exists:
                        updated.append(gradeable['id'])
                    else:
                        created.append(gradeable['id'])
                    synced.append(gradeable['id'])
        finally:
            engine.dispose()

        return {
            'created': sorted(created),
            'updated': sorted(updated),
            'synced': sorted(synced),
        }

    def run_job(self):
        semester = self.job_details['semester']
        course = self.job_details['course']

        course_path = os.path.join(DATA_DIR, 'courses', semester, course)
        config_path = os.path.join(course_path, 'config', 'config.json')
        repo_root = os.path.join(course_path, 'course_repo')
        checkout_path = os.path.join(repo_root, 'checkout')
        status_path = os.path.join(course_path, 'config', 'course_repo_sync_status.json')
        log_path = os.path.join(repo_root, 'sync.log')
        os.makedirs(repo_root, exist_ok=True)

        log_lines = []

        def run_and_log(command, display_command=None, allow_failure=False):
            shown_command = display_command if display_command is not None else command
            log_lines.append(f"$ {' '.join(shown_command)}")
            result = subprocess.run(command, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True)
            if result.stdout:
                log_lines.append(result.stdout.rstrip())
            if result.returncode != 0 and not allow_failure:
                raise RuntimeError(f"Command failed: {' '.join(command)}")
            return result

        try:
            course_json = self._safe_read_json(config_path)
            course_details = course_json.get('course_details', {})
            if not isinstance(course_details, dict):
                raise RuntimeError('config/config.json course_details must be a JSON object')
            repo_url = str(course_details.get('course_repo_url', '')).strip()
            branch = str(course_details.get('course_repo_branch', 'main')).strip() or 'main'
            subdirectory = str(course_details.get('course_repo_subdirectory', '')).strip().strip('/')

            if repo_url == '':
                raise RuntimeError('Course repository URL is not set')
            if any(ch.isspace() for ch in repo_url):
                raise RuntimeError('Course repository URL cannot contain whitespace')
            if not self._validate_branch(branch):
                raise RuntimeError('Course repository branch is invalid')
            if not self._validate_subdirectory(subdirectory):
                raise RuntimeError('Course repository subdirectory is invalid')

            git_path = shutil.which('git') or '/usr/bin/git'
            if os.path.isdir(os.path.join(checkout_path, '.git')):
                set_url_result = run_and_log(
                    [git_path, '-C', checkout_path, 'remote', 'set-url', 'origin', repo_url],
                    [git_path, '-C', checkout_path, 'remote', 'set-url', 'origin', '<repo_url>'],
                    allow_failure=True,
                )
                if set_url_result.returncode != 0:
                    run_and_log(
                        [git_path, '-C', checkout_path, 'remote', 'add', 'origin', repo_url],
                        [git_path, '-C', checkout_path, 'remote', 'add', 'origin', '<repo_url>'],
                    )
                run_and_log([git_path, '-C', checkout_path, 'fetch', '--prune', 'origin'])
                run_and_log([git_path, '-C', checkout_path, 'checkout', '-B', branch, f'origin/{branch}'])
                run_and_log([git_path, '-C', checkout_path, 'reset', '--hard', f'origin/{branch}'])
                run_and_log([git_path, '-C', checkout_path, 'clean', '-fd'])
            else:
                if os.path.exists(checkout_path):
                    shutil.rmtree(checkout_path, ignore_errors=True)
                run_and_log(
                    [git_path, 'clone', '--branch', branch, '--single-branch', repo_url, checkout_path],
                    [git_path, 'clone', '--branch', branch, '--single-branch', '<repo_url>', checkout_path],
                )

            repo_content_root = os.path.join(checkout_path, subdirectory) if subdirectory else checkout_path
            if not os.path.isdir(repo_content_root):
                raise RuntimeError('Configured repository subdirectory does not exist in the pulled repository')

            warnings = []
            updated_course_keys = []

            course_manifest = os.path.join(repo_content_root, 'course.json')
            if os.path.isfile(course_manifest):
                manifest_json = self._safe_read_json(course_manifest)
                if not isinstance(manifest_json, dict):
                    raise RuntimeError('course.json must be a JSON object')
                manifest_course_details = self._json_or_dict(manifest_json.get('course_details'))
                if len(manifest_course_details) == 0:
                    # Allow top-level format as a convenience for the course repository manifest.
                    manifest_course_details = manifest_json
                if not isinstance(manifest_course_details, dict):
                    raise RuntimeError('course.json must be a JSON object')
                if len(manifest_course_details) == 0:
                    warnings.append('course.json is missing course_details; skipped applying course settings.')
                else:
                    allowed_keys = set(course_details.keys())
                    protected_keys = {
                        'course_repo_url',
                        'course_repo_branch',
                        'course_repo_subdirectory',
                    }
                    for key, value in manifest_course_details.items():
                        if key in allowed_keys and key not in protected_keys:
                            course_json['course_details'][key] = value
                            updated_course_keys.append(key)
                    if len(updated_course_keys) > 0:
                        self._write_json_atomic(config_path, course_json)
            else:
                warnings.append('Repository does not contain course.json at the configured root.')

            gradeables_root = os.path.join(repo_content_root, 'gradeables')
            gradeable_sync_info = self._sync_gradeables(
                semester,
                course,
                course_path,
                gradeables_root,
                repo_content_root,
                warnings,
            )

            commit_result = run_and_log([git_path, '-C', checkout_path, 'rev-parse', '--short', 'HEAD'])
            commit = commit_result.stdout.strip()

            if len(warnings) > 0:
                self._write_sync_status(
                    status_path,
                    'warning',
                    'Repository pulled with warnings.',
                    commit=commit,
                    warnings=warnings,
                    updated_course_keys=sorted(set(updated_course_keys)),
                    synced_gradeables=gradeable_sync_info['synced'],
                    created_gradeables=gradeable_sync_info['created'],
                    updated_gradeables=gradeable_sync_info['updated'],
                )
            else:
                self._write_sync_status(
                    status_path,
                    'success',
                    'Repository pulled successfully.',
                    commit=commit,
                    updated_course_keys=sorted(set(updated_course_keys)),
                    synced_gradeables=gradeable_sync_info['synced'],
                    created_gradeables=gradeable_sync_info['created'],
                    updated_gradeables=gradeable_sync_info['updated'],
                )
        except Exception as exc:
            self._write_sync_status(status_path, 'error', str(exc))
            log_lines.append(f"ERROR: {exc}")
        finally:
            with open(log_path, 'a', encoding='utf-8') as logfile:
                logfile.write('\n'.join(log_lines))
                if len(log_lines) > 0:
                    logfile.write('\n')


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
        pdf_file_path = self.job_details["pdf_file_path"]
        output_dir = self.job_details["output_dir"]
        # optionally get redactions
        redactions = self.job_details.get("redactions", [])
        generate_pdf_images.main(
            pdf_file_path,
            output_dir,
            [generate_pdf_images.Redaction(**r) for r in redactions],
        )

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

        repo_url = str(self.job_details.get('course_repo_url', '')).strip()
        repo_branch = str(self.job_details.get('course_repo_branch', 'main')).strip() or 'main'
        repo_subdirectory = str(self.job_details.get('course_repo_subdirectory', '')).strip().strip('/')

        if any(ch.isspace() for ch in repo_url):
            return False
        if not SyncCourseRepo._validate_branch(repo_branch):
            return False
        if not SyncCourseRepo._validate_subdirectory(repo_subdirectory):
            return False

        self.job_details['course_repo_url'] = repo_url
        self.job_details['course_repo_branch'] = repo_branch
        self.job_details['course_repo_subdirectory'] = repo_subdirectory
        return True

    def run_job(self):
        semester = self.job_details['semester']
        course = self.job_details['course']
        head_instructor = self.job_details['head_instructor']
        base_group = self.job_details['group_name']
        repo_url = self.job_details.get('course_repo_url', '')
        repo_branch = self.job_details.get('course_repo_branch', 'main')
        repo_subdirectory = self.job_details.get('course_repo_subdirectory', '')

        log_file_path = Path(DATA_DIR, 'logs', 'course_creation', '{}_{}_{}_{}.txt'.format(
            semester, course, head_instructor, base_group
        ))

        with log_file_path.open("w") as output_file:
            subprocess.run(["sudo", "/usr/local/submitty/sbin/create_course.sh", semester, course, head_instructor, base_group], stdout=output_file, stderr=output_file)
            subprocess.run(["sudo", "/usr/local/submitty/sbin/adduser_course.py", head_instructor, semester, course], stdout=output_file, stderr=output_file)
            if VERIFIED_ADMIN_USER != "":
                subprocess.run(["sudo", "/usr/local/submitty/sbin/adduser_course.py", VERIFIED_ADMIN_USER, semester, course], stdout=output_file, stderr=output_file)

            if repo_url != '':
                course_config_path = os.path.join(DATA_DIR, 'courses', semester, course, 'config', 'config.json')
                try:
                    course_json = SyncCourseRepo._safe_read_json(course_config_path)
                    course_details = course_json.get('course_details', {})
                    if isinstance(course_details, dict):
                        course_details['course_repo_url'] = repo_url
                        course_details['course_repo_branch'] = repo_branch
                        course_details['course_repo_subdirectory'] = repo_subdirectory
                        SyncCourseRepo._write_json_atomic(course_config_path, course_json)

                        sync_job_path = os.path.join(DATA_DIR, 'daemon_job_queue', f"sync_course_repo__{semester}__{course}.json")
                        if not os.path.isfile(sync_job_path):
                            sync_job = {
                                'job': 'SyncCourseRepo',
                                'semester': semester,
                                'course': course,
                                'triggered_by': self.job_details.get('triggered_by', ''),
                            }
                            SyncCourseRepo._write_json_atomic(sync_job_path, sync_job)
                    else:
                        print("Skipping initial course repository sync: config.json course_details is invalid.", file=output_file)
                except Exception as exc:
                    print(f"Failed to configure initial course repository sync: {exc}", file=output_file)

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


# Used to regenerate images for all submissions in a bulk upload
class RegenerateBulkImages(AbstractJob):
    def run_job(self):
        folder = self.job_details["pdf_file_path"]
        redactions = [
            generate_pdf_images.Redaction(**r)
            for r in self.job_details.get("redactions", [])
        ]
        regenerate_bulk_images.main(folder, redactions)

    def cleanup_job(self):
        pass


class DocxToPDF(AbstractJob):
    def run_job(self):
        log_dir = os.path.join(DATA_DIR, "logs", "docx_to_pdf")
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
            submissions_processed_path = os.path.join(submissions_processed_dir, gradeable, user, str(version), 'convert_docx_to_pdf')

            if not os.path.isdir(submissions_processed_path):
                os.makedirs(submissions_processed_path)

            docx_MIME_TYPES = ['application/vnd.openxmlformats-officedocument.wordprocessingml.document']

            docx_files = []

            for root, _, files in os.walk(submissions_path):
                for file in files:
                    file_path = os.path.join(root, file)
                    mimetype, _ = mimetypes.guess_type(file_path)
                    if mimetype in docx_MIME_TYPES:
                        docx_files.append(file_path)

            with TemporaryDirectory() as tmpdir:
                os.mkdir(os.path.join(tmpdir, 'docx_files'))

                for i in range(len(docx_files)):
                    numfolder = os.path.join(tmpdir, 'docx_files', str(i))
                    os.mkdir(numfolder)
                    shutil.copyfile(docx_files[i], os.path.join(numfolder, os.path.basename(docx_files[i])))

                client = docker.from_env(timeout=60)
                container = client.containers.run(
                    image='submitty/libreoffice-writer:latest',
                    command=['/bin/bash', '-c', f'for x in /app/docx_files/*/; \
                            do libreoffice --headless --convert-to pdf --outdir "$x"out "$x"*; done; \
                            chown -R {os.getuid()}:{os.getgid()} /app/docx_files'],
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
                for i in range(len(docx_files)):
                    dest = os.path.join(submissions_processed_path, os.path.relpath(docx_files[i], submissions_path) + '.pdf')
                    os.makedirs(os.path.dirname(dest), 0o2755, exist_ok=True)
                    out_dir = os.path.join(tmpdir, 'docx_files', str(i), 'out')
                    if not os.path.isdir(out_dir):
                        log.write(f"Failed to generate output for '{docx_files[i]}'\n")
                        continue
                    out_contents = os.listdir(out_dir)
                    if len(out_contents) != 1:
                        log.write(f"Failed to generate output for '{docx_files[i]}'\n")
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
