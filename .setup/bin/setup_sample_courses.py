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
from __future__ import print_function, division
import argparse
from collections import OrderedDict
from datetime import datetime, timedelta
import glob
import grp
import hashlib
import json
import os
import pwd
import random
import re
import shutil
import subprocess
import uuid
# TODO: Remove this and purely use shutil once we move totally to Python 3
from zipfile import ZipFile

from sqlalchemy import create_engine, Table, MetaData, bindparam
import yaml

CURRENT_PATH = os.path.dirname(os.path.realpath(__file__))
SETUP_DATA_PATH = os.path.join(CURRENT_PATH, "..", "data")

SUBMITTY_REPOSITORY = "/usr/local/submitty/GIT_CHECKOUT_Submitty"
SUBMITTY_INSTALL_DIR = "/usr/local/submitty"
SUBMITTY_DATA_DIR = "/var/local/submitty"
MORE_EXAMPLES_DIR = os.path.join(SUBMITTY_INSTALL_DIR, "more_autograding_examples")
TUTORIAL_DIR = os.path.join(SUBMITTY_INSTALL_DIR, "GIT_CHECKOUT_Tutorial", "examples")

DB_HOST = "localhost"
DB_USER = "hsdbu"
DB_PASS = "hsdbu"

DB_ONLY = False

NOW = datetime.now()


def main():
    """
    Main program execution. This gets us our commandline arugments, reads in the data files,
    and then sets us up to run the create methods for the users and courses.
    """
    global DB_ONLY

    args = parse_args()
    DB_ONLY = args.db_only
    if not os.path.isdir(SUBMITTY_DATA_DIR):
        raise SystemError("The following directory does not exist: " + SUBMITTY_DATA_DIR)
    for directory in ["courses", "instructors"]:
        if not os.path.isdir(os.path.join(SUBMITTY_DATA_DIR, directory)):
            raise SystemError("The following directory does not exist: " + os.path.join(
                SUBMITTY_DATA_DIR, directory))
    use_courses = args.course

    # We have to kill crontab and all running grade students processes as otherwise we end up with the process
    # grabbing the homework files that we are inserting before we're ready to (and permission errors exist) which
    # ends up with just having a ton of build failures. Better to wait on grading any homeworks until we've done
    # all steps of setting up a course.
    os.system("crontab -u hwcron -l > /tmp/hwcron_cron_backup.txt")
    os.system("crontab -u hwcron -r")
    os.system("killall grade_students.sh")

    courses = {}  # dict[str, Course]
    users = {}  # dict[str, User]
    for course_file in glob.iglob(os.path.join(args.courses_path, '*.yml')):
        course_json = load_data_yaml(course_file)
        if len(use_courses) == 0 or course_json['code'] in use_courses:
            course = Course(course_json)
            courses[course.code] = course

    create_group("course_builders")

    for user_file in glob.iglob(os.path.join(args.users_path, '*.yml')):
        user = User(load_data_yaml(user_file))
        if user.id in ['hwphp', 'hwcron', 'hwcgi', 'hsdbu', 'vagrant', 'postgres'] or \
                user.id.startswith("untrusted"):
            continue
        user.create()
        users[user.id] = user
        if user.courses is not None:
            for course in user.courses:
                if course in courses:
                    courses[course].users.append(user)
        else:
            for key in courses.keys():
                courses[key].users.append(user)

    # To make Rainbow Grades testing possible, need to seed random to have the same users each time
    random.seed(10090542)

    # we get the max number of extra students, and then create a list that holds all of them,
    # which we then randomly choose from to add to a course
    extra_students = 0
    for course_id in courses:
        course = courses[course_id]
        tmp = course.registered_students + course.unregistered_students + \
              course.no_rotating_students + \
              course.no_registration_students
        extra_students = max(tmp, extra_students)
    extra_students = generate_random_users(extra_students, users)

    submitty_engine = create_engine("postgresql://{}:{}@{}/submitty".format(DB_USER, DB_PASS, DB_HOST))
    submitty_conn = submitty_engine.connect()
    submitty_metadata = MetaData(bind=submitty_engine)
    user_table = Table('users', submitty_metadata, autoload=True)
    for user_id, user in users.items():
        submitty_conn.execute(user_table.insert(),
                              user_id=user.id,
                              user_password=get_php_db_password(user.password),
                              user_firstname=user.firstname,
                              user_preferred_firstname=user.preferred_firstname,
                              user_lastname=user.lastname,
                              user_email=user.email,
                              last_updated=NOW.strftime("%Y-%m-%d %H:%M:%S"))

    for user in extra_students:
        submitty_conn.execute(user_table.insert(),
                              user_id=user.id,
                              user_password=get_php_db_password(user.password),
                              user_firstname=user.firstname,
                              user_preferred_firstname=user.preferred_firstname,
                              user_lastname=user.lastname,
                              user_email=user.email,
                              last_updated=NOW.strftime("%Y-%m-%d %H:%M:%S"))
    submitty_conn.close()

    today = datetime.today()
    semester = 'Fall'
    if today.month < 7:
        semester = 'Spring'

    list_of_courses_file = "/usr/local/submitty/site/app/views/current_courses.php"
    with open(list_of_courses_file, "w") as courses_file:
        courses_file.write("")
        for course_id in courses.keys():
            courses_file.write('<a href="http://192.168.56.101/index.php?semester='+get_current_semester()+'&course='+course_id+'">'+course_id+', '+semester+' '+str(today.year)+'</a>')
            courses_file.write('<br />')

    for course_id in courses.keys():
        course = courses[course_id]
        students = random.sample(extra_students, course.registered_students + course.no_registration_students +
                                 course.no_rotating_students + course.unregistered_students)
        key = 0
        for i in range(course.registered_students):
            reg_section = (i % course.registration_sections) + 1
            rot_section = (i % course.rotating_sections) + 1
            students[key].courses[course.code] = {"registration_section": reg_section, "rotating_section": rot_section}
            course.users.append(students[key])
            key += 1

        for i in range(course.no_rotating_students):
            reg_section = (i % course.registration_sections) + 1
            students[key].courses[course.code] = {"registration_section": reg_section, "rotating_section": None}
            course.users.append(students[key])
            key += 1

        for i in range(course.no_registration_students):
            rot_section = (i % course.rotating_sections) + 1
            students[key].courses[course.code] = {"registration_section": None, "rotating_section": rot_section}
            course.users.append(students[key])
            key += 1

        for i in range(course.unregistered_students):
            students[key].courses[course.code] = {"registration_section": None, "rotating_section": None}
            course.users.append(students[key])
            key += 1

    for course in courses.keys():
        courses[course].instructor = users[courses[course].instructor]
        courses[course].check_rotating(users)
        courses[course].create()
        if courses[course].make_customization:
            courses[course].make_course_json()

    os.system("crontab -u hwcron /tmp/hwcron_cron_backup.txt")
    os.system("rm /tmp/hwcron_cron_backup.txt")

def generate_random_user_id(length):
    id_base = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"
    anon_id = ""
    for i in range(length):
        anon_id += random.choice(id_base)
    return anon_id


def generate_random_users(total, real_users):
    """

    :param total:
    :param real_users:
    :return:
    :rtype: list[User]
    """
    with open(os.path.join(SETUP_DATA_PATH, 'random', 'lastNames.txt')) as last_file, \
            open(os.path.join(SETUP_DATA_PATH, 'random', 'maleFirstNames.txt')) as male_file, \
            open(os.path.join(SETUP_DATA_PATH, 'random', 'womenFirstNames.txt')) as woman_file:
        last_names = last_file.read().strip().split()
        male_names = male_file.read().strip().split()
        women_names = woman_file.read().strip().split()

    users = []
    user_ids = []
    anon_ids = []
    with open(os.path.join(SETUP_DATA_PATH, "random_users.txt"), "w") as random_users_file:
        for i in range(total):
            if random.random() < 0.5:
                first_name = random.choice(male_names)
            else:
                first_name = random.choice(women_names)
            last_name = random.choice(last_names)
            user_id = last_name.replace("'", "")[:5] + first_name[0]
            user_id = user_id.lower()
            anon_id = generate_random_user_id(15)
            while user_id in user_ids or user_id in real_users:
                if user_id[-1].isdigit():
                    user_id = user_id[:-1] + str(int(user_id[-1]) + 1)
                else:
                    user_id = user_id + "1"
            if anon_id in anon_ids:
                anon_id = generate_random_user_id(15)
            new_user = User({"user_id": user_id,
                             "anon_id": anon_id,
                             "user_firstname": first_name,
                             "user_lastname": last_name,
                             "user_group": 4,
                             "courses": dict()})
            new_user.create()
            user_ids.append(user_id)
            users.append(new_user)
            anon_ids.append(anon_id)
            random_users_file.write(user_id + "\n")
    return users


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


def load_data_yaml(file_path):
    """
    Loads yaml file from the .setup/data directory returning the parsed structure
    :param file_path: name of file to load
    :return: parsed YAML structure from loaded file
    """
    if not os.path.isfile(file_path):
        raise IOError("Missing the yaml file {}".format(file_path))
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
    :type date_string: str
    :return:
    :rtype: datetime
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
        return NOW.replace(hour=hour, minute=minu, second=sec) + timedelta(days=days)

    m = re.search('([+|\-][0-9]+) (days|day)', date_string)
    if m is not None:
        days = int(m.group(1))
        return NOW.replace(hour=23, minute=59, second=59) + timedelta(days=days)

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

    parser.add_argument("--db_only", action='store_true')
    parser.add_argument("--users_path", default=os.path.join(SETUP_DATA_PATH, "users"),
                        help="Path to folder that contains .yml files to use for user creation. Defaults to "
                             "../data/users")
    parser.add_argument("--courses_path", default=os.path.join(SETUP_DATA_PATH, "courses"),
                        help="Path to the folder that contains .yml files to use for course creation. Defaults to "
                             "../data/courses")
    parser.add_argument("course", nargs="*",
                        help="course code to build. If no courses are passed in, then it'll use "
                             "all courses in courses.json")
    return parser.parse_args()


def create_user(user_id):
    if not user_exists(id):
        print("Creating user {}...".format(user_id))
        os.system("/usr/sbin/adduser {} --quiet --home /tmp --gecos \'AUTH ONLY account\' "
                  "--no-create-home --disabled-password --shell "
                  "/usr/sbin/nologin".format(user_id))
        print("Setting password for user {}...".format(user_id))
        os.system("echo {}:{} | chpasswd".format(user_id, user_id))


def create_gradeable_submission(src, dst):
    """
    Given a source and a destination, copy the files from the source to the destination. First, before
    copying, we check if the source is a directory, if it is, then we zip the contents of this to a temp
    zip file (stored in /tmp) and store the path to this newly created zip as our new source.

    At this point, (for all uploads), we check if our source is a zip (by just checking file extension is
    a .zip), then we will extract the contents of the source (using ZipFile) to the destination, else we
    just do a simple copy operation of the source file to the destination location.

    At this point, if we created a zip file (as part of that first step), we remove it from the /tmp directory.

    :param src: path of the file or directory we want to use for this submission
    :type src: str
    :param dst: path to the folder where we should copy the submission to
    :type src: str
    """
    zip_dst = None
    if os.path.isdir(src):
        zip_dst = os.path.join("/tmp", str(uuid.uuid4()))
        zip_dst = shutil.make_archive(zip_dst, 'zip', src)
        src = zip_dst

    if src[-3:] == "zip":
        with ZipFile(src, 'r') as zip_file:
            zip_file.extractall(dst)
    else:
        shutil.copy(src, dst)

    if zip_dst is not None and isinstance(zip_dst, str):
        os.remove(zip_dst)


class User(object):
    """
    A basic object to contain the objects loaded from the users.json file. We use this to link
    against the courses.

    Attributes:
        id
        anon_id
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
        self.anon_id = user['anon_id']
        self.password = self.id
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
            self.grading_registration_section = user['grading_registration_section']
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
                for course in self.courses:
                    if 'user_group' not in self.courses[course]:
                        self.courses[course]['user_group'] = self.group
            else:
                raise ValueError("Invalid type for courses key, it should either be list or dict")
        if 'sudo' in user:
            self.sudo = user['sudo'] is True
        if 'user_password' in user:
            self.password = user['user_password']

    def create(self, force_ssh=False):
        if not DB_ONLY:
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
        if not DB_ONLY and not user_exists(self.id):
            print("Creating user {}...".format(self.id))
            os.system("/usr/sbin/adduser {} --quiet --home /tmp --gecos \'AUTH ONLY account\' "
                      "--no-create-home --disabled-password --shell "
                      "/usr/sbin/nologin".format(self.id))
            self.set_password()

    def set_password(self):
        print("Setting password for user {}...".format(self.id))
        os.system("echo {}:{} | chpasswd".format(self.id, self.password))

    def get_detail(self, course, detail):
        if self.courses is not None and course in self.courses:
            user_detail = "user_" + detail
            if user_detail in self.courses[course]:
                return self.courses[course][user_detail]
            elif detail in self.courses[course]:
                return self.courses[course][detail]
        if detail in self.__dict__:
            return self.__dict__[detail]
        else:
            return None


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
        max_random_submissions
    """
    def __init__(self, course):
        self.semester = get_current_semester()
        self.code = course['code']
        self.instructor = course['instructor']
        self.gradeables = []
        self.make_customization = False
        ids = []
        for gradeable in course['gradeables']:
            self.gradeables.append(Gradeable(gradeable))
            assert self.gradeables[-1].id not in ids
            ids.append(self.gradeables[-1].id)
        self.users = []
        self.registration_sections = 10
        self.rotating_sections = 5
        self.registered_students = 50
        self.no_registration_students = 10
        self.no_rotating_students = 10
        self.unregistered_students = 10
        if 'registration_sections' in course:
            self.registration_sections = course['registration_sections']
        if 'rotating_sections' in course:
            self.rotating_sections = course['rotating_sections']
        if 'registered_students' in course:
            self.registered_students = course['registered_students']
        if 'no_registration_students' in course:
            self.no_registration_students = course['no_registration_students']
        if 'no_rotating_students' in course:
            self.no_rotating_students = course['no_rotating_students']
        if 'unregistered_students' in course:
            self.unregistered_students = course['unregistered_students']
        if 'make_customization' in course:
            self.make_customization = course['make_customization']

    def create(self):

        # To make Rainbow Grades testing possible, need to seed random
        m = hashlib.md5()
        m.update(self.code)
        random.seed(int(m.hexdigest(),16))

        course_group = self.code + "_tas_www"
        archive_group = self.code + "_archive"
        create_group(self.code)
        create_group(course_group)
        create_group(archive_group)
        add_to_group(self.code, self.instructor.id)
        add_to_group(course_group, self.instructor.id)
        add_to_group(archive_group, self.instructor.id)
        os.system("{}/bin/create_course.sh {} {} {} {}"
                  .format(SUBMITTY_INSTALL_DIR, self.semester, self.code, self.instructor.id,
                          course_group))

        os.environ['PGPASSWORD'] = DB_PASS
        database = "submitty_" + self.semester + "_" + self.code
        os.system('psql -d postgres -h {} -U hsdbu -c "CREATE DATABASE {}"'.format(DB_HOST,
                                                                                   database))
        os.system("psql -d {} -h {} -U {} -f {}/site/data/course_tables.sql"
                  .format(database, DB_HOST, DB_USER, SUBMITTY_REPOSITORY))

        print("Database created, now populating ", end="")
        submitty_engine = create_engine("postgresql://{}:{}@{}/submitty".format(DB_USER, DB_PASS, DB_HOST))
        submitty_conn = submitty_engine.connect()
        submitty_metadata = MetaData(bind=submitty_engine)

        courses_table = Table('courses', submitty_metadata, autoload=True)
        submitty_conn.execute(courses_table.insert(), semester=self.semester, course=self.code)

        engine = create_engine("postgresql://{}:{}@{}/{}".format(DB_USER, DB_PASS, DB_HOST,
                                                                 database))
        conn = engine.connect()
        metadata = MetaData(bind=engine)
        print("(connection made, metadata bound)...")
        print("Creating registration sections ", end="")
        table = Table("sections_registration", metadata, autoload=True)
        print("(tables loaded)...")
        for section in range(1, self.registration_sections+1):
            print("Create section {}".format(section))
            conn.execute(table.insert(), sections_registration_id=section)

        print("Creating rotating sections ", end="")
        table = Table("sections_rotating", metadata, autoload=True)
        print("(tables loaded)...")
        for section in range(1, self.rotating_sections+1):
            print("Create section {}".format(section))
            conn.execute(table.insert(), sections_rotating_id=section)

        print("Create users ", end="")
        submitty_users = Table("courses_users", submitty_metadata, autoload=True)
        users_table = Table("users", metadata, autoload=True)
        reg_table = Table("grading_registration", metadata, autoload=True)
        print("(tables loaded)...")
        for user in self.users:
            print("Creating user {} {} ({})...".format(user.get_detail(self.code, "firstname"),
                                                       user.get_detail(self.code, "lastname"),
                                                       user.get_detail(self.code, "id")))
            reg_section = user.get_detail(self.code, "registration_section")
            if reg_section is not None and reg_section > self.registration_sections:
                reg_section = None
            rot_section = user.get_detail(self.code, "rotating_section")
            if rot_section is not None and rot_section > self.rotating_sections:
                rot_section = None
            # We already have a row in submitty.users for this user,
            # just need to add a row in courses_users which will put a
            # a row in the course specific DB, and off we go.
            submitty_conn.execute(submitty_users.insert(),
                                  semester=self.semester,
                                  course=self.code,
                                  user_id=user.get_detail(self.code, "id"),
                                  user_group=user.get_detail(self.code, "group"),
                                  registration_section=reg_section,
                                  manual_registration=user.get_detail(self.code, "manual"))
            update = users_table.update(values={
                users_table.c.rotating_section: bindparam('rotating_section'),
                users_table.c.anon_id: bindparam('anon_id')
            }).where(users_table.c.user_id == bindparam('b_user_id'))

            conn.execute(update, rotating_section=rot_section, anon_id=user.anon_id, b_user_id=user.id)
            if user.get_detail(self.code, "grading_registration_section") is not None:
                try:
                    grading_registration_sections = str(user.get_detail(self.code,"grading_registration_section"))
                    grading_registration_sections = [int(x) for x in grading_registration_sections.split(",")]
                except ValueError:
                    grading_registration_sections = []
                for grading_registration_section in grading_registration_sections:
                    conn.execute(reg_table.insert(),
                                 user_id=user.get_detail(self.code, "id"),
                                 sections_registration_id=grading_registration_section)

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
        gradeable_data = Table("gradeable_data", metadata, autoload=True)
        gradeable_component_data = Table("gradeable_component_data", metadata, autoload=True)
        electronic_gradeable_data = Table("electronic_gradeable_data", metadata, autoload=True)
        electronic_gradeable_version = Table("electronic_gradeable_version", metadata, autoload=True)
        course_path = os.path.join(SUBMITTY_DATA_DIR, "courses", self.semester, self.code)
        for gradeable in self.gradeables:
            gradeable.create(conn, gradeable_table, electronic_table, reg_table, component_table)
            form = os.path.join(course_path, "config", "form", "form_{}.json".format(gradeable.id))
            with open(form, "w") as open_file:
                json.dump(gradeable.create_form(), open_file, indent=2)
        os.system("chown hwphp:{}_tas_www {}".format(self.code, os.path.join(course_path, "config", "form", "*")))
        if not os.path.isfile(os.path.join(course_path, "ASSIGNMENTS.txt")):
            os.system("touch {}".format(os.path.join(course_path, "ASSIGNMENTS.txt")))
            os.system("chown {}:{}_tas_www {}".format(self.instructor.id, self.code,
                                                      os.path.join(course_path, "ASSIGNMENTS.txt")))
        os.system("su {} -c '{}'".format(self.instructor.id, os.path.join(course_path,
                                                                          "BUILD_{}.sh".format(self.code))))
        os.system("chown -R {}:{}_tas_www {}".format(self.instructor.id, self.code, os.path.join(course_path, "build")))
        os.system("chown -R {}:{}_tas_www {}".format(self.instructor.id, self.code,
                                                     os.path.join(course_path, "test_*")))

        # On python 3, replace with os.makedirs(..., exist_ok=True)
        os.system("mkdir -p {}".format(os.path.join(course_path, "submissions")))
        os.system('chown hwphp:{}_tas_www {}'.format(self.code, os.path.join(course_path, 'submissions')))
        for gradeable in self.gradeables:
            if gradeable.type == 0 and \
                (len(gradeable.submissions) == 0 or
                 gradeable.sample_path is None or
                 gradeable.config_path is None):
                    continue

            gradeable_path = os.path.join(course_path, "submissions", gradeable.id)

            if gradeable.type == 0:
                os.makedirs(gradeable_path)
                os.system("chown -R hwphp:{}_tas_www {}".format(self.code, gradeable_path))

            submission_count = 0
            max_submissions = gradeable.max_random_submissions
            for user in self.users:
                submitted = False
                submission_path = os.path.join(gradeable_path, user.id)
                if gradeable.type == 0 and gradeable.submission_open_date < NOW:
                    os.makedirs(submission_path)
                    if not (gradeable.gradeable_config is None or \
                            (gradeable.submission_due_date < NOW and random.random() < 0.5) or \
                            (random.random() < 0.3) or \
                            (max_submissions is not None and submission_count >= max_submissions)):
                        os.system("mkdir -p " + os.path.join(submission_path, "1"))
                        submitted = True
                        submission_count += 1
                        current_time = (gradeable.submission_due_date - timedelta(days=1)).strftime("%Y-%m-%d %H:%M:%S")
                        conn.execute(electronic_gradeable_data.insert(), g_id=gradeable.id, user_id=user.id,
                                     g_version=1, submission_time=current_time)
                        conn.execute(electronic_gradeable_version.insert(), g_id=gradeable.id, user_id=user.id,
                                     active_version=1)
                        with open(os.path.join(submission_path, "user_assignment_settings.json"), "w") as open_file:
                            json.dump({"active_version": 1, "history": [{"version": 1, "time": current_time}]},
                                      open_file)
                        with open(os.path.join(submission_path, "1", ".submit.timestamp"), "w") as open_file:
                            open_file.write(current_time + "\n")

                        if isinstance(gradeable.submissions, dict):
                            for key in gradeable.submissions:
                                os.system("mkdir -p " + os.path.join(submission_path, "1", key))
                                submission = random.choice(gradeable.submissions[key])
                                src = os.path.join(gradeable.sample_path, submission)
                                dst = os.path.join(submission_path, "1", key)
                                create_gradeable_submission(src, dst)
                        else:
                            submission = random.choice(gradeable.submissions)
                            if isinstance(submission, list):
                                submissions = submission
                            else:
                                submissions = [submission]
                            for submission in submissions:
                                src = os.path.join(gradeable.sample_path, submission)
                                dst = os.path.join(submission_path, "1")
                                create_gradeable_submission(src, dst)

                if gradeable.grade_start_date < NOW:
                    if gradeable.grade_released_date < NOW or random.random() < 0.8:
                        status = 1 if gradeable.type != 0 or submitted else 0
                        print("Inserting {} for {}...".format(gradeable.id, user.id))
                        ins = gradeable_data.insert().values(g_id=gradeable.id, gd_user_id=user.id,
                                                             gd_overall_comment="lorem ipsum lodar")
                        res = conn.execute(ins)
                        gd_id = res.inserted_primary_key[0]
                        if gradeable.type !=0 or gradeable.use_ta_grading:
                            for component in gradeable.components:
                                if status == 0:
                                    score = 0
                                elif component.max_value > 0:
                                    score = random.randint(0, component.max_value * 2) / 2
                                else:
                                    score = random.randint(component.max_value * 2, 0) / 2
                                grade_time = gradeable.grade_start_date.strftime("%Y-%m-%d %H:%M:%S")
                                conn.execute(gradeable_component_data.insert(), gc_id=component.key, gd_id=gd_id,
                                             gcd_score=score, gcd_component_comment="lorem ipsum",
                                             gcd_grader_id=self.instructor.id, gcd_grade_time=grade_time, gcd_graded_version=1)

                if gradeable.type == 0 and os.path.isdir(submission_path):
                    os.system("chown -R hwphp:{}_tas_www {}".format(self.code, submission_path))

                if gradeable.type == 0 and submitted:
                    queue_file = "__".join([self.semester, self.code, gradeable.id, user.id, "1"])
                    print("Creating queue file:", queue_file)
                    queue_file = os.path.join(SUBMITTY_DATA_DIR, "to_be_graded_batch", queue_file)
                    with open(queue_file, "w") as open_file:
                        # FIXME: This will need to be adjusted for team assignments!
                        json.dump({"semester": self.semester,
                                   "course": self.code,
                                   "gradeable": gradeable.id,
                                   "user": user.id,
                                   "version": 1,
                                   "who": user.id,
                                   "is_team": False,
                                   "team": ""}, open_file)
        conn.close()
        submitty_conn.close()
        os.environ['PGPASSWORD'] = ""

    def check_rotating(self, users):
        for gradeable in self.gradeables:
            for grading_rotating in gradeable.grading_rotating:
                string = "Invalid user_id {} for rotating section for gradeable {}".format(
                    grading_rotating['user_id'], gradeable.id)
                if grading_rotating['user_id'] not in users:
                    raise ValueError(string)

    def make_course_json(self):
        """
        This function generates customization_sample.json in case it has changed from the provided version in the test suite
        within the Submitty repository. Ideally this function will be pulled out and made independent, or better yet when
        the code for the web interface is done, that will become the preferred route and this function can be retired.

        Keeping this function after the web interface would mean we have another place where we need to update code anytime
        the expected format of customization.json changes.

        Right now the code uses the Gradeable and Component classes, so to avoid code duplication the function lives inside
        setup_sample_courses.py

        :return:
        """

        course_id = self.code

        # Reseed to minimize the situations under which customization.json changes
        m = hashlib.md5()
        m.update(course_id)
        random.seed(int(m.hexdigest(), 16))

        customization_path = os.path.join(SUBMITTY_INSTALL_DIR, ".setup")
        print("Generating customization_{}.json".format(course_id))

        gradeables = {}
        gradeables_json_output = {}

        # Create gradeables by syllabus bucket
        for gradeable in self.gradeables:
            if gradeable.syllabus_bucket not in gradeables:
                gradeables[gradeable.syllabus_bucket] = []
            gradeables[gradeable.syllabus_bucket].append(gradeable)

        # Randomly generate the impact of each bucket on the overall grade
        gradeables_percentages = []
        gradeable_percentage_left = 100 - len(gradeables)
        for i in range(len(gradeables)):
            gradeables_percentages.append(random.randint(1, gradeable_percentage_left) + 1)
            gradeable_percentage_left -= (gradeables_percentages[-1] - 1)
        if gradeable_percentage_left > 0:
            gradeables_percentages[-1] += gradeable_percentage_left

        # Compute totals and write out each syllabus bucket in the "gradeables" field of customization.json
        bucket_no = 0

        for bucket,g_list in gradeables.items():
            bucket_json = {"type": bucket, "count": len(g_list), "percent": 0.01*gradeables_percentages[bucket_no],
                           "ids" : []}

            # Manually total up the non-penalty non-extra-credit max scores, and decide which gradeables are 'released'
            for gradeable in g_list:
                use_ta_grading = gradeable.use_ta_grading
                g_type = gradeable.type
                components = gradeable.components
                g_id = gradeable.id
                max_auto = 0
                max_ta = 0

                print_grades = True if g_type != 0 or (gradeable.submission_open_date < NOW) else False
                release_grades = (gradeable.grade_released_date < NOW)

                # Another spot where if INSTALL_SUBMITTY_HELPER.sh is used we could use fill-in paths
                # gradeable_config_dir = os.path.join("__INSTALL__FILLIN__SUBMITTY_DATA_DIR__", "courses",
                #                                    get_current_semester(), "sample", "config", "complete_config")

                gradeable_config_dir = os.path.join(SUBMITTY_DATA_DIR, "courses", get_current_semester(), "sample",
                                                    "config", "complete_config")

                # For electronic gradeables there is a config file - read through to get the total
                if os.path.isdir(gradeable_config_dir):
                    gradeable_config = os.path.join(gradeable_config_dir, "complete_config_" + g_id + ".json")
                    if os.path.isfile(gradeable_config):
                        try:
                            with open(gradeable_config, 'r') as gradeable_config_file:
                                gradeable_json = json.load(gradeable_config_file)

                                # Not every config has AUTO_POINTS, so have to parse through test cases
                                # Add points to max if not extra credit, and points>0 (not penalty)
                                if "testcases" in gradeable_json:
                                    for test_case in gradeable_json["testcases"]:
                                        if "extra_credit" in test_case:
                                            continue
                                        if "points" in test_case and test_case["points"] > 0:
                                            max_auto += test_case["points"]
                        except EnvironmentError:
                            print("Failed to load JSON")

                # For non-electronic gradeables, or electronic gradeables with TA grading, read through components
                if use_ta_grading or g_type != 0:
                    for component in components:
                        if component.is_extra_credit:
                            continue
                        if component.max_value >0:
                            max_ta += component.max_value

                # Add the specific associative array for this gradeable in customization.json to the output string
                max_points = max_auto + max_ta
                if print_grades:
                    bucket_json["ids"].append({"id": g_id, "max": max_points})
                    if not release_grades:
                        bucket_json["ids"][-1]["released"] = False

            # Close the bucket's array in customization.json
            if "gradeables" not in gradeables_json_output:
                gradeables_json_output["gradeables"] = []
            gradeables_json_output["gradeables"].append(bucket_json)
            bucket_no += 1

        # Generate the section labels
        section_ta_mapping = {}
        for section in range(1, self.registration_sections + 1):
            section_ta_mapping[section] = []
        for user in self.users:
            if user.get_detail(course_id, "grading_registration_section") is not None:
                grading_registration_sections = str(user.get_detail(course_id, "grading_registration_section"))
                grading_registration_sections = [int(x) for x in grading_registration_sections.split(",")]
                for section in grading_registration_sections:
                    section_ta_mapping[section].append(user.id)

        for section in section_ta_mapping:
            if len(section_ta_mapping[section]) == 0:
                section_ta_mapping[section] = "TBA"
            else:
                section_ta_mapping[section] = ", ".join(section_ta_mapping[section])

        # Construct the rest of the JSON dictionary
        benchmarks = ["a-", "b-", "c-", "d"]
        gradeables_json_output["display"] = ["instructor_notes", "grade_summary", "grade_details"]
        gradeables_json_output["display_benchmark"] = ["average", "stddev", "perfect"]
        gradeables_json_output["benchmark_percent"] = {}
        for i in range(len(benchmarks)):
            gradeables_json_output["display_benchmark"].append("lowest_" + benchmarks[i])
            gradeables_json_output["benchmark_percent"]["lowest_" + benchmarks[i]] = 0.9 - (0.1 * i)

        gradeables_json_output["section"] = section_ta_mapping
        messages = ["<b>{} Course</b>".format(course_id),
                    "Note: Please be patient with data entry/grade corrections for the most recent " 
                    "lab, homework, and test.",
                    "Please contact your graduate lab TA if a grade remains missing or incorrect for more than a week."]
        gradeables_json_output["messages"] = messages

        # Attempt to write the customization.json file
        try:
            json.dump(gradeables_json_output,
                      open(os.path.join(customization_path, "customization_" + course_id + ".json"), 'w'),indent=2)
        except EnvironmentError as e:
            print("Failed to write to customization file: {}".format(e))

        print("Wrote customization_{}.json".format(course_id))

class Gradeable(object):
    """
    Attributes:
        config_path
        id
        type
    """
    def __init__(self, gradeable):
        self.id = ""
        self.gradeable_config = None
        self.config_path = None
        self.sample_path = None
        self.title = ""
        self.instructions_url = ""
        self.overall_ta_instructions = ""
        self.team_assignment = False
        self.peer_grading = False
        self.grade_by_registration = True
        self.is_repository = False
        self.subdirectory = ""
        self.use_ta_grading = True
        self.late_days = 2
        self.precision = 0.5
        self.syllabus_bucket = "none (for practice only)"
        self.min_grading_group = 3
        self.grading_rotating = []
        self.submissions = []
        self.max_random_submissions = None

        if 'gradeable_config' in gradeable:
            self.gradeable_config = gradeable['gradeable_config']
            self.type = 0

            if 'g_id' in gradeable:
                self.id = gradeable['g_id']
            else:
                self.id = gradeable['gradeable_config']

            if 'eg_max_random_submissions' in gradeable:
                self.max_random_submissions = int(gradeable['eg_max_random_submissions'])

            if 'config_path' in gradeable:
                self.config_path = gradeable['config_path']
            else:
                examples_path = os.path.join(MORE_EXAMPLES_DIR, self.gradeable_config, "config")
                tutorial_path = os.path.join(TUTORIAL_DIR, self.gradeable_config, "config")
                if os.path.isdir(examples_path):
                    self.config_path = examples_path
                elif os.path.isdir(tutorial_path):
                    self.config_path = tutorial_path
                else:
                    self.config_path = None

            examples_path = os.path.join(MORE_EXAMPLES_DIR, self.gradeable_config, "submissions")
            tutorial_path = os.path.join(TUTORIAL_DIR, self.gradeable_config, "submissions")
            if 'sample_path' in gradeable:
                self.sample_path = gradeable['sample_path']
            else:
                if os.path.isdir(examples_path):
                    self.sample_path = examples_path
                elif os.path.isdir(tutorial_path):
                    self.sample_path = tutorial_path
                else:
                    self.sample_path = None
        else:
            self.id = gradeable['g_id']
            self.type = int(gradeable['g_type'])
            self.config_path = None
            self.sample_path = None

        if 'g_bucket' in gradeable:
            self.syllabus_bucket = gradeable['g_bucket']

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
                examples_path = os.path.join(MORE_EXAMPLES_DIR, self.id, "config")
                tutorial_path = os.path.join(TUTORIAL_DIR, self.id, "config")
                if os.path.isdir(examples_path):
                    self.config_path = examples_path
                elif os.path.isdir(tutorial_path):
                    self.config_path = tutorial_path
                else:
                    self.config_path = None
            assert self.ta_view_date < self.submission_open_date
            assert self.submission_open_date < self.submission_due_date
            assert self.submission_due_date < self.grade_start_date
            if self.gradeable_config is not None:
                if self.sample_path is not None:
                    if os.path.isfile(os.path.join(self.sample_path, "submissions.yml")):
                        self.submissions = load_data_yaml(os.path.join(self.sample_path, "submissions.yml"))
                    else:
                        self.submissions = os.listdir(self.sample_path)
                        self.submissions = list(filter(lambda x: not x.startswith("."), self.submissions))
                    if isinstance(self.submissions, list):
                        for elem in self.submissions:
                            if isinstance(elem, dict):
                                raise TypeError("Cannot have dictionary inside of list for submissions "
                                                "for {}".format(self.sample_path))
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
            self.components.append(Component(component, i+1))

    def create(self, conn, gradeable_table, electronic_table, reg_table, component_table):
        conn.execute(gradeable_table.insert(), g_id=self.id, g_title=self.title,
                     g_instructions_url=self.instructions_url,
                     g_overall_ta_instructions=self.overall_ta_instructions,
                     g_team_assignment=self.team_assignment, 
                     g_peer_grading=self.peer_grading, 
                     g_gradeable_type=self.type,
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
        self.is_peer = False
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

        self.key = None

    def create(self, g_id, conn, table):
        ins = table.insert().values(g_id=g_id, gc_title=self.title, gc_ta_comment=self.ta_comment,
                                    gc_student_comment=self.student_comment,
                                    gc_max_value=self.max_value, gc_is_text=self.is_text,
                                    gc_is_extra_credit=self.is_extra_credit, gc_is_peer=self.is_peer, gc_order=self.order)
        res = conn.execute(ins)
        self.key = res.inserted_primary_key[0]

if __name__ == "__main__":
    main()
