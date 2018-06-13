#!/usr/bin/env python3
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
from shutil import copyfile
import glob
import grp
import hashlib
import json
import os
import pwd
import random
import shutil
import subprocess
import uuid
import os.path
import string
import sys
import configparser
import csv
import pdb

from submitty_utils import dateutils

# TODO: Remove this and purely use shutil once we move totally to Python 3
from zipfile import ZipFile

from sqlalchemy import create_engine, Table, MetaData, bindparam, select, join
import yaml

CURRENT_PATH = os.path.dirname(os.path.realpath(__file__))
SETUP_DATA_PATH = os.path.join(CURRENT_PATH, "..", "data")

SUBMITTY_REPOSITORY = "/usr/local/submitty/GIT_CHECKOUT/Submitty"
SUBMITTY_INSTALL_DIR = "/usr/local/submitty"
SUBMITTY_DATA_DIR = "/var/local/submitty"
MORE_EXAMPLES_DIR = os.path.join(SUBMITTY_INSTALL_DIR, "more_autograding_examples")
TUTORIAL_DIR = os.path.join(SUBMITTY_INSTALL_DIR, "GIT_CHECKOUT/Tutorial", "examples")

DB_HOST = "localhost"
DB_USER = "hsdbu"
with open(os.path.join(SUBMITTY_INSTALL_DIR,".setup","submitty_conf.json")) as submitty_config:
    submitty_config_json = json.load(submitty_config)
    DB_PASS = submitty_config_json["database_password"]

DB_ONLY = False

NOW = dateutils.get_current_time()


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

    # We have to kill crontab and all running grading scheduler processes as otherwise we end up with the process
    # grabbing the homework files that we are inserting before we're ready to (and permission errors exist) which
    # ends up with just having a ton of build failures. Better to wait on grading any homeworks until we've done
    # all steps of setting up a course.
    print ("pausing the autograding scheduling daemon")
    os.system("crontab -u hwcron -l > /tmp/hwcron_cron_backup.txt")
    os.system("crontab -u hwcron -r")
    os.system("systemctl stop submitty_autograding_shipper")
    os.system("systemctl stop submitty_autograding_worker")

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
                              last_updated=NOW.strftime("%Y-%m-%d %H:%M:%S%z"))

    for user in extra_students:
        submitty_conn.execute(user_table.insert(),
                              user_id=user.id,
                              user_password=get_php_db_password(user.password),
                              user_firstname=user.firstname,
                              user_preferred_firstname=user.preferred_firstname,
                              user_lastname=user.lastname,
                              user_email=user.email,
                              last_updated=NOW.strftime("%Y-%m-%d %H:%M:%S%z"))
    submitty_conn.close()

    today = datetime.today()
    semester = 'Fall'
    if today.month < 7:
        semester = 'Spring'

    list_of_courses_file = "/usr/local/submitty/site/app/views/current_courses.php"
    with open(list_of_courses_file, "w") as courses_file:
        courses_file.write("")
        for course_id in courses.keys():
            courses_file.write('<a href="'+args.submission_url+'/index.php?semester='+get_current_semester()+'&course='+course_id+'">'+course_id+', '+semester+' '+str(today.year)+'</a>')
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

    # restart the autograding daemon
    print("restarting the autograding scheduling daemon")
    os.system("crontab -u hwcron /tmp/hwcron_cron_backup.txt")
    os.system("rm /tmp/hwcron_cron_backup.txt")
    os.system("systemctl restart submitty_autograding_shipper")
    os.system("systemctl restart submitty_autograding_worker")

    # queue up all of the newly created submissions to grade!
    os.system("/usr/local/submitty/bin/regrade.py --no_input /var/local/submitty/courses/")

def generate_random_user_id(length=15):
    return ''.join(random.choice(string.ascii_lowercase + string.ascii_uppercase +string.digits) for _ in range(length))

def generate_random_ta_comment():
    line = ""
    with open(os.path.join(SETUP_DATA_PATH, 'random', 'TAComment.txt')) as comment:
        line = next(comment)
        for num, aline in enumerate(comment):
            if random.randrange(num + 2): continue
            line = aline
    return line.strip()

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
                anon_id = generate_random_user_id()
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
    return out.decode('utf-8')


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
    parser.add_argument("--submission_url", type=str, default="",help="top level url for the website")
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
        if 'gradeables' in course:
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
        m.update(bytes(self.code, 'utf-8'))
        random.seed(int(m.hexdigest(), 16))

        course_group = self.code + "_tas_www"
        archive_group = self.code + "_archive"
        create_group(self.code)
        create_group(course_group)
        create_group(archive_group)
        add_to_group(self.code, self.instructor.id)
        add_to_group(course_group, self.instructor.id)
        add_to_group(archive_group, self.instructor.id)
        add_to_group("course_builders", self.instructor.id)
        add_to_group(course_group, "hwphp")
        add_to_group(course_group, "hwcron")
        add_to_group(course_group, "hwcgi")
        os.system("{}/sbin/create_course.sh {} {} {} {}"
                  .format(SUBMITTY_INSTALL_DIR, self.semester, self.code, self.instructor.id,
                          course_group))

        os.environ['PGPASSWORD'] = DB_PASS
        database = "submitty_" + self.semester + "_" + self.code

        print("Database created, now populating ", end="")
        submitty_engine = create_engine("postgresql://{}:{}@{}/submitty".format(DB_USER, DB_PASS, DB_HOST))
        submitty_conn = submitty_engine.connect()
        submitty_metadata = MetaData(bind=submitty_engine)

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
            conn.execute(table.insert(), sections_registration_id=str(section))

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
            if reg_section is not None:
                reg_section=str(reg_section)    
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
                                 sections_registration_id=str(grading_registration_section))

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
        mark_table = Table('gradeable_component_mark', metadata, autoload=True)
        gradeable_data = Table("gradeable_data", metadata, autoload=True)
        gradeable_component_data = Table("gradeable_component_data", metadata, autoload=True)
        gradeable_component_mark_data = Table('gradeable_component_mark_data', metadata, autoload=True)
        electronic_gradeable_data = Table("electronic_gradeable_data", metadata, autoload=True)
        electronic_gradeable_version = Table("electronic_gradeable_version", metadata, autoload=True)
        gradeable_teams_table = Table("gradeable_teams", metadata, autoload=True)
        teams_table = Table("teams", metadata, autoload=True)
        course_path = os.path.join(SUBMITTY_DATA_DIR, "courses", self.semester, self.code)
        for gradeable in self.gradeables:
            gradeable.create(conn, gradeable_table, electronic_table, reg_table, component_table, mark_table)
            form = os.path.join(course_path, "config", "form", "form_{}.json".format(gradeable.id))
            with open(form, "w") as open_file:
                json.dump(gradeable.create_form(), open_file, indent=2)
        os.system("chown -f hwphp:{}_tas_www {}".format(self.code, os.path.join(course_path, "config", "form", "*")))
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
            #create_teams
            json_team_history = {}
            if gradeable.team_assignment is True:
                ucounter = 0
                for user in self.users:
                    #the unique team id is made up of 5 digits, an underline, and the team creater's userid. 
                    #example: 00001_aphacker
                    unique_team_id=str(ucounter).zfill(5)+"_"+user.get_detail(self.code, "id")
                    team_in_other_gradeable = select([gradeable_teams_table]).where(
                        gradeable_teams_table.c['team_id'] == unique_team_id)
                    res = conn.execute(team_in_other_gradeable)
                    num = res.rowcount                        
                    while num is not 0:
                        ucounter+=1
                        unique_team_id=str(ucounter).zfill(5)+"_"+user.get_detail(self.code, "id")
                        team_in_other_gradeable = select([gradeable_teams_table]).where(
                        gradeable_teams_table.c['team_id'] == unique_team_id)
                        res = conn.execute(team_in_other_gradeable)
                        num = res.rowcount
                    res.close()
                    reg_section = user.get_detail(self.code, "registration_section")
                    if reg_section is None:
                        continue
                    #The teams are created based on the order of the users. As soon as the number of teamates
                    #exceeds the max team size, then a new team will be created within the same registration section
                    print("Adding team for " + unique_team_id + " in gradeable " + gradeable.id)
                    #adding json data for team history                     
                    teams_registration = select([gradeable_teams_table]).where(
                        (gradeable_teams_table.c['registration_section'] == str(reg_section)) &
                        (gradeable_teams_table.c['g_id'] == gradeable.id))
                    res = conn.execute(teams_registration)
                    added = False
                    if res.rowcount != 0:
                        #If the registration has a team already, join it
                        for team_in_section in res:  
                            members_in_team = select([teams_table]).where(
                                teams_table.c['team_id'] == team_in_section['team_id'])
                            res = conn.execute(members_in_team)
                            if res.rowcount < gradeable.max_team_size:                        
                                conn.execute(teams_table.insert(),
                                            team_id=team_in_section['team_id'], 
                                            user_id=user.get_detail(self.code, "id"),
                                            state=1)
                                json_team_history[team_in_section['team_id']].append({"action": "admin_create",
                                                                     "time": dateutils.write_submitty_date(gradeable.submission_open_date),
                                                                     "admin_user": "instructor",
                                                                     "added_user": user.get_detail(self.code, "id")})
                                added = True
                    if not added:
                        #if the team the user tried to join is full, make a new team
                        conn.execute(gradeable_teams_table.insert(),
                                     team_id=unique_team_id,
                                     g_id=gradeable.id,
                                     registration_section=str(reg_section),
                                     rotation_section=None)
                        conn.execute(teams_table.insert(),
                                     team_id=unique_team_id, 
                                     user_id=user.get_detail(self.code, "id"),
                                     state=1)
                        json_team_history[unique_team_id] =  [{"action": "admin_create",
                                                             "time": dateutils.write_submitty_date(gradeable.submission_open_date),
                                                             "admin_user": "instructor",
                                                             "first_user": user.get_detail(self.code, "id")}]
                        ucounter+=1
                    res.close()
            if gradeable.type == 0 and \
                (len(gradeable.submissions) == 0 or
                 gradeable.sample_path is None or
                 gradeable.config_path is None):
                    continue
            #creating the folder containing all the submissions
            gradeable_path = os.path.join(course_path, "submissions", gradeable.id)

            submission_count = 0
            max_submissions = gradeable.max_random_submissions
            #This for loop adds submissions for users and teams(if applicable)
            for user in self.users:
                submitted = False
                team_id = None
                if gradeable.team_assignment is True:
                    res = conn.execute("SELECT teams.team_id FROM teams INNER JOIN gradeable_teams\
                    ON teams.team_id = gradeable_teams.team_id where user_id='{}' and g_id='{}'".format(user.id, gradeable.id))
                    temp = res.fetchall()
                    if(temp):
                        team_id = temp[0][0]
                    res.close()
                if team_id is not None:
                    previous_submission = select([electronic_gradeable_version]).where(
                                                  electronic_gradeable_version.c['team_id'] == team_id)
                    res = conn.execute(previous_submission)
                    if res.rowcount > 0:
                        continue
                    submission_path = os.path.join(gradeable_path, team_id)
                else: 
                    submission_path = os.path.join(gradeable_path, user.id)

                if gradeable.type == 0 and gradeable.submission_open_date < NOW:
                    versions_to_submit = 0
                    #The chance of a student submitting 3 versions is 20%, submitting 2 versions is 30%, and submitting 1 version is 50%.
                    random_num = random.choice(range(0, 100))
                    #TODO: make this configureable
                    if random_num < 20:
                        versions_to_submit = 3
                    elif random_num < 50:
                        versions_to_submit = 2
                    else:
                        versions_to_submit = 1
                    if (gradeable.gradeable_config is not None and
                       (gradeable.submission_due_date < NOW or random.random() < 0.5)
                       and (random.random() < 0.9) and (max_submissions is None or submission_count < max_submissions)):
                        # only create these directories if we're actually going to put something in them
                        if not os.path.exists(gradeable_path):
                            os.makedirs(gradeable_path)
                            os.system("chown -R hwphp:{}_tas_www {}".format(self.code, gradeable_path))
                        if not os.path.exists(submission_path):
                            os.makedirs(submission_path)
                        active_version = random.choice(range(1, versions_to_submit+1))
                        if team_id is not None:
                            json_history = {"active_version": active_version, "history": [], "team_history": []}
                        else:
                            json_history = {"active_version": active_version, "history": []}
                        random_days = 1
                        if random.random() < 0.3:
                            random_days = random.choice(range(-3,2))
                        for version in range(1, versions_to_submit+1):
                            os.system("mkdir -p " + os.path.join(submission_path, str(version)))
                            submitted = True
                            submission_count += 1
                            current_time_string = dateutils.write_submitty_date(gradeable.submission_due_date - timedelta(days=random_days+version/versions_to_submit))
                            if team_id is not None:
                                conn.execute(electronic_gradeable_data.insert(), g_id=gradeable.id, user_id=None,
                                             team_id=team_id, g_version=version, submission_time=current_time_string)
                                if version == versions_to_submit:
                                    conn.execute(electronic_gradeable_version.insert(), g_id=gradeable.id, user_id=None,
                                                 team_id=team_id, active_version=active_version)
                                json_history["team_history"] = json_team_history[team_id]
                            else:
                                conn.execute(electronic_gradeable_data.insert(), g_id=gradeable.id, user_id=user.id,
                                            g_version=version, submission_time=current_time_string)
                                if version == versions_to_submit:
                                    conn.execute(electronic_gradeable_version.insert(), g_id=gradeable.id, user_id=user.id,
                                                active_version=active_version)
                            json_history["history"].append({"version": version, "time": current_time_string, "who": user.id, "type": "upload"})
                            with open(os.path.join(submission_path, str(version), ".submit.timestamp"), "w") as open_file:
                                open_file.write(current_time_string + "\n")
                            if isinstance(gradeable.submissions, dict):
                                for key in gradeable.submissions:
                                    os.system("mkdir -p " + os.path.join(submission_path, str(version), key))
                                    submission = random.choice(gradeable.submissions[key])
                                    src = os.path.join(gradeable.sample_path, submission)
                                    dst = os.path.join(submission_path, str(version), key)
                                    create_gradeable_submission(src, dst)
                            else:
                                submission = random.choice(gradeable.submissions)
                                if isinstance(submission, list):
                                    submissions = submission
                                else:
                                    submissions = [submission]
                                for submission in submissions:
                                    src = os.path.join(gradeable.sample_path, submission)
                                    dst = os.path.join(submission_path, str(version))
                                    create_gradeable_submission(src, dst)
                            random_days-=0.5
                        
                        with open(os.path.join(submission_path, "user_assignment_settings.json"), "w") as open_file:
                                json.dump(json_history, open_file)
                if gradeable.grade_start_date < NOW and os.path.exists(os.path.join(submission_path, str(versions_to_submit))):
                    if gradeable.grade_released_date < NOW or (random.random() < 0.5 and (submitted or gradeable.type !=0)):
                        status = 1 if gradeable.type != 0 or submitted else 0
                        print("Inserting {} for {}...".format(gradeable.id, user.id))
                        values = {'g_id': gradeable.id, 'gd_overall_comment': 'lorem ipsum lodar'}
                        if gradeable.team_assignment is True:
                            values['gd_team_id'] = team_id
                        else:
                            values['gd_user_id'] = user.id
                        if gradeable.grade_released_date < NOW and random.random() < 0.5:
                            values['gd_user_viewed_date'] = NOW.strftime('%Y-%m-%d %H:%M:%S%z')
                        ins = gradeable_data.insert().values(**values)
                        res = conn.execute(ins)
                        gd_id = res.inserted_primary_key[0]
                        if gradeable.type !=0 or gradeable.use_ta_grading:
                            skip_grading = random.random()
                            for component in gradeable.components:
                                if random.random() < 0.05 and skip_grading < 0.3:
                                    #This is used to simulate unfinished grading.
                                    # pdb.set_trace()
                                    break
                                if status == 0 or random.random() < 0.4:
                                    score = 0
                                else:
                                    score = random.randint(component.lower_clamp * 2, component.max_value * 2) / 2
                                    if random.random() < 0.1:
                                        score = random.randint(component.lower_clamp * 2, component.upper_clamp * 2) / 2
                                    if random.random() < 0.1:
                                        #custom mark takes away points
                                        score = -score
                                    if random.random() < 0.01: 
                                        #Just for some weird number example
                                        score = -99999
                                grade_time = gradeable.grade_start_date.strftime("%Y-%m-%d %H:%M:%S%z")
                                conn.execute(gradeable_component_data.insert(), gc_id=component.key, gd_id=gd_id,
                                             gcd_score=score, gcd_component_comment=generate_random_ta_comment(),
                                             gcd_grader_id=self.instructor.id, gcd_grade_time=grade_time, gcd_graded_version=versions_to_submit)
                                first = True
                                first_set = False
                                for mark in component.marks:
                                    if (random.random() < 0.5 and first_set == False and first == False) or random.random() < 0.2:
                                        conn.execute(gradeable_component_mark_data.insert(), gc_id=component.key, gd_id=gd_id, gcm_id=mark.key, gcd_grader_id=self.instructor.id)
                                        if(first):
                                            first_set = True
                                    first = False

                if gradeable.type == 0 and os.path.isdir(submission_path):
                    os.system("chown -R hwphp:{}_tas_www {}".format(self.code, submission_path))
        #This segment adds the sample forum posts for the sample course only
        if(self.code == "sample"): 
            #set sample course to have forum enabled by default
            config = configparser.ConfigParser()    
            config.read(os.path.join(course_path, "config", "config.ini"))
            config.set("course_details", "forum_enabled", "true")
            with open(os.path.join(course_path, "config", "config.ini"), 'w') as configfile:
                config.write(configfile)
            f_data = (self.getForumDataFromFile('posts.txt'), self.getForumDataFromFile('threads.txt'), self.getForumDataFromFile('categories.txt'))
            forum_threads = Table("threads", metadata, autoload=True)
            forum_posts = Table("posts", metadata, autoload=True)
            forum_cat_list = Table("categories_list", metadata, autoload=True)
            forum_thread_cat = Table("thread_categories", metadata, autoload=True)

            for catData in f_data[2]:
                conn.execute(forum_cat_list.insert(), category_desc=catData[0])

            for thread_id, threadData in enumerate(f_data[1], start = 1):
                conn.execute(forum_threads.insert(),
                                  title=threadData[0],
                                  created_by=threadData[1],
                                  pinned=True if threadData[2] == "t" else False,
                                  deleted=True if threadData[3] == "t" else False,
                                  merged_thread_id=threadData[4],
                                  merged_post_id=threadData[5],
                                  is_visible=True if threadData[6] == "t" else False)
                conn.execute(forum_thread_cat.insert(), thread_id=thread_id, category_id=threadData[7])
            counter = 1
            for postData in f_data[0]:
                if(postData[10] != "f" and postData[10] != ""):
                    #In posts.txt, if the 10th column is f or empty, then no attachment is added. If anything else is in the column, then it will be treated as the file name.
                    attachment_path = os.path.join(course_path, "forum_attachments", str(postData[0]), str(counter))
                    os.makedirs(attachment_path)
                    os.system("chown -R hwphp:sample_tas_www {}".format(os.path.join(course_path, "forum_attachments", str(postData[0]))))
                    copyfile(os.path.join(SETUP_DATA_PATH, "forum", "attachments", postData[10]), os.path.join(attachment_path, postData[10]))
                counter += 1
                conn.execute(forum_posts.insert(),
                                  thread_id=postData[0],
                                  parent_id=postData[1],
                                  author_user_id=postData[2],
                                  content=postData[3],
                                  timestamp=postData[4],
                                  anonymous=True if postData[5] == "t" else False,
                                  deleted=True if postData[6] == "t" else False,
                                  endorsed_by=postData[7],
                                  resolved = True if postData[8] == "t" else False,
                                  type=postData[9],
                                  has_attachment=True if postData[10] != "f" else False)

            print('Added forum data to sample course.') 
        

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

    def getForumDataFromFile(self, filename):
        forum_path = os.path.join(SETUP_DATA_PATH, "forum")
        forum_data = []
        for line in open(os.path.join(forum_path, filename)):
            l = [ x.replace("\\n","\n").strip() for x in line.split("|") ]
            if(len(line) > 1):
                forum_data.append(l)
        return forum_data

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
        m.update(bytes(course_id, "utf-8"))
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
            gradeables_percentages.append(random.randint(1, max(1, gradeable_percentage_left)) + 1)
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
        self.team_assignment = False
        self.max_team_size = 1

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

        self.ta_view_date = dateutils.parse_datetime(gradeable['g_ta_view_start_date'])
        self.grade_start_date = dateutils.parse_datetime(gradeable['g_grade_start_date'])
        self.grade_released_date = dateutils.parse_datetime(gradeable['g_grade_released_date'])
        if self.type == 0:
            self.submission_open_date = dateutils.parse_datetime(gradeable['eg_submission_open_date'])
            self.submission_due_date = dateutils.parse_datetime(gradeable['eg_submission_due_date'])
            self.team_lock_date = dateutils.parse_datetime(gradeable['eg_submission_due_date'])
            self.student_view = True
            self.student_submit = True
            self.student_download = False
            self.student_any_version = True
            if 'eg_is_repository' in gradeable:
                self.is_repository = gradeable['eg_is_repository'] is True
            if self.is_repository and 'eg_subdirectory' in gradeable:
                self.subdirectory = gradeable['eg_subdirectory']
            if 'eg_peer_grading' in gradeable:
                self.peer_grading = gradeable['eg_peer_grading'] is False
            if 'eg_use_ta_grading' in gradeable:
                self.use_ta_grading = gradeable['eg_use_ta_grading'] is True
            if 'eg_student_view' in gradeable:
                self.student_view = gradeable['eg_student_view'] is True
            if 'eg_student_submit' in gradeable:
                self.student_submit = gradeable['eg_student_submit'] is True
            if 'eg_student_download' in gradeable:
                self.student_download = gradeable['eg_student_download'] is True
            if 'eg_student_any_version' in gradeable:
                self.student_any_version = gradeable['eg_student_any_version'] is True
            if 'eg_late_days' in gradeable:
                self.late_days = max(0, int(gradeable['eg_late_days']))
            else:
                self.late_days = random.choice(range(0, 3))
            if 'eg_precision' in gradeable:
                self.precision = float(gradeable['eg_precision'])
            if 'eg_team_assignment' in gradeable:
                self.team_assignment = gradeable['eg_team_assignment'] is True
            if 'eg_max_team_size' in gradeable:
                self.max_team_size = gradeable['eg_max_team_size']
            if 'eg_team_lock_date' in gradeable:
                self.team_lock_date = submitty_utils.parse_datetime(gradeable['eg_team_lock_date'])
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
                component['gc_page'] = 0

            if self.type == 1:
                component['gc_lower_clamp'] = 0
                component['gc_default'] = 0
                component['gc_max_value'] = 1
                component['gc_upper_clamp'] = 1
            i-=1;
            self.components.append(Component(component, i+1))

    def create(self, conn, gradeable_table, electronic_table, reg_table, component_table, mark_table):
        conn.execute(gradeable_table.insert(), g_id=self.id, g_title=self.title,
                     g_instructions_url=self.instructions_url,
                     g_overall_ta_instructions=self.overall_ta_instructions,
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
                         eg_team_assignment=self.team_assignment,
                         eg_max_team_size=self.max_team_size,
                         eg_team_lock_date=self.team_lock_date,
                         eg_use_ta_grading=self.use_ta_grading, 
                         eg_student_view=self.student_view, 
                         eg_student_submit=self.student_submit, eg_student_download=self.student_download,
                         eg_student_any_version=self.student_any_version, eg_config_path=self.config_path,
                         eg_late_days=self.late_days, eg_precision=self.precision, eg_peer_grading=self.peer_grading)

        for component in self.components:
            component.create(self.id, conn, component_table, mark_table)

    def create_form(self):
        form_json = OrderedDict()
        form_json['gradeable_id'] = self.id
        if self.type == 0:
            form_json['config_path'] = self.config_path
        form_json['gradeable_title'] = self.title
        form_json['gradeable_type'] = self.get_gradeable_type_text()
        form_json['instructions_url'] = self.instructions_url
        form_json['ta_view_date'] = dateutils.write_submitty_date(self.ta_view_date)
        if self.type == 0:
            form_json['date_submit'] = dateutils.write_submitty_date(self.submission_open_date)
            form_json['date_due'] = dateutils.write_submitty_date(self.submission_due_date)
        form_json['date_grade'] = dateutils.write_submitty_date(self.grade_start_date)
        form_json['date_released'] = dateutils.write_submitty_date(self.grade_released_date)

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
                # form_json['lower_clamp'].append(component.lower_clamp)
                # form_json['default'].append(component.default)
                form_json['points'].append(component.max_value)
                # form_json['upper_clamp'].append(component.upper_clamp)
                form_json['ta_comment'].append(component.ta_comment)
                form_json['student_comment'].append(component.student_comment)
        elif self.type == 1:
            form_json['checkpoint_label'] = []
            form_json['checkpoint_extra'] = []
            for i in range(len(self.components)):
                component = self.components[i]
                form_json['checkpoint_label'].append(component.title)
        else:
            form_json['num_numeric_items'] = 0
            form_json['numeric_labels'] = []
            form_json['lower_clamp'] = []
            form_json['default'] = []
            form_json['max_score'] = []
            form_json['upper_clamp'] = []
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
                    form_json['lower_clamp'].append(component.lower_clamp)
                    form_json['default'].append(component.default)
                    form_json['max_score'].append(component.max_value)
                    form_json['upper_clamp'].append(component.upper_clamp)
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
        self.is_peer = False
        self.page = 0
        self.order = order
        self.marks = []
        if 'marks' in component:
            for i in range(len(component['marks'])):
                mark = component['marks'][i]
                self.marks.append(Mark(mark, i))

        if 'gc_ta_comment' in component:
            self.ta_comment = component['gc_ta_comment']
        if 'gc_student_comment' in component:
            self.student_comment = component['gc_student_comment']
        if 'gc_is_text' in component:
            self.is_text = component['gc_is_text'] is True
        if 'gc_page' in component:
            self.page = int(component['gc_page'])

        if self.is_text:
            self.lower_clamp = 0
            self.default = 0
            self.max_value = 0
            self.upper_clamp = 0
        else:
            self.lower_clamp = float(component['gc_lower_clamp'])
            self.default = float(component['gc_default'])
            self.max_value = float(component['gc_max_value'])
            self.upper_clamp = float(component['gc_upper_clamp'])

        self.key = None

    def create(self, g_id, conn, table, mark_table):
        ins = table.insert().values(g_id=g_id, gc_title=self.title, gc_ta_comment=self.ta_comment,
                                    gc_student_comment=self.student_comment,
                                    gc_lower_clamp=self.lower_clamp, gc_default=self.default, gc_max_value=self.max_value, 
                                    gc_upper_clamp=self.upper_clamp, gc_is_text=self.is_text,
                                    gc_is_peer=self.is_peer, gc_order=self.order, gc_page=self.page)
        res = conn.execute(ins)
        self.key = res.inserted_primary_key[0]

        for mark in self.marks:
            mark.create(self.key, conn, mark_table)

class Mark(object):
    def __init__(self, mark, order):
        self.note = mark['gcm_note']
        self.points = mark['gcm_points']
        self.order = order
        self.grader = 'instructor'
        self.key = None

    def create(self, gc_id, conn, table):
        ins = table.insert().values(gc_id=gc_id, gcm_points=self.points, gcm_note=self.note,
                                    gcm_order=self.order)
        res = conn.execute(ins)
        self.key = res.inserted_primary_key[0]
        
if __name__ == "__main__":
    main()
