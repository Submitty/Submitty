#!/usr/bin/env python
"""
Setup script that reads in the users.yml and courses.yml files in the ../data directory and then
creates the users and courses for the system. This is primarily used by Vagrant and Travis to
figure the environments easily, but it could be run pretty much anywhere, unless the courses
already exist as else the system will probably fail.

Usage: ./setup_sample_courses.py
       ./setup_sample_courses.py [course [course]]
       ./setup_sample_courses.py --help

The first will create all couress in courses.yml while the second will only create the courses
specified (which is useful for something like Travis where we don't need the "demo classes", and
just the ones used for testing.
"""
from __future__ import print_function
import argparse
from collections import OrderedDict
from datetime import datetime, timedelta
import grp
import json
import os
import pwd
import re
import subprocess

from sqlalchemy import create_engine, Table, MetaData
import yaml

CURRENT_PATH = os.path.dirname(os.path.realpath(__file__))
SETUP_DATA_PATH = os.path.join(CURRENT_PATH, "..", "data")

SUBMITTY_REPOSITORY = "/usr/local/submitty/GIT_CHECKOUT_Submitty"
SUBMITTY_INSTALL_DIR = "/usr/local/submitty"
SUBMITTY_DATA_DIR = "/var/local/submitty"
SAMPLE_DIR = os.path.join(SUBMITTY_INSTALL_DIR, "sample_files/sample_assignment_config")

DB_HOST = "localhost"
DB_USER = "hsdbu"
DB_PASS = "hsdbu"


def main():
    """
    Main program execution. This gets us our commandline arugments, reads in the data files,
    and then sets us up to run the create methods for the users and courses.
    """
    args = parse_args()
    if not os.path.isdir(SUBMITTY_DATA_DIR):
        raise SystemError("The following directory does not exist: " + SUBMITTY_DATA_DIR)
    for directory in ["courses", "instructors"]:
        if not os.path.isdir(os.path.join(SUBMITTY_DATA_DIR, directory)):
            raise SystemError("The following directory does not exist: " + os.path.join(
                SUBMITTY_DATA_DIR, directory))
    use_courses = args.course

    courses = {}  # dict[str, Course]
    users = {}  # dict[str, User]
    courses_json = load_data_yaml('courses.yml')
    for course_json in courses_json:
        if len(use_courses) == 0 or course_json['code'] in use_courses:
            course = Course(course_json)
            courses[course.code] = course

    create_group("course_builders")
    users_json = load_data_yaml('users.yml')
    for user_json in users_json:
        # TODO: Add check for builtin system users (hwphp, etc.) and untrusted as they should
        # NOT be defined in this fashion as they're necessary for a lot of pre course creation steps
        user = User(user_json)
        user.create()
        users[user.id] = user
        if user.courses is not None:
            for course in user.courses:
                if course in courses:
                    courses[course].users.append(user)
                    if user.registration_section is not None:
                        courses[course].registration_sections.add(user.registration_section)
                    if user.rotating_section is not None:
                        courses[course].rotating_sections.add(user.rotating_section)
                    if user.grading_registration_section is not None:
                        courses[course].registration_sections.add(user.grading_registration_section)
        else:
            for key in courses.keys():
                courses[key].users.append(user)
                if user.registration_section is not None:
                    courses[key].registration_sections.add(user.registration_section)
                if user.rotating_section is not None:
                    courses[key].rotating_sections.add(user.rotating_section)
                if user.grading_registration_section is not None:
                    courses[key].registration_sections.add(user.grading_registration_section)

    for course in courses.keys():
        courses[course].instructor = users[courses[course].instructor]
        courses[course].check_rotating(users)
        courses[course].create()


def load_data_json(file_name):
    """
    Loads json file from the .setup/data directory returning the parsed structure
    :param file_name: name of file to load
    :return: parsed JSON structure from loaded file
    """
    file_path = os.path.join(SETUP_DATA_PATH, file_name)
    if not os.path.isfile(file_path):
        raise IOError("Missing the json file .setup/data/{}".format(file_name))
    with open(file_path) as open_file:
        json_file = json.load(open_file)
    return json_file


def load_data_yaml(file_name):
    """
    Loads yaml file from the .setup/data directory returning the parsed structure
    :param file_name: name of file to load
    :return: parsed YAML structure from loaded file
    """
    file_path = os.path.join(SETUP_DATA_PATH, file_name)
    if not os.path.isfile(file_path):
        raise IOError("Missing the yaml file .setup/data{}".format(file_name))
    with open(file_path) as open_file:
        yaml_file = yaml.safe_load(open_file)
    return yaml_file


def user_exists(user):
    """
    Checks to see if the user exists on the linux file system. We can use this to delete a user
    so that we can recreate them which avoids users having left over data from a previous run of
    setting up the sample courses.
    :param user: string to check if user exists
    :return: boolean on if user exists or not
    """
    try:
        pwd.getpwnam(user)
        return True
    except KeyError:
        return False


def group_exists(group):
    """
    Checks to see if the group exists on the linux file system so that we don't try to create
    groups that already exist.

    :param group: string to check if group exists
    :return: boolean on if group exists or not
    """
    try:
        grp.getgrnam(group)
        return True
    except KeyError:
        return False


def create_group(group):
    """
    Creates the group on the system, adding some base users to the group as well that are necessary
    for the system to function and are not defined within the users.yml file.
    :param group: name of the group to create
    """
    if not group_exists(group):
        os.system("addgroup {}".format(group))

    if group == "sudo":
        return
    # These users must be in the groups that get created as else creating the course
    # might fail (and we wouldn't be able to read some necessary files on PHP interface
    os.system("adduser hwphp {}".format(group))
    os.system("adduser hwcgi {}".format(group))
    os.system("adduser hwcron {}".format(group))


def add_to_group(group, user_id):
    """
    Adds the user to the specified group, creating the group if it does not exist.
    :param group:
    :param user_id:
    """
    create_group(group)
    os.system("adduser {} {}".format(user_id, group))


def get_php_db_password(password):
    """
    Generates a password to be used within the site for database authentication. The password_hash
    function (http://php.net/manual/en/function.password-hash.php) generates us a nice secure
    password and takes care of things like salting and hashing.
    :param password:
    :return: password hash to be inserted into the DB for a user
    """
    proc = subprocess.Popen(
        ["php", "-r", "print(password_hash('{}', PASSWORD_DEFAULT));".format(password)],
        stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    (out, err) = proc.communicate()
    return out


def get_current_semester():
    """
    Given today's date, generates a three character code that represents the semester to use for
    courses such that the first half of the year is considered "Spring" and the last half is
    considered "Fall". The "Spring" semester  gets an S as the first letter while "Fall" gets an
    F. The next two characters are the last two digits in the current year.
    :return:
    """
    today = datetime.today()
    semester = "f" + str(today.year)[-2:]
    if today.month < 7:
        semester = "s" + str(today.year)[-2:]
    return semester


def parse_datetime(date_string):
    """
    Given a string that should either represent an absolute date or an arbitrary date, parse this
    into a datetime object that is then used. Absolute dates should be in the format of
    YYYY-MM-DD HH:MM:SS while arbitrary dates are of the format "+/-# day(s) [at HH:MM:SS]" where
    the last part is optional. If the time is omitted, then it uses midnight of whatever day was
    specified.

    Examples of allowed strings:
    2016-10-14
    2016-10-13 22:11:32
    -1 day
    +2 days at 00:01:01

    :param date_string:
    :return:
    """
    if isinstance(date_string, datetime):
        return date_string
    try:
        return datetime.strptime(date_string, "%Y-%m-%d %H:%M:%S")
    except ValueError:
        pass

    try:
        return datetime.strptime(date_string, "%Y-%m-%d").replace(hour=23, minute=59, second=59)
    except ValueError:
        pass

    m = re.search('([+|\-][0-9]+) (days|day) at [0-2][0-9]:[0-5][0-9]:[0-5][0-9]', date_string)
    if m is not None:
        hour = int(m.group(2))
        minu = int(m.group(3))
        sec = int(m.group(4))
        days = int(m.group(1))
        return datetime.now().replace(hour=hour, minute=minu, second=sec) + timedelta(days=days)

    m = re.search('([+|\-][0-9]+) (days|day)', date_string)
    if m is not None:
        days = int(m.group(1))
        return datetime.now().replace(hour=23, minute=59, second=59) + timedelta(days=days)

    raise ValueError("Invalid string for date parsing: " + str(date_string))


def datetime_str(datetime_obj):
    if not isinstance(datetime_obj, datetime):
        return datetime_obj
    return datetime_obj.strftime("%Y-%m-%d %H:%M:%S")


def parse_args():
    """
    Parses out the arguments that might be passed to this script as it's run as a commandline
    application.
    :return: parsed args from the argparse module
    """
    parser = argparse.ArgumentParser(
        description="Sets up the sample courses as well as creating the necessary users for the "
                    "course as needed. It reads in the courses.json and users.json files from the "
                    ".setup/data directory to determine what courses/users are allowed and then "
                    "either adds all or just a few depending on what gets passed to this script")

    parser.add_argument("course", nargs="*",
                        help="course code to build. If no courses are passed in, then it'll use "
                             "all courses in courses.json")
    return parser.parse_args()


class User(object):
    """
    A basic object to contain the objects loaded from the users.json file. We use this to link
    against the courses.

    Attributes:
        id
        password
        firstname
        lastname
        email
        group
        preferred_firstname
        registration_section
        rotating_section
        unix_groups
        courses
    """
    def __init__(self, user):
        self.id = user['user_id']
        self.password = get_php_db_password(self.id)
        self.firstname = user['user_firstname']
        self.lastname = user['user_lastname']
        self.email = self.id + "@example.com"
        self.group = 4
        self.preferred_firstname = None
        self.registration_section = None
        self.rotating_section = None
        self.grading_registration_section = None
        self.unix_groups = None
        self.courses = None
        self.manual = False
        self.sudo = False

        if 'user_preferred_firstname' in user:
            self.preferred_firstname = user['user_preferred_firstname']
        if 'user_email' in user:
            self.email = user['user_email']
        if 'user_group' in user:
            self.group = user['user_group']
        assert 0 <= self.group <= 4
        if 'registration_section' in user:
            self.registration_section = int(user['registration_section'])
        if 'rotating_section' in user:
            self.rotating_section = int(user['rotating_section'])
        if 'grading_registration_section' in user:
            self.grading_registration_section = int(user['grading_registration_section'])
        if 'unix_groups' in user:
            self.unix_groups = user['unix_groups']
        if 'manual_registration' in user:
            self.manual = user['manual_registration'] is True
        if 'courses' in user:
            self.courses = {}
            if isinstance(user['courses'], list):
                for course in user['courses']:
                    self.courses[course] = {"user_group": self.group}
            elif isinstance(user['courses'], dict):
                self.courses = user['courses']
                check_group = 5
                for course in self.courses:
                    if 'user_group' not in self.courses[course]:
                        self.courses[course]['user_group'] = self.group
                    check_group = min(self.courses[course]['user_group'], check_group)
            else:
                raise ValueError("Invalid type for courses key, it should either be list or dict")
        if 'sudo' in user:
            self.sudo = user['sudo'] is True

    def create(self, force_ssh=False):
        if self.group > 2 and not force_ssh:
            self._create_non_ssh()
        else:
            self._create_ssh()
        if self.group <= 1:
            add_to_group("course_builders", self.id)
            with open(os.path.join(SUBMITTY_DATA_DIR, "instructors", "valid"), "a") as open_file:
                open_file.write(self.id + "\n")
        if self.sudo:
            add_to_group("sudo", self.id)

    def _create_ssh(self):
        if not user_exists(self.id):
            print("Creating user {}...".format(self.id))
            os.system("adduser {} --gecos 'First Last,RoomNumber,WorkPhone,HomePhone' "
                      "--disabled-password".format(self.id))
            self.set_password()

    def _create_non_ssh(self):
        if not user_exists(self.id):
            print("Creating user {}...".format(self.id))
            os.system("/usr/sbin/adduser {} --quiet --home /tmp --gecos \'AUTH ONLY account\' "
                      "--no-create-home --disabled-password --shell "
                      "/usr/sbin/nologin".format(self.id))
            self.set_password()

    def set_password(self):
        print("Setting password for user {}...".format(self.id));
        os.system("echo {}:{} | chpasswd".format(self.id, self.id))

    def __getitem__(self, item):
        return self.__dict__[item]

    def get_detail(self, course, detail):
        if self.courses is not None and course in self.courses:
            user_detail = "user_" + detail
            if user_detail in self.courses[course]:
                return self.courses[course][user_detail]
            elif detail in self.courses[course]:
                return self.courses[course][detail]
        return self[detail]


class Course(object):
    """
    Object to represent the courses loaded from the courses.json file as well as the list of
    users that are needed for this particular course (which is a list of User objects).

    Attributes:
        code
        semester
        instructor
        gradeables
        users
    """
    def __init__(self, course):
        self.semester = get_current_semester()
        self.code = course['code']
        self.instructor = course['instructor']
        self.gradeables = []
        ids = []
        for gradeable in course['gradeables']:
            self.gradeables.append(Gradeable(gradeable))
            assert self.gradeables[-1].id not in ids
            ids.append(self.gradeables[-1].id)
        self.users = []
        self.registration_sections = set()
        self.rotating_sections = set()

    def create(self):
        course_group = self.code + "_tas_www"
        archive_group = self.code + "_archive"
        create_group(self.code)
        create_group(course_group)
        create_group(archive_group)
        add_to_group(self.code, self.instructor.id)
        add_to_group(course_group, self.instructor.id)
        add_to_group(archive_group, self.instructor.id)
        os.system("{}/bin/create_course.sh {} {} {} {}"
                  .format(SUBMITTY_INSTALL_DIR, self.semester, self.code, "instructor",
                          course_group))

        os.environ['PGPASSWORD'] = DB_PASS
        database = "submitty_" + self.semester + "_" + self.code
        os.system('psql -d postgres -h {} -U hsdbu -c "CREATE DATABASE {}"'.format(DB_HOST,
                                                                                   database))
        os.system("psql -d {} -h {} -U {} -f {}/site/data/tables.sql"
                  .format(database, DB_HOST, DB_USER, SUBMITTY_REPOSITORY))

        engine = create_engine("postgresql://{}:{}@{}/{}".format(DB_USER, DB_PASS, DB_HOST,
                                                                 database))
        conn = engine.connect()
        metadata = MetaData(bind=engine)
        table = Table("sections_registration", metadata, autoload=True)
        for section in self.registration_sections:
            conn.execute(table.insert(), sections_registration_id=section)

        table = Table("sections_rotating", metadata, autoload=True)
        for section in self.rotating_sections:
            conn.execute(table.insert(), sections_rotating_id=section)

        users_table = Table("users", metadata, autoload=True)
        reg_table = Table("grading_registration", metadata, autoload=True)
        for user in self.users:
            conn.execute(users_table.insert(), user_id=user.get_detail(self.code, "id"),
                         user_password=user.get_detail(self.code, "password"),
                         user_firstname=user.get_detail(self.code, "firstname"),
                         user_preferred_firstname=user.get_detail(self.code, "preferred_firstname"),
                         user_lastname=user.get_detail(self.code, "lastname"),
                         user_email=user.get_detail(self.code, "email"),
                         user_group=user.get_detail(self.code, "group"),
                         registration_section=user.get_detail(self.code, "registration_section"),
                         rotating_section=user.get_detail(self.code, "rotating_section"),
                         manual_registration=user.get_detail(self.code, "manual"))

            if user.get_detail(self.code, "grading_registration_section") is not None:
                conn.execute(reg_table.insert(),
                             user_id=user.get_detail(self.code, "id"),
                             sections_registration_id=
                             user.get_detail(self.code, "grading_registration_section"))

            if user.unix_groups is None:
                if user.get_detail(self.code, "group") <= 1:
                    add_to_group(self.code, user.id)
                    add_to_group(self.code + "_archive", user.id)
                if user.get_detail(self.code, "group") <= 2:
                    add_to_group(self.code + "_tas_www", user.id)

        gradeable_table = Table("gradeable", metadata, autoload=True)
        electronic_table = Table("electronic_gradeable", metadata, autoload=True)
        reg_table = Table("grading_rotating", metadata, autoload=True)
        component_table = Table('gradeable_component', metadata, autoload=True)
        for gradeable in self.gradeables:
            gradeable.create(conn, gradeable_table, electronic_table, reg_table, component_table)
            form = os.path.join(SUBMITTY_DATA_DIR, "courses", self.semester, self.code,
                                "config", "form", "form_{}.json".format(gradeable.id))
            with open(form, "w") as open_file:
                json.dump(gradeable.create_form(), open_file, indent=2)
        conn.close()

        os.system("{}/courses/{}/{}/BUILD_{}.sh".format(SUBMITTY_DATA_DIR, self.semester,
                                                        self.code, self.code))
        os.environ['PGPASSWORD'] = ""

    def check_rotating(self, users):
        for gradeable in self.gradeables:
            for grading_rotating in gradeable.grading_rotating:
                string = "Invalid user_id {} for rotating section for gradeable {}".format(
                    grading_rotating['user_id'], gradeable.id)
                if grading_rotating['user_id'] not in users:
                    raise ValueError(string)


class Gradeable(object):
    """
    Attributes:
        config_path
        id
        type
    """
    def __init__(self, gradeable):
        self.id = ""
        self.config_path = None
        self.title = ""
        self.instructions_url = ""
        self.overall_ta_instructions = ""
        self.team_assignment = False
        self.grade_by_registration = True
        self.is_repository = False
        self.subdirectory = ""
        self.use_ta_grading = True
        self.late_days = 2
        self.precision = 0.5
        self.syllabus_bucket = "none (for practice only)"
        self.min_grading_group = 3
        self.grading_rotating = []

        if 'gradeable_config' in gradeable:
            self.config_path = os.path.join(SAMPLE_DIR, gradeable['gradeable_config'])
            if 'g_id' in gradeable:
                self.id = gradeable['g_id']
            else:
                self.id = gradeable['gradeable_config']
            self.type = 0
        else:
            self.config_path = None
            self.type = int(gradeable['g_type'])
            self.id = gradeable['g_id']
        assert 0 <= self.type <= 2

        if 'g_title' in gradeable:
            self.title = gradeable['g_title']
        else:
            self.title = self.id.replace("_", " ").title()

        if 'g_grade_by_registration' in gradeable:
            self.grade_by_registration = gradeable['g_grade_by_registration'] is True

        if 'grading_rotating' in gradeable:
            self.grading_rotating = gradeable['grading_rotating']

        self.ta_view_date = parse_datetime(gradeable['g_ta_view_start_date'])
        self.grade_start_date = parse_datetime(gradeable['g_grade_start_date'])
        self.grade_released_date = parse_datetime(gradeable['g_grade_released_date'])
        if self.type == 0:
            self.submission_open_date = parse_datetime(gradeable['eg_submission_open_date'])
            self.submission_due_date = parse_datetime(gradeable['eg_submission_due_date'])
            if 'eg_is_repository' in gradeable:
                self.is_repository = gradeable['eg_is_repository'] is True
            if self.is_repository and 'eg_subdirectory' in gradeable:
                self.subdirectory = gradeable['eg_subdirectory']
            if 'eg_use_ta_grading' in gradeable:
                self.use_ta_grading = gradeable['eg_use_ta_grading'] is True
            if 'eg_late_days' in gradeable:
                self.late_days = max(0, int(gradeable['eg_late_days']))
            if 'eg_precision' in gradeable:
                self.precision = float(gradeable['eg_precision'])
            if self.config_path is None:
                self.config_path = os.path.join(SAMPLE_DIR, self.id)
            assert self.ta_view_date < self.submission_open_date
            assert self.submission_open_date < self.submission_due_date
            assert self.submission_due_date < self.grade_start_date
        assert self.ta_view_date < self.grade_start_date
        assert self.grade_start_date < self.grade_released_date

        self.components = []
        for i in range(len(gradeable['components'])):
            component = gradeable['components'][i]
            if self.type < 2:
                component['gc_is_text'] = False
            elif self.type > 0:
                component['gc_ta_comment'] = ""
                component['gc_student_comment'] = ""

            if self.type == 1:
                component['gc_max_value'] = 1
            self.components.append(Component(component, i))

    def create(self, conn, gradeable_table, electronic_table, reg_table, component_table):
        conn.execute(gradeable_table.insert(), g_id=self.id, g_title=self.title,
                     g_instructions_url=self.instructions_url,
                     g_overall_ta_instructions=self.overall_ta_instructions,
                     g_team_assignment=self.team_assignment, g_gradeable_type=self.type,
                     g_grade_by_registration=self.grade_by_registration,
                     g_ta_view_start_date=self.ta_view_date,
                     g_grade_start_date=self.grade_start_date,
                     g_grade_released_date=self.grade_released_date,
                     g_syllabus_bucket=self.syllabus_bucket,
                     g_min_grading_group=self.min_grading_group,
                     g_closed_date=None)

        for rotate in self.grading_rotating:
            conn.execute(reg_table.insert(), g_id=self.id, user_id=rotate['user_id'],
                         sections_rotating=rotate['section_rotating_id'])

        if self.type == 0:
            conn.execute(electronic_table.insert(), g_id=self.id,
                         eg_submission_open_date=self.submission_open_date,
                         eg_submission_due_date=self.submission_due_date,
                         eg_is_repository=self.is_repository, eg_subdirectory=self.subdirectory,
                         eg_use_ta_grading=self.use_ta_grading, eg_config_path=self.config_path,
                         eg_late_days=self.late_days, eg_precision=self.precision)

        for component in self.components:
            component.create(self.id, conn, component_table)

    def create_form(self):
        form_json = OrderedDict()
        form_json['gradeable_id'] = self.id
        if self.type == 0:
            form_json['config_path'] = self.config_path
        form_json['gradeable_title'] = self.title
        form_json['gradeable_type'] = self.get_gradeable_type_text()
        form_json['instructions_url'] = self.instructions_url
        form_json['ta_view_date'] = datetime_str(self.ta_view_date)
        if self.type == 0:
            form_json['date_submit'] = datetime_str(self.submission_open_date)
            form_json['date_due'] = datetime_str(self.submission_due_date)
        form_json['date_grade'] = datetime_str(self.grade_start_date)
        form_json['date_released'] = datetime_str(self.grade_released_date)

        if self.type == 0:
            form_json['section_type'] = self.get_submission_type()
            form_json['eg_late_days'] = self.late_days
            form_json['upload_type'] = self.get_upload_type()
            form_json['upload_repo'] = self.subdirectory
            form_json['comment_title'] = []
            form_json['points'] = []
            form_json['eg_extra'] = []
            form_json['ta_comment'] = []
            form_json['student_comment'] = []
            for i in range(len(self.components)):
                component = self.components[i]
                form_json['comment_title'].append(component.title)
                form_json['points'].append(component.max_value)
                form_json['ta_comment'].append(component.ta_comment)
                form_json['student_comment'].append(component.student_comment)
                if component.is_extra_credit:
                    form_json['eg_extra'].append(i+1)
        elif self.type == 1:
            form_json['checkpoint_label'] = []
            form_json['checkpoint_extra'] = []
            for i in range(len(self.components)):
                component = self.components[i]
                form_json['checkpoint_label'].append(component.title)
                if component.is_extra_credit:
                    form_json['checkpoint_extra'].append(i+1)
        else:
            form_json['num_numeric_items'] = 0
            form_json['numeric_labels'] = []
            form_json['max_score'] = []
            form_json['numeric_extra'] = []
            form_json['num_text_items'] = 0
            form_json['text_label'] = []
            for i in range(len(self.components)):
                component = self.components[i]
                if component.is_text:
                    form_json['num_text_items'] += 1
                    form_json['text_label'].append(component.title)
                else:
                    form_json['num_numeric_items'] += 1
                    form_json['numeric_labels'].append(component.title)
                    form_json['max_score'].append(component.max_value)
                    if component.is_extra_credit:
                        form_json['numeric_extra'].append(i+1)
        form_json['minimum_grading_group'] = self.min_grading_group
        form_json['gradeable_buckets'] = self.syllabus_bucket

        return form_json

    def get_gradeable_type_text(self):
        if self.type == 0:
            return "Electronic File"
        elif self.type == 1:
            return "Checkpoints"
        else:
            return "Numeric"

    def get_submission_type(self):
        if self.grade_by_registration:
            return "reg_section"
        else:
            return "rotating-section"

    def get_upload_type(self):
        if self.is_repository:
            return "Repository"
        else:
            return "Upload File"


class Component(object):
    def __init__(self, component, order):
        self.title = component['gc_title']
        self.ta_comment = ""
        self.student_comment = ""

        self.is_text = False
        self.is_extra_credit = False
        self.order = order
        if 'gc_ta_comment' in component:
            self.ta_comment = component['gc_ta_comment']
        if 'gc_student_comment' in component:
            self.student_comment = component['gc_student_comment']
        if 'gc_is_text' in component:
            self.is_text = component['gc_is_text'] is True
        if 'gc_is_extra_credit' in component:
            self.is_extra_credit = component['gc_is_extra_credit'] is True

        if self.is_text:
            self.max_value = 0
        else:
            self.max_value = float(component['gc_max_value'])

    def create(self, g_id, conn, table):
        ins = table.insert().values(g_id=g_id, gc_title=self.title, gc_ta_comment=self.ta_comment,
                                    gc_student_comment=self.student_comment,
                                    gc_max_value=self.max_value, gc_is_text=self.is_text,
                                    gc_is_extra_credit=self.is_extra_credit, gc_order=self.order)
        conn.execute(ins)


if __name__ == "__main__":
    main()
