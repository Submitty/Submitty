#!/usr/bin/env python3
"""
Setup script that reads in the users.yml and courses.yml files in the ../data directory and then
creates the users and courses for the system. This is primarily used by Vagrant and Travis to
figure the environments easily, but it could be run pretty much anywhere, unless the courses
already exist as else the system will probably fail.

Usage: ./setup_sample_courses.py
       ./setup_sample_courses.py [course [course]]
       ./setup_sample_courses.py --help

The first will create all courses in courses.yml while the second will only create the courses
specified (which is useful for something like Travis where we don't need the "demo classes", and
just the ones used for testing.)

Note about editing:
If you make changes that use/alter random number generation, you may need to 
edit the following files:
    Peer Review:
        students.txt
        graders.txt
    Office Hours Queue:
        queue_data.json
    Discussion Forum:
        threads.txt
        posts.txt
        
These files are manually written for a given set of users (the set is predetermined due to 
the random's seed staying the same). If you make any changes that affects the contents of the 
set these files will be outdated and result in failure of recreate_sample_courses.
"""
from __future__ import print_function, division
import argparse
from collections import OrderedDict
from datetime import datetime, timedelta
from pathlib import Path
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
import pdb
import docker
import random
from tempfile import TemporaryDirectory
from submitty_utils import dateutils

from sqlalchemy import create_engine, Table, MetaData, bindparam, select, join, func

# if you need to modify any global variables, change this to import file as name
from sample_courses import *
from utils import *
from models import *

def main():
    """
    Main program execution. This gets us our commandline arguments, reads in the data files,
    and then sets us up to run the create methods for the users and courses.
    """

    if not os.path.isdir(SUBMITTY_INSTALL_DIR):
        raise SystemError(f"The following directory does not exist: {SUBMITTY_INSTALL_DIR}")
    if not os.path.isdir(SUBMITTY_DATA_DIR):
        raise SystemError(f"The following directory does not exist: {SUBMITTY_DATA_DIR}")
    for directory in ["courses"]:
        if not os.path.isdir(os.path.join(SUBMITTY_DATA_DIR, directory)):
            raise SystemError("The following directory does not exist: " + os.path.join(
                SUBMITTY_DATA_DIR, directory))
    with open(os.path.join(SUBMITTY_INSTALL_DIR, "config", "database.json")) as database_config:
        database_config_json = json.load(database_config)
        DB_USER = database_config_json["database_user"]
        DB_HOST = database_config_json["database_host"]
        DB_PORT = database_config_json["database_port"]
        DB_PASS = database_config_json["database_password"]
    use_courses = args.course

    # We have to stop all running daemon grading and jobs handling
    # processes as otherwise we end up with the process grabbing the
    # homework files that we are inserting before we're ready to (and
    # permission errors exist) which ends up with just having a ton of
    # build failures. Better to wait on grading any homeworks until
    # we've done all steps of setting up a course.
    print("pausing the autograding and jobs handler daemons")
    os.system("systemctl stop submitty_autograding_shipper")
    os.system("systemctl stop submitty_autograding_worker")
    os.system("systemctl stop submitty_daemon_jobs_handler")
    os.system("systemctl stop submitty_websocket_server")

    courses = {}  # dict[str, Course]
    users = {}  # dict[str, User]
    for course_file in sorted(glob.iglob(os.path.join(args.courses_path, '*.yml'))):
        # only create the plagiarism course if we have a local LichenTestData repo
        if os.path.basename(course_file) == "plagiarism.yml" and not os.path.isdir(os.path.join(SUBMITTY_INSTALL_DIR, "GIT_CHECKOUT", "LichenTestData")):
            continue

        course_json = load_data_yaml(course_file)

        if len(use_courses) == 0 or course_json['code'] in use_courses:
            course = Course(course_json)
            courses[course.code] = course

    create_group("submitty_course_builders")

    for user_file in sorted(glob.iglob(os.path.join(args.users_path, '*.yml'))):
        user = User(load_data_yaml(user_file))
        if user.id in ['submitty_php', 'submitty_daemon', 'submitty_cgi', 'submitty_dbuser', 'vagrant', 'postgres'] or \
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
    for course_id in sorted(courses.keys()):
        course = courses[course_id]
        tmp = course.registered_students + course.unregistered_students + \
              course.no_rotating_students + \
              course.no_registration_students
        extra_students = max(tmp, extra_students)
    extra_students = generate_random_users(extra_students, users)

    submitty_engine = create_engine("postgresql:///submitty?host={}&port={}&user={}&password={}"
                                    .format(DB_HOST, DB_PORT, DB_USER, DB_PASS))
    submitty_conn = submitty_engine.connect()
    submitty_metadata = MetaData(bind=submitty_engine)
    user_table = Table('users', submitty_metadata, autoload=True)
    for user_id in sorted(users.keys()):
        user = users[user_id]
        submitty_conn.execute(user_table.insert(),
                              user_id=user.id,
                              user_numeric_id = user.numeric_id,
                              user_password=get_php_db_password(user.password),
                              user_givenname=user.givenname,
                              user_preferred_givenname=user.preferred_givenname,
                              user_familyname=user.familyname,
                              user_preferred_familyname=user.preferred_familyname,
                              user_email=user.email,
                              user_access_level=user.access_level,
                              user_pronouns=user.pronouns,
                              last_updated=NOW.strftime("%Y-%m-%d %H:%M:%S%z"))

    for user in extra_students:
        submitty_conn.execute(user_table.insert(),
                              user_id=user.id,
                              user_numeric_id=user.numeric_id,
                              user_password=get_php_db_password(user.password),
                              user_givenname=user.givenname,
                              user_preferred_givenname=user.preferred_givenname,
                              user_familyname=user.familyname,
                              user_preferred_familyname=user.preferred_familyname,
                              user_email=user.email,
                              user_pronouns=user.pronouns,
                              last_updated=NOW.strftime("%Y-%m-%d %H:%M:%S%z"))

    # INSERT term into terms table, based on today's date.
    today = datetime.today()
    year = str(today.year)
    if today.month < 7:
        term_id    = "s" + year[-2:]
        term_name  = "Spring " + year
        term_start = "01/02/" + year
        term_end   = "06/30/" + year
    else:
        term_id    = "f" + year[-2:]
        term_name  = "Fall " + year
        term_start = "07/01/" + year
        term_end   = "12/23/" + year

    terms_table = Table("terms", submitty_metadata, autoload=True)
    submitty_conn.execute(terms_table.insert(),
                          term_id    = term_id,
                          name       = term_name,
                          start_date = term_start,
                          end_date   = term_end)

    submitty_conn.close()

    for course_id in sorted(courses.keys()):
        course = courses[course_id]
        total_students = course.registered_students + course.no_registration_students + \
            course.no_rotating_students + course.unregistered_students
        students = extra_students[:total_students]
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

        course.users.sort(key=lambda x: x.id)

    for course in sorted(courses.keys()):
        courses[course].instructor = users[courses[course].instructor]
        courses[course].check_rotating(users)
        courses[course].create()
        if courses[course].make_customization:
            courses[course].make_course_json()

    # restart the autograding daemon
    print("restarting the autograding and jobs handler daemons")
    os.system("systemctl restart submitty_autograding_shipper")
    os.system("systemctl restart submitty_autograding_worker")
    os.system("systemctl restart submitty_daemon_jobs_handler")
    os.system("systemctl restart submitty_websocket_server")

    if not NO_GRADING:
        # queue up all of the newly created submissions to grade!
        os.system(f"{SUBMITTY_INSTALL_DIR}/bin/regrade.py --no_input {SUBMITTY_DATA_DIR}/courses/")


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
        # Sort users and gradeables in the name of determinism
        self.users.sort(key=lambda x: x.get_detail(self.code, "id"))
        self.gradeables.sort(key=lambda x: x.id)
        self.course_path = os.path.join(SUBMITTY_DATA_DIR, "courses", self.semester, self.code)
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
        add_to_group("submitty_course_builders", self.instructor.id)
        add_to_group(course_group, "submitty_php")
        add_to_group(course_group, "submitty_daemon")
        add_to_group(course_group, "submitty_cgi")
        os.system("{}/sbin/create_course.sh {} {} {} {}"
                  .format(SUBMITTY_INSTALL_DIR, self.semester, self.code, self.instructor.id,
                          course_group))

        os.environ['PGPASSWORD'] = DB_PASS
        database = "submitty_" + self.semester + "_" + self.code
        print("Database created, now populating ", end="")

        submitty_engine = create_engine("postgresql:///submitty?host={}&port={}&user={}&password={}"
                                        .format(DB_HOST, DB_PORT, DB_USER, DB_PASS))
        submitty_conn = submitty_engine.connect()
        submitty_metadata = MetaData(bind=submitty_engine)
        print("(Master DB connection made, metadata bound)...")

        engine = create_engine("postgresql:///{}?host={}&port={}&user={}&password={}"
                               .format(database, DB_HOST, DB_PORT, DB_USER, DB_PASS))
        self.conn = engine.connect()
        self.metadata = MetaData(bind=engine)
        print("(Course DB connection made, metadata bound)...")

        print("Creating registration sections ", end="")
        table = Table("courses_registration_sections", submitty_metadata, autoload=True)
        print("(tables loaded)...")
        for section in range(1, self.registration_sections+1):
            print("Create section {}".format(section))
            submitty_conn.execute(table.insert(), term=self.semester, course=self.code, registration_section_id=str(section))

        print("Creating rotating sections ", end="")
        table = Table("sections_rotating", self.metadata, autoload=True)
        print("(tables loaded)...")
        for section in range(1, self.rotating_sections+1):
            print("Create section {}".format(section))
            self.conn.execute(table.insert(), sections_rotating_id=section)

        print("Create users ", end="")
        submitty_users = Table("courses_users", submitty_metadata, autoload=True)
        users_table = Table("users", self.metadata, autoload=True)
        reg_table = Table("grading_registration", self.metadata, autoload=True)
        print("(tables loaded)...")
        for user in self.users:
            print("Creating user {} {} ({})...".format(user.get_detail(self.code, "givenname"),
                                                       user.get_detail(self.code, "familyname"),
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
                                  term=self.semester,
                                  course=self.code,
                                  user_id=user.get_detail(self.code, "id"),
                                  user_group=user.get_detail(self.code, "group"),
                                  registration_section=reg_section,
                                  manual_registration=user.get_detail(self.code, "manual"))
            update = users_table.update(values={
                users_table.c.rotating_section: bindparam('rotating_section')
            }).where(users_table.c.user_id == bindparam('b_user_id'))

            self.conn.execute(update, rotating_section=rot_section, b_user_id=user.id)
            if user.get_detail(self.code, "grading_registration_section") is not None:
                try:
                    grading_registration_sections = str(user.get_detail(self.code,"grading_registration_section"))
                    grading_registration_sections = [int(x) for x in grading_registration_sections.split(",")]
                except ValueError:
                    grading_registration_sections = []
                for grading_registration_section in grading_registration_sections:
                    self.conn.execute(reg_table.insert(),
                                 user_id=user.get_detail(self.code, "id"),
                                 sections_registration_id=str(grading_registration_section))

            if user.unix_groups is None:
                if user.get_detail(self.code, "group") <= 1:
                    add_to_group(self.code, user.id)
                    add_to_group(self.code + "_archive", user.id)
                if user.get_detail(self.code, "group") <= 2:
                    add_to_group(self.code + "_tas_www", user.id)
        gradeable_table = Table("gradeable", self.metadata, autoload=True)
        electronic_table = Table("electronic_gradeable", self.metadata, autoload=True)
        peer_assign = Table("peer_assign", self.metadata, autoload=True)
        reg_table = Table("grading_rotating", self.metadata, autoload=True)
        component_table = Table('gradeable_component', self.metadata, autoload=True)
        mark_table = Table('gradeable_component_mark', self.metadata, autoload=True)
        gradeable_data = Table("gradeable_data", self.metadata, autoload=True)
        gradeable_component_data = Table("gradeable_component_data", self.metadata, autoload=True)
        gradeable_component_mark_data = Table('gradeable_component_mark_data', self.metadata, autoload=True)
        gradeable_data_overall_comment = Table('gradeable_data_overall_comment', self.metadata, autoload=True)
        electronic_gradeable_data = Table("electronic_gradeable_data", self.metadata, autoload=True)
        electronic_gradeable_version = Table("electronic_gradeable_version", self.metadata, autoload=True)
        for gradeable in self.gradeables:
            gradeable.create(self.conn, gradeable_table, electronic_table, peer_assign, reg_table, component_table, mark_table)
            form = os.path.join(self.course_path, "config", "form", "form_{}.json".format(gradeable.id))
            with open(form, "w") as open_file:
                json.dump(gradeable.create_form(), open_file, indent=2)
        os.system("chown -f submitty_php:{}_tas_www {}".format(self.code, os.path.join(self.course_path, "config", "form", "*")))
        if not os.path.isfile(os.path.join(self.course_path, "ASSIGNMENTS.txt")):
            os.system("touch {}".format(os.path.join(self.course_path, "ASSIGNMENTS.txt")))
            os.system("chown {}:{}_tas_www {}".format(self.instructor.id, self.code,
                                                      os.path.join(self.course_path, "ASSIGNMENTS.txt")))
            os.system("chmod -R g+w {}".format(self.course_path))
            os.system("su {} -c '{}'".format("submitty_daemon", os.path.join(self.course_path,
                                                                          "BUILD_{}.sh".format(self.code))))
            #os.system("su {} -c '{}'".format(self.instructor.id, os.path.join(self.course_path,
            #                                                              "BUILD_{}.sh".format(self.code))))
        os.system("chown -R {}:{}_tas_www {}".format(self.instructor.id, self.code, os.path.join(self.course_path, "build")))
        os.system("chown -R {}:{}_tas_www {}".format(self.instructor.id, self.code,
                                                     os.path.join(self.course_path, "test_*")))
        # On python 3, replace with os.makedirs(..., exist_ok=True)
        os.system("mkdir -p {}".format(os.path.join(self.course_path, "submissions")))
        os.system('chown submitty_php:{}_tas_www {}'.format(self.code, os.path.join(self.course_path, 'submissions')))

        anon_ids = {}
        for gradeable in self.gradeables:
            #create gradeable specific anonymous ids for users
            prev_state = random.getstate()
            for user in self.users:
                anon_id = generate_random_user_id(15)
                while anon_id in anon_ids.values():
                    anon_id = generate_random_user_id(15)
                anon_ids[user.id] = anon_id
                gradeable_anon = Table("gradeable_anon", self.metadata, autoload=True)
                self.conn.execute(gradeable_anon.insert(),
                                  user_id=user.id,
                                  g_id=gradeable.id,
                                  anon_id=anon_id)
            random.setstate(prev_state)
            # create_teams
            if gradeable.team_assignment is True:
                json_team_history = self.make_sample_teams(gradeable)
            if gradeable.type == 0 and \
                (len(gradeable.submissions) == 0 or
                 gradeable.sample_path is None or
                 gradeable.config_path is None):
                #  Make sure the electronic gradeable is valid
                continue

            # creating the folder containing all the submissions
            gradeable_path = os.path.join(self.course_path, "submissions", gradeable.id)

            checkout_path = os.path.join(self.course_path, "checkout", gradeable.id)

            if gradeable.is_repository:
                # generate the repos for the vcs gradeable
                print(f"generating repositories for gradeable {gradeable.id}")
                subprocess.check_call(f"sudo {SUBMITTY_INSTALL_DIR}/bin/generate_repos.py {self.semester} {self.code} {gradeable.id}", stdout=subprocess.DEVNULL, stderr=subprocess.STDOUT, shell=True)

            gradeable_annotation_path = os.path.join(self.course_path, "annotations", gradeable.id)

            submission_count = 0
            max_submissions = gradeable.max_random_submissions
            max_individual_submissions = gradeable.max_individual_submissions
            # makes a section be ungraded if the gradeable is not electronic
            ungraded_section = random.randint(1, max(1, self.registration_sections if gradeable.grade_by_registration else self.rotating_sections))
            # This for loop adds submissions/annotations for users and teams(if applicable)
            if not NO_SUBMISSIONS:
                only_submit_plagiarized_users = gradeable.lichen_sample_path is not None and len(gradeable.plagiarized_user) > 0
                for user in self.users:
                    if only_submit_plagiarized_users and user.id not in gradeable.plagiarized_user:
                        continue

                    submitted = False
                    team_id = None
                    anon_team_id = None
                    if gradeable.team_assignment is True:
                        # If gradeable is team assignment, then make sure to make a team_id and don't over submit
                        res = self.conn.execute("SELECT teams.team_id, gradeable_teams.anon_id FROM teams INNER JOIN gradeable_teams\
                        ON teams.team_id = gradeable_teams.team_id where user_id='{}' and g_id='{}'".format(user.id, gradeable.id))
                        temp = res.fetchall()
                        if len(temp) != 0:
                            team_id = temp[0][0]
                            anon_team_id = temp[0][1]
                            previous_submission = select([electronic_gradeable_version]).where(
                                electronic_gradeable_version.c['team_id'] == team_id)
                            res = self.conn.execute(previous_submission)
                            if res.rowcount > 0:
                                continue
                            submission_path = os.path.join(gradeable_path, team_id)
                            annotation_path = os.path.join(gradeable_annotation_path, team_id)
                        else:
                            continue
                        res.close()
                    else:
                        submission_path = os.path.join(gradeable_path, user.id)
                        annotation_path = os.path.join(gradeable_annotation_path, user.id)

                    # need to create the directories for the user/version in "checkout" too for git sunmissions
                    if gradeable.is_repository:
                        user_checkout_path = os.path.join(checkout_path, user.id)
                    else:
                        user_checkout_path = None

                    if gradeable.type == 0 and gradeable.submission_open_date < NOW:
                        if user.id in gradeable.plagiarized_user:
                            # If the user is a bad and unethical student(plagiarized_user), then the version to submit is going to
                            # be the same as the number of assignments defined in users.yml in the lichen_submissions folder.
                            versions_to_submit = len(gradeable.plagiarized_user[user.id])
                        elif gradeable.lichen_sample_path is not None:
                            # if we have set a plagiarism configuration but no manually-specified submissions, submit the default number
                            versions_to_submit = gradeable.plagiarism_versions_per_user
                        else:
                            versions_to_submit = generate_versions_to_submit(max_individual_submissions, max_individual_submissions)

                        if ((gradeable.gradeable_config is not None
                           and (gradeable.has_due_date is True and (gradeable.submission_due_date < NOW or random.random() < 0.5))
                           and (random.random() < 0.9) and (max_submissions is None or submission_count < max_submissions))
                           or (gradeable.gradeable_config is not None and user.id in gradeable.plagiarized_user)):
                            # only create these directories if we're actually going to put something in them
                            if not os.path.exists(gradeable_path):
                                os.makedirs(gradeable_path)
                                os.system("chown -R submitty_php:{}_tas_www {}".format(self.code, gradeable_path))
                            if not os.path.exists(submission_path):
                                os.makedirs(submission_path)
                            if gradeable.is_repository:
                                if not os.path.exists(checkout_path):
                                    os.makedirs(checkout_path)
                                    os.system(f'chown submitty_daemon:{self.code}_tas_www "{checkout_path}"')
                                if not os.path.exists(user_checkout_path):
                                    os.makedirs(user_checkout_path)
                                    os.system(f'chown submitty_daemon:{self.code}_tas_www "{user_checkout_path}"')

                            if gradeable.annotated_pdf is True:
                                if not os.path.exists(gradeable_annotation_path):
                                    os.makedirs(gradeable_annotation_path)
                                if not os.path.exists(annotation_path):
                                    os.makedirs(annotation_path)

                            # Reduce the probability to get a cancelled submission (active_version = 0)
                            # This is done by making other possibilities three times more likely
                            version_population = []
                            for version in range(1, versions_to_submit+1):
                                version_population.append((version, 3))

                            # disallow cancelled submission if this is a manually-specified user
                            if user.id not in gradeable.plagiarized_user:
                                version_population = [(0, 1)] + version_population
                            version_population = [ver for ver, freq in version_population for i in range(freq)]

                            active_version = random.choice(version_population)
                            if team_id is not None:
                                json_history = {"active_version": active_version, "history": [], "team_history": []}
                            else:
                                json_history = {"active_version": active_version, "history": []}
                            random_days = 1
                            if random.random() < 0.3:
                                random_days = random.choice(range(-3, 2))
                            for version in range(1, versions_to_submit+1):
                                os.system("mkdir -p " + os.path.join(submission_path, str(version)))
                                submitted = True
                                submission_count += 1
                                current_time_string = dateutils.write_submitty_date(gradeable.submission_due_date - timedelta(days=random_days+version/versions_to_submit))
                                if team_id is not None:
                                    self.conn.execute(electronic_gradeable_data.insert(), g_id=gradeable.id, user_id=None,
                                                 team_id=team_id, g_version=version, submission_time=current_time_string)
                                    if version == versions_to_submit:
                                        self.conn.execute(electronic_gradeable_version.insert(), g_id=gradeable.id, user_id=None,
                                                     team_id=team_id, active_version=active_version)
                                    json_history["team_history"] = json_team_history[team_id]
                                else:
                                    self.conn.execute(electronic_gradeable_data.insert(), g_id=gradeable.id, user_id=user.id,
                                                g_version=version, submission_time=current_time_string)
                                    if version == versions_to_submit:
                                        self.conn.execute(electronic_gradeable_version.insert(), g_id=gradeable.id, user_id=user.id,
                                                    active_version=active_version)
                                json_history["history"].append({"version": version, "time": current_time_string, "who": user.id, "type": "upload"})

                                with open(os.path.join(submission_path, str(version), ".submit.timestamp"), "w") as open_file:
                                    open_file.write(current_time_string + "\n")

                                if user.id in gradeable.plagiarized_user:
                                    # If the user is in the plagirized folder, then only add those submissions
                                    src = os.path.join(gradeable.lichen_sample_path, gradeable.plagiarized_user[user.id][version-1])
                                    dst = os.path.join(submission_path, str(version))
                                    # pdb.set_trace()
                                    create_gradeable_submission(src, dst)
                                elif gradeable.lichen_sample_path is not None:
                                    if len(gradeable.plagiarism_submissions) > 0:  # check to make sure we haven't run out of data
                                        # if there were no specified plagiarized users but we have plagiarism submissions, grab a random submisison
                                        src = os.path.join(gradeable.lichen_sample_path, gradeable.plagiarism_submissions.pop())
                                        dst = os.path.join(submission_path, str(version))
                                        create_gradeable_submission(src, dst)
                                elif gradeable.annotated_pdf is True:
                                    # Get a list of graders that have access to the submission
                                    assigned_graders = []
                                    stmt = select([
                                        peer_assign.columns.user_id,
                                        peer_assign.columns.grader_id
                                    ]).where(
                                        peer_assign.columns.user_id == user.id
                                    )
                                    for res in self.conn.execute(stmt):
                                        assigned_graders.append(res[1])

                                    submissions = random.sample(gradeable.submissions, random.randint(1, len(gradeable.submissions)))
                                    for submission in submissions:
                                        src = os.path.join(gradeable.sample_path, submission)
                                        dst = os.path.join(submission_path, str(version))
                                        create_gradeable_submission(src, dst)

                                        if version == versions_to_submit:
                                            annotation_version_path = os.path.join(annotation_path, str(versions_to_submit))
                                            if not os.path.exists(annotation_version_path):
                                                os.makedirs(annotation_version_path)

                                            annotations = random.sample(gradeable.annotations, random.randint(1, len(gradeable.annotations)))
                                            graders = random.sample(assigned_graders, len(annotations)-1) if len(assigned_graders) > 0 else []
                                            # Make sure instructor is responsible for one of the annotations
                                            graders.append("instructor")

                                            anon_dst = os.path.join(dst, submission).split("/")
                                            anon_dst[9] = anon_team_id if team_id is not None else anon_ids[user.id]
                                            anon_dst = "/".join(anon_dst) # has the user id or the team id in the file path being anonymous

                                            for i in range(len(graders)):
                                                annotation_src = os.path.join(gradeable.annotation_path, annotations[i])
                                                annotation_dst = os.path.join(annotation_path, str(version))
                                                encoded_path = hashlib.md5(anon_dst.encode()).hexdigest()
                                                # the file name has the format of ENCODED-ANON-SUBMISSION-PATH_GRADER.json
                                                annotation_file_name = f"{str(encoded_path)}_{graders[i]}.json"
                                                create_pdf_annotations(annotation_file_name, anon_dst, annotation_src, annotation_dst, graders[i])
                                else:
                                    if isinstance(gradeable.submissions, dict):
                                        for key in sorted(gradeable.submissions.keys()):
                                            os.system("mkdir -p " + os.path.join(submission_path, str(version), key))
                                            submission = random.choice(gradeable.submissions[key])
                                            src = os.path.join(gradeable.sample_path, submission)
                                            # To mimic a 'checkout', the VCS gradeable files are cloned to the 'user_checkout_ folder
                                            # They are also committed to the repository, so clicking regrade works. 
                                            if gradeable.is_repository:
                                                repo_path = f"{self.semester}/{self.code}/{gradeable.id}/{user.id}"
                                                commit_submission_to_repo(user.id, src, repo_path, gradeable.subdirectory)
                                                mimic_checkout(repo_path, os.path.join(user_checkout_path, str(version)), gradeable.subdirectory)
                                            else:
                                                create_gradeable_submission(src, dst)
                                    else:
                                        submission = random.choice(gradeable.submissions)
                                        if isinstance(submission, list):
                                            submissions = submission
                                        else:
                                            submissions = [submission]
                                        for submission in submissions:
                                            src = os.path.join(gradeable.sample_path, submission)
                                            # To mimic a 'checkout', the VCS gradeable files are cloned to the 'user_checkout_ folder
                                            # They are also committed to the repository, so clicking regrade works. 
                                            if gradeable.is_repository:
                                                repo_path = f"{self.semester}/{self.code}/{gradeable.id}/{user.id}"
                                                commit_submission_to_repo(user.id, src, repo_path, gradeable.subdirectory)
                                                mimic_checkout(repo_path, os.path.join(user_checkout_path, str(version)), gradeable.subdirectory)
                                            else:
                                                dst = os.path.join(submission_path, str(version))
                                                create_gradeable_submission(src, dst)
                                random_days -= 0.5
                            # submissions to vcs greadeable also have a ".submit.VCS_CHECKOUT"
                            if gradeable.is_repository:
                                with open(os.path.join(submission_path, str(version), ".submit.VCS_CHECKOUT"), "w") as open_file:
                                    # the file contains info only if the git repos are non-submitty hosted
                                    pass
                                with open(os.path.join(submission_path, str(version), ".submit.timestamp"), "w") as open_file:
                                    open_file.write(dateutils.write_submitty_date(NOW))

                            else:  
                                with open(os.path.join(submission_path, "user_assignment_settings.json"), "w") as open_file:
                                    json.dump(json_history, open_file)

                    if gradeable.grade_start_date < NOW and os.path.exists(os.path.join(submission_path, str(versions_to_submit))):
                        if (gradeable.has_release_date is True and gradeable.grade_released_date < NOW) or (random.random() < 0.5 and (submitted or gradeable.type !=0)):
                            status = 1 if gradeable.type != 0 or submitted else 0
                            print("Inserting {} for {}...".format(gradeable.id, user.id))
                            values = {'g_id': gradeable.id}
                            overall_comment_values = {'g_id' : gradeable.id,  'goc_overall_comment': 'lorem ipsum lodar', 'goc_grader_id' : self.instructor.id}

                            if gradeable.team_assignment is True:
                                values['gd_team_id'] = team_id
                                overall_comment_values['goc_team_id'] = team_id
                            else:
                                values['gd_user_id'] = user.id
                                overall_comment_values['goc_user_id'] = user.id
                            if gradeable.grade_released_date < NOW and random.random() < 0.5:
                                values['gd_user_viewed_date'] = NOW.strftime('%Y-%m-%d %H:%M:%S%z')
                            ins = gradeable_data.insert().values(**values)
                            res = self.conn.execute(ins)
                            gd_id = res.inserted_primary_key[0]
                            if gradeable.type != 0 or gradeable.use_ta_grading:
                                skip_grading = random.random()
                                if skip_grading > 0.3 and random.random() > 0.01:
                                    ins = gradeable_data_overall_comment.insert().values(**overall_comment_values)
                                    res = self.conn.execute(ins)
                                for component in gradeable.components:
                                    if random.random() < 0.01 and skip_grading < 0.3:
                                        # This is used to simulate unfinished grading.
                                        break
                                    if status == 0 or random.random() < 0.4:
                                        score = 0
                                    else:
                                        max_value_score = random.randint(component.lower_clamp * 2, component.max_value * 2) / 2
                                        uppser_clamp_score = random.randint(component.lower_clamp * 2, component.upper_clamp * 2) / 2
                                        score = generate_probability_space({0.7: max_value_score, 0.2: uppser_clamp_score, 0.08: -max_value_score, 0.02: -99999})
                                    grade_time = gradeable.grade_start_date.strftime("%Y-%m-%d %H:%M:%S%z")
                                    self.conn.execute(gradeable_component_data.insert(), gc_id=component.key, gd_id=gd_id,
                                                 gcd_score=score, gcd_component_comment=generate_random_ta_comment(),
                                                 gcd_grader_id=self.instructor.id, gcd_grade_time=grade_time, gcd_graded_version=versions_to_submit)
                                    first = True
                                    first_set = False
                                    for mark in component.marks:
                                        if (random.random() < 0.5 and first_set == False and first == False) or random.random() < 0.2:
                                            self.conn.execute(gradeable_component_mark_data.insert(), gc_id=component.key, gd_id=gd_id, gcm_id=mark.key, gcd_grader_id=self.instructor.id)
                                            if(first):
                                                first_set = True
                                        first = False

                    if gradeable.type == 0 and os.path.isdir(submission_path):
                        os.system("chown -R submitty_php:{}_tas_www {}".format(self.code, submission_path))

                    if gradeable.type == 0 and os.path.isdir(gradeable_annotation_path):
                        os.system("chown -R submitty_php:{}_tas_www {}".format(self.code, gradeable_annotation_path))

                    if (gradeable.type != 0 and gradeable.grade_start_date < NOW and ((gradeable.has_release_date is True and gradeable.grade_released_date < NOW) or random.random() < 0.5) and
                       random.random() < 0.9 and (ungraded_section != (user.get_detail(self.code, 'registration_section') if gradeable.grade_by_registration else user.get_detail(self.code, 'rotating_section')))):
                        res = self.conn.execute(gradeable_data.insert(), g_id=gradeable.id, gd_user_id=user.id)
                        gd_id = res.inserted_primary_key[0]
                        skip_grading = random.random()
                        for component in gradeable.components:
                            if random.random() < 0.01 and skip_grading < 0.3:
                                break
                            if random.random() < 0.1:
                                continue
                            elif gradeable.type == 1:
                                score = generate_probability_space({0.2: 0, 0.1: 0.5}, 1)
                            else:
                                score = random.randint(component.lower_clamp * 2, component.upper_clamp * 2) / 2
                            grade_time = gradeable.grade_start_date.strftime("%Y-%m-%d %H:%M:%S%z")
                            self.conn.execute(gradeable_component_data.insert(), gc_id=component.key, gd_id=gd_id,
                                         gcd_score=score, gcd_component_comment="", gcd_grader_id=self.instructor.id, gcd_grade_time=grade_time, gcd_graded_version=-1)
        # This segment adds the sample data for features in the sample course only
        if self.code == "sample":
            self.add_sample_forum_data()
            print('Added forum data to sample course.')
            self.add_sample_polls_data()
            print('Added polls data to sample course.')
            self.add_sample_queue_data()
            print('Added office hours queue data to sample course.')

        if self.code == 'sample':
            student_image_folder = os.path.join(SUBMITTY_DATA_DIR, 'courses', self.semester, self.code, 'uploads', 'student_images')
            zip_path = os.path.join(SUBMITTY_REPOSITORY, 'sample_files', 'user_photos', 'CSCI-1300-01.zip')
            with TemporaryDirectory() as tmpdir:
                shutil.unpack_archive(zip_path, tmpdir)
                inner_folder = os.path.join(tmpdir, 'CSCI-1300-01')
                for f in os.listdir(inner_folder):
                    shutil.move(os.path.join(inner_folder, f), os.path.join(student_image_folder, f))
            course_materials_source = os.path.join(SUBMITTY_REPOSITORY, 'sample_files', 'course_materials')
            course_materials_folder = os.path.join(SUBMITTY_DATA_DIR, 'courses', self.semester, self.code, 'uploads', 'course_materials')
            course_materials_table = Table("course_materials", self.metadata, autoload=True)
            for dpath, dirs, files in os.walk(course_materials_source):
                inner_dir=os.path.relpath(dpath, course_materials_source)
                if inner_dir!=".":
                    dir_to_make=os.path.join(course_materials_folder, inner_dir)
                    os.mkdir(dir_to_make)
                    subprocess.run(["chown", "submitty_php:submitty_php", dir_to_make])
                    self.conn.execute(course_materials_table.insert(),
                            path=dir_to_make,
                            type=2,
                            release_date='2022-01-01 00:00:00',
                            hidden_from_students=False,
                            priority=0)
                for f in files:
                    tmpfilepath= os.path.join(dpath,f)
                    filepath=os.path.join(course_materials_folder, os.path.relpath(tmpfilepath, course_materials_source))
                    shutil.copy(tmpfilepath, filepath)
                    subprocess.run(["chown", "submitty_php:submitty_php", filepath])
                    self.conn.execute(course_materials_table.insert(),
                                path=filepath,
                                type=0,
                                release_date='2022-01-01 00:00:00',
                                hidden_from_students=False,
                                priority=0)
        self.conn.close()
        submitty_conn.close()
        os.environ['PGPASSWORD'] = ""

        if self.code == 'tutorial':
            client = docker.from_env()
            client.images.pull('submitty/tutorial:tutorial_18')
            client.images.pull('submitty/tutorial:database_client')

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
            l = [x.replace("\\n", "\n").strip() for x in line.split("|")]
            if(len(line) > 1):
                forum_data.append(l)
        return forum_data

    def make_sample_teams(self, gradeable):
        """
        arg: any team gradeable

        This function adds teams to the database and gradeable.

        return: A json object filled with team information
        """
        assert gradeable.team_assignment
        json_team_history = {}
        gradeable_teams_table = Table("gradeable_teams", self.metadata, autoload=True)
        teams_table = Table("teams", self.metadata, autoload=True)
        ucounter = self.conn.execute(select([func.count()]).select_from(gradeable_teams_table)).scalar()
        anon_team_ids = []
        for user in self.users:
            #the unique team id is made up of 5 digits, an underline, and the team creater's userid.
            #example: 00001_aphacker
            unique_team_id = str(ucounter).zfill(5)+"_"+user.get_detail(self.code, "id")
            #also need to create and save the anonymous team id
            anon_team_id = generate_random_user_id(15)
            if anon_team_id in anon_team_ids:
                anon_team_id = generate_random_user_id()
            reg_section = user.get_detail(self.code, "registration_section")
            if reg_section is None:
                continue
            # The teams are created based on the order of the users. As soon as the number of teamates
            # exceeds the max team size, then a new team will be created within the same registration section
            print("Adding team for " + unique_team_id + " in gradeable " + gradeable.id)
            # adding json data for team history
            teams_registration = select([gradeable_teams_table]).where(
                (gradeable_teams_table.c['registration_section'] == str(reg_section)) &
                (gradeable_teams_table.c['g_id'] == gradeable.id))
            res = self.conn.execute(teams_registration)
            added = False
            if res.rowcount != 0:
                # If the registration has a team already, join it
                for team_in_section in res:
                    members_in_team = select([teams_table]).where(
                        teams_table.c['team_id'] == team_in_section['team_id'])
                    res = self.conn.execute(members_in_team)
                    if res.rowcount < gradeable.max_team_size:
                        self.conn.execute(teams_table.insert(),
                                    team_id=team_in_section['team_id'],
                                    user_id=user.get_detail(self.code, "id"),
                                    state=1)
                        json_team_history[team_in_section['team_id']].append({"action": "admin_create",
                                                             "time": dateutils.write_submitty_date(gradeable.submission_open_date),
                                                             "admin_user": "instructor",
                                                             "added_user": user.get_detail(self.code, "id")})
                        added = True
            if not added:
                # if the team the user tried to join is full, make a new team
                self.conn.execute(gradeable_teams_table.insert(),
                             team_id=unique_team_id,
                             anon_id=anon_team_id,
                             g_id=gradeable.id,
                             registration_section=str(reg_section),
                             rotating_section=str(random.randint(1, self.rotating_sections)))
                self.conn.execute(teams_table.insert(),
                             team_id=unique_team_id,
                             user_id=user.get_detail(self.code, "id"),
                             state=1)
                json_team_history[unique_team_id] = [{"action": "admin_create",
                                                      "time": dateutils.write_submitty_date(gradeable.submission_open_date),
                                                      "admin_user": "instructor",
                                                      "first_user": user.get_detail(self.code, "id")}]
                ucounter += 1
            res.close()
            anon_team_ids.append(anon_team_id);
        return json_team_history

    def add_sample_forum_data(self):
        # set sample course to have forum enabled by default
        course_json_file = os.path.join(self.course_path, 'config', 'config.json')
        with open(course_json_file, 'r+') as open_file:
            course_json = json.load(open_file)
            course_json['course_details']['forum_enabled'] = True
            open_file.seek(0)
            open_file.truncate()
            json.dump(course_json, open_file, indent=2)

        f_data = (self.getForumDataFromFile('posts.txt'), self.getForumDataFromFile('threads.txt'), self.getForumDataFromFile('categories.txt'))
        forum_threads = Table("threads", self.metadata, autoload=True)
        forum_posts = Table("posts", self.metadata, autoload=True)
        forum_cat_list = Table("categories_list", self.metadata, autoload=True)
        forum_thread_cat = Table("thread_categories", self.metadata, autoload=True)

        for catData in f_data[2]:
            self.conn.execute(forum_cat_list.insert(), category_desc=catData[0], rank=catData[1], color=catData[2])

        for thread_id, threadData in enumerate(f_data[1], start = 1):
            self.conn.execute(forum_threads.insert(),
                              title=threadData[0],
                              created_by=threadData[1],
                              pinned=True if threadData[2] == "t" else False,
                              deleted=True if threadData[3] == "t" else False,
                              merged_thread_id=threadData[4],
                              merged_post_id=threadData[5],
                              is_visible=True if threadData[6] == "t" else False)
            self.conn.execute(forum_thread_cat.insert(), thread_id=thread_id, category_id=threadData[7])
        counter = 1
        for postData in f_data[0]:
            if(postData[10] != "f" and postData[10] != ""):
                # In posts.txt, if the 10th column is f or empty, then no attachment is added. If anything else is in the column, then it will be treated as the file name.
                attachment_path = os.path.join(self.course_path, "forum_attachments", str(postData[0]), str(counter))
                os.makedirs(attachment_path)
                os.system("chown -R submitty_php:sample_tas_www {}".format(os.path.join(self.course_path, "forum_attachments", str(postData[0]))))
                copyfile(os.path.join(SETUP_DATA_PATH, "forum", "attachments", postData[10]), os.path.join(attachment_path, postData[10]))
            counter += 1
            self.conn.execute(forum_posts.insert(),
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
                              has_attachment=True if postData[10] != "f" else False,
                              render_markdown=True if postData[11] == "t" else False)

    def add_sample_polls_data(self):
        # set sample course to have polls enabled by default
        course_json_file = os.path.join(self.course_path, 'config', 'config.json')
        with open(course_json_file, 'r+') as open_file:
            course_json = json.load(open_file)
            course_json['course_details']['polls_enabled'] = True
            open_file.seek(0)
            open_file.truncate()
            json.dump(course_json, open_file, indent=2)

        # load the sample polls from input file
        polls_data_path = os.path.join(SETUP_DATA_PATH, "polls", "polls_data.json")
        with open(polls_data_path, 'r') as polls_file:
            polls_data = json.load(polls_file)

        # set some values that depend on current time
        polls_data[0]["image_path"] = self.course_path + polls_data[0]["image_path"]
        polls_data[2]["release_date"] = f"{datetime.today().date()}"

        # add attached image
        image_dir = os.path.dirname(polls_data[0]["image_path"])
        if os.path.isdir(image_dir):
            shutil.rmtree(image_dir)

        os.makedirs(image_dir)
        os.system(f"chown -R submitty_php:sample_tas_www {image_dir}")
        copyfile(os.path.join(SETUP_DATA_PATH, "polls", "sea_animals.png"), polls_data[0]["image_path"])

        # add polls to DB
        polls_table = Table("polls", self.metadata, autoload=True)
        poll_options_table = Table("poll_options", self.metadata, autoload=True)
        poll_responses_table = Table("poll_responses", self.metadata, autoload=True)

        for poll in polls_data:
            self.conn.execute(polls_table.insert(),
                              name=poll["name"],
                              question=poll["question"],
                              status=poll["status"],
                              release_date=poll["release_date"],
                              image_path=poll["image_path"],
                              question_type=poll["question_type"],
                              release_histogram=poll["release_histogram"])
            for i in range(len(poll["responses"])):
                self.conn.execute(poll_options_table.insert(),
                                  order_id=i,
                                  poll_id=poll["id"],
                                  response=poll["responses"][i],
                                  correct=(i in poll["correct_responses"]))

        # generate responses to the polls
        poll_responses_data = []
        # poll1: for each self.users make a random number (0-5) of responses
        poll1_response_ids = list(range(len(polls_data[0]['responses'])))
        for user in self.users:
            random_responses = random.sample(poll1_response_ids, random.randint(0, len(polls_data[0]['responses'])))
            for response_id in random_responses:
                poll_responses_data.append({
                    "poll_id": polls_data[0]["id"],
                    "student_id": user.id,
                    "option_id": response_id+1
                })
        # poll2: take a large portion of self.users and make each submit one random response
        for user in self.users:
            if random.random() < 0.8:
                poll_responses_data.append({
                    "poll_id": polls_data[1]["id"],
                    "student_id": user.id,
                    "option_id": random.randint(1, len(polls_data[1]['responses'])) + len(polls_data[0]['responses']) # Must offset by number of options for poll 1
                })

        # add responses to DB
        for response in poll_responses_data:
            self.conn.execute(poll_responses_table.insert(),
                              poll_id=response["poll_id"],
                              student_id=response["student_id"],
                              option_id=response["option_id"])

    def add_sample_queue_data(self):
        # load the sample polls from input file
        queue_data_path = os.path.join(SETUP_DATA_PATH, "queue", "queue_data.json")
        with open(queue_data_path, 'r') as queue_file:
            queue_data = json.load(queue_file)

        # set sample course to have office hours queue enabled by default
        course_json_file = os.path.join(self.course_path, 'config', 'config.json')
        with open(course_json_file, 'r+') as open_file:
            course_json = json.load(open_file)
            course_json['course_details']['queue_enabled'] = True
            course_json['course_details']['queue_message'] = queue_data["queue_message"]
            course_json['course_details']['queue_announcement_message'] = queue_data["queue_announcement_message"]
            open_file.seek(0)
            open_file.truncate()
            json.dump(course_json, open_file, indent=2)

        # generate values that depend on current date and time
        # helped for the first time today, done --- LAB queue
        queue_data["queue_entries"][0]["time_in"] = datetime.now() - timedelta(minutes=25)
        queue_data["queue_entries"][0]["time_out"] = datetime.now() - timedelta(minutes=19)
        queue_data["queue_entries"][0]["time_help_start"] = datetime.now() - timedelta(minutes=24)
        # helped, done --- LAB queue
        queue_data["queue_entries"][1]["time_in"] = datetime.now() - timedelta(minutes=24)
        queue_data["queue_entries"][1]["time_out"] = datetime.now() - timedelta(minutes=15)
        queue_data["queue_entries"][1]["time_help_start"] = datetime.now() - timedelta(minutes=23)
        # removed by self --- LAB queue
        queue_data["queue_entries"][2]["time_in"] = datetime.now() - timedelta(minutes=22)
        queue_data["queue_entries"][2]["time_out"] = datetime.now() - timedelta(minutes=21)
        # being helped --- HW queue
        queue_data["queue_entries"][3]["time_in"] = datetime.now() - timedelta(minutes=23)
        queue_data["queue_entries"][3]["time_help_start"] = datetime.now() - timedelta(minutes=14)
        # waiting for help for second time today --- LAB queue
        queue_data["queue_entries"][4]["time_in"] = datetime.now() - timedelta(minutes=21)
        queue_data["queue_entries"][4]["last_time_in_queue"] = queue_data["queue_entries"][0]["time_in"]
        # paused --- HW queue
        queue_data["queue_entries"][5]["time_in"] = datetime.now() - timedelta(minutes=20)
        queue_data["queue_entries"][5]["time_paused_start"] = datetime.now() - timedelta(minutes=18)
        # wait for the first time --- HW queue
        queue_data["queue_entries"][6]["time_in"] = datetime.now() - timedelta(minutes=15)
        # waiting for help for second time this week --- LAB queue
        queue_data["queue_entries"][7]["time_in"] = datetime.now() - timedelta(minutes=10)
        queue_data["queue_entries"][7]["last_time_in_queue"] = datetime.now() - timedelta(days=1, minutes=30)

        queues_table = Table("queue_settings", self.metadata, autoload=True)
        queue_entries_table = Table("queue", self.metadata, autoload=True)

        # make two sample queues
        self.conn.execute(queues_table.insert(),
                          open=True,
                          code="Lab Help",
                          token="lab")
        self.conn.execute(queues_table.insert(),
                          open=True,
                          code="Homework Debugging",
                          token="hw_debug")

        # add, help, remove, pause, etc. students in the queue
        for queue_entry in queue_data["queue_entries"]:
            self.conn.execute(queue_entries_table.insert(),
                              current_state=queue_entry["current_state"],
                              removal_type=queue_entry["removal_type"],
                              queue_code=queue_entry["queue_code"],
                              user_id=queue_entry["user_id"],
                              name=queue_entry["name"],
                              time_in=queue_entry["time_in"],
                              time_out=queue_entry["time_out"],
                              added_by=queue_entry["added_by"],
                              help_started_by=queue_entry["help_started_by"],
                              removed_by=queue_entry["removed_by"],
                              contact_info=queue_entry["contact_info"],
                              last_time_in_queue=queue_entry["last_time_in_queue"],
                              time_help_start=queue_entry["time_help_start"],
                              paused=queue_entry["paused"],
                              time_paused=queue_entry["time_paused"],
                              time_paused_start=queue_entry["time_paused_start"])

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

        # Would be great if we could install directly to test_suite, but
        # currently the test uses "clean" which will blow away test_suite
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

        # for bucket,g_list in gradeables.items():
        for bucket in sorted(gradeables.keys()):
            g_list = gradeables[bucket]
            bucket_json = {"type": bucket, "count": len(g_list), "percent": 0.01*gradeables_percentages[bucket_no],
                           "ids" : []}

            g_list.sort(key=lambda x: x.id)

            # Manually total up the non-penalty non-extra-credit max scores, and decide which gradeables are 'released'
            for gradeable in g_list:
                use_ta_grading = gradeable.use_ta_grading
                g_type = gradeable.type
                components = gradeable.components
                g_id = gradeable.id
                max_auto = 0
                max_ta = 0

                print_grades = True if g_type != 0 or (gradeable.submission_open_date < NOW) else False
                release_grades = (gradeable.has_release_date is True) and (gradeable.grade_released_date < NOW)

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
            with open(os.path.join(customization_path, "customization_" + course_id + ".json"), 'w') as customization_file:
                customization_file.write("/*\n"
                                         "This JSON is based on the automatically generated customization for\n"
                                         "the development course \"{}\" as of {}.\n"
                                         "It is intended as a simple example, with additional documentation online.\n"
                                         "*/\n".format(course_id,NOW.strftime("%Y-%m-%d %H:%M:%S%z")))
            json.dump(gradeables_json_output,
                      open(os.path.join(customization_path, "customization_" + course_id + ".json"), 'a'),indent=2)
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
        self.lichen_sample_path = None
        self.plagiarized_user = {}
        self.title = ""
        self.instructions_url = ""
        self.overall_ta_instructions = ""
        self.peer_grading = False
        self.grade_by_registration = True
        self.grader_assignment_method = 1
        self.is_repository = False
        self.subdirectory = ""
        self.using_subdirectory = False
        self.vcs_partial_path = ""
        self.use_ta_grading = True
        self.late_days = 2
        self.precision = 0.5
        self.syllabus_bucket = "none (for practice only)"
        self.min_grading_group = 3
        self.grading_rotating = []
        self.submissions = []
        self.max_random_submissions = None
        self.max_individual_submissions = 3
        self.team_assignment = False
        self.max_team_size = 1
        self.has_due_date = True
        self.has_release_date = True
        self.allow_custom_marks = True
        self.plagiarism_submissions = []
        self.plagiarism_versions_per_user = 1
        self.annotated_pdf = False
        self.annotation_path = None
        self.annotations = []

        if 'gradeable_config' in gradeable:
            self.gradeable_config = gradeable['gradeable_config']
            self.type = 0

            if 'g_id' in gradeable:
                self.id = gradeable['g_id']
            else:
                self.id = gradeable['gradeable_config']

            if 'eg_max_random_submissions' in gradeable:
                self.max_random_submissions = int(gradeable['eg_max_random_submissions'])

            if 'eg_max_individual_submissions' in gradeable:
                self.max_individual_submissions = int(gradeable['eg_max_individual_submissions'])

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
            if 'eg_lichen_sample_path' in gradeable:
                # pdb.set_trace()
                self.lichen_sample_path = gradeable['eg_lichen_sample_path']
                if 'eg_plagiarized_users' in gradeable:
                    for user in gradeable['eg_plagiarized_users']:
                        temp = user.split(" - ")
                        self.plagiarized_user[temp[0]] = temp[1:]
                else:  # if we weren't given a list of plagiarized users, make one
                    self.plagiarism_submissions = os.listdir(self.lichen_sample_path)
                    random.shuffle(self.plagiarism_submissions)

                if 'eg_plagiarism_versions_per_user' in gradeable:
                    self.plagiarism_versions_per_user = gradeable['plagiarism_versions_per_user']

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

        # To make Rainbow Grades testing possible, need to seed random
        m = hashlib.md5()
        m.update(bytes(self.id, 'utf-8'))
        random.seed(int(m.hexdigest(), 16))

        if 'g_bucket' in gradeable:
            self.syllabus_bucket = gradeable['g_bucket']

        assert 0 <= self.type <= 2

        if 'g_title' in gradeable:
            self.title = gradeable['g_title']
        else:
            self.title = self.id.replace("_", " ").title()

        if 'g_grader_assignment_method' in gradeable:
            self.grade_by_registration = gradeable['g_grader_assignment_method'] == 1
            self.grader_assignment_method = gradeable['g_grader_assignment_method']

        if 'grading_rotating' in gradeable:
            self.grading_rotating = gradeable['grading_rotating']

        self.ta_view_date = dateutils.parse_datetime(gradeable['g_ta_view_start_date'])
        self.grade_start_date = dateutils.parse_datetime(gradeable['g_grade_start_date'])
        self.grade_due_date = dateutils.parse_datetime(gradeable['g_grade_due_date'])
        self.grade_released_date = dateutils.parse_datetime(gradeable['g_grade_released_date'])
        if self.type == 0:
            self.submission_open_date = dateutils.parse_datetime(gradeable['eg_submission_open_date'])
            self.submission_due_date = dateutils.parse_datetime(gradeable['eg_submission_due_date'])
            self.team_lock_date = dateutils.parse_datetime(gradeable['eg_submission_due_date'])
            self.grade_inquiry_start_date = dateutils.parse_datetime(gradeable['eg_grade_inquiry_start_date'])
            self.grade_inquiry_due_date = dateutils.parse_datetime(gradeable['eg_grade_inquiry_due_date'])
            self.student_view = True
            self.student_view_after_grades = False
            self.student_download = True
            self.student_submit = True
            if 'eg_is_repository' in gradeable:
                self.is_repository = gradeable['eg_is_repository'] is True
            if self.is_repository and 'eg_vcs_subdirectory' in gradeable:
                self.using_subdirectory = gradeable['eg_using_subdirectory'] is True
                self.subdirectory = gradeable['eg_vcs_subdirectory']
                self.vcs_partial_path = gradeable['eg_vcs_partial_path']
            if 'eg_peer_grading' in gradeable:
                self.peer_grading = gradeable['eg_peer_grading']
            if 'eg_use_ta_grading' in gradeable:
                self.use_ta_grading = gradeable['eg_use_ta_grading'] is True
            if 'eg_student_view' in gradeable:
                self.student_view = gradeable['eg_student_view'] is True
            if 'eg_student_download' in gradeable:
                self.student_download = gradeable['eg_student_download'] is True
            if 'eg_student_submit' in gradeable:
                self.student_submit = gradeable['eg_student_submit'] is True
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
            if 'eg_annotated_pdf' in gradeable:
                self.annotated_pdf = gradeable['eg_annotated_pdf'] is True
                self.annotation_path = os.path.join(MORE_EXAMPLES_DIR, self.gradeable_config, "annotation")
            if 'eg_bulk_test' in gradeable:
                self.student_view = gradeable['eg_bulk_test'] is True
                self.student_view_after_grades = gradeable['eg_bulk_test'] is True

            self.has_due_date = gradeable['eg_has_due_date'] if 'eg_has_due_date' in gradeable else True
            self.has_release_date = gradeable['eg_has_release_date'] if 'eg_has_release_date' in gradeable else True
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
            assert self.has_due_date is False or self.submission_open_date < self.submission_due_date
            assert self.has_due_date is False or self.submission_due_date < self.grade_start_date
            assert self.has_release_date is False or self.grade_released_date <= self.grade_inquiry_start_date
            assert self.grade_inquiry_start_date < self.grade_inquiry_due_date
            if self.gradeable_config is not None:
                if self.sample_path is not None:
                    if os.path.isfile(os.path.join(self.sample_path, "submissions.yml")):
                        self.submissions = load_data_yaml(os.path.join(self.sample_path, "submissions.yml"))
                    else:
                        self.submissions = os.listdir(self.sample_path)
                        self.submissions = list(filter(lambda x: not x.startswith("."), self.submissions))
                        # Ensure we're not sensitive to directory traversal order
                        self.submissions.sort()
                    if isinstance(self.submissions, list):
                        for elem in self.submissions:
                            if isinstance(elem, dict):
                                raise TypeError("Cannot have dictionary inside of list for submissions "
                                                "for {}".format(self.sample_path))
                if self.annotation_path is not None:
                    self.annotations = os.listdir(self.annotation_path)
                    self.annotations = list(filter(lambda x: not x.startswith("."), self.annotations))
                    # Ensure we're not sensitive to directory traversal order
                    self.annotations.sort()
        assert self.ta_view_date < self.grade_start_date
        assert self.grade_start_date < self.grade_due_date
        assert self.has_release_date is False or self.grade_due_date <= self.grade_released_date

        self.components = []
        for i in range(len(gradeable['components'])):
            component = gradeable['components'][i]
            if self.type >= 0:
                component['gc_ta_comment'] = generate_random_ta_note()
                component['gc_student_comment'] = generate_random_student_note()
                component['gc_page'] = 0
            if self.type == 1:
                component['gc_lower_clamp'] = 0
                component['gc_default'] = 0
                component['gc_max_value'] = 1
                component['gc_upper_clamp'] = 1
            if self.type != 2:
                component['gc_is_text'] = False
            i -= 1
            self.components.append(Component(component, i+1))

    def create(self, conn, gradeable_table, electronic_table, peer_assign, reg_table, component_table, mark_table):
        conn.execute(gradeable_table.insert(), g_id=self.id, g_title=self.title,
                     g_instructions_url=self.instructions_url,
                     g_overall_ta_instructions=self.overall_ta_instructions,
                     g_gradeable_type=self.type,
                     g_grader_assignment_method=self.grader_assignment_method,
                     g_ta_view_start_date=self.ta_view_date,
                     g_grade_start_date=self.grade_start_date,
                     g_grade_due_date=self.grade_due_date,
                     g_grade_released_date=self.grade_released_date,
                     g_syllabus_bucket=self.syllabus_bucket,
                     g_allow_custom_marks=self.allow_custom_marks,
                     g_min_grading_group=self.min_grading_group,
                     g_closed_date=None)

        for rotate in self.grading_rotating:
            conn.execute(reg_table.insert(), g_id=self.id, user_id=rotate['user_id'],
                         sections_rotating=rotate['section_rotating_id'])



        if self.peer_grading is True:
            with open(os.path.join(SETUP_DATA_PATH, 'random', 'graders.txt')) as graders, \
            open(os.path.join(SETUP_DATA_PATH, 'random', 'students.txt')) as students:
                graders = graders.read().strip().split()
                students = students.read().strip().split()
                length = len(graders)
                for i in range(length):
                    conn.execute(peer_assign.insert(), g_id=self.id, grader_id=graders[i], user_id=students[i])

        if self.type == 0:
            conn.execute(electronic_table.insert(), g_id=self.id,
                         eg_submission_open_date=self.submission_open_date,
                         eg_submission_due_date=self.submission_due_date,
                         eg_is_repository=self.is_repository, 
                         eg_using_subdirectory=self.using_subdirectory,
                         eg_vcs_subdirectory=self.subdirectory,
                         eg_vcs_partial_path=self.vcs_partial_path,
                         eg_team_assignment=self.team_assignment,
                         eg_max_team_size=self.max_team_size,
                         eg_team_lock_date=self.team_lock_date,
                         eg_use_ta_grading=self.use_ta_grading,
                         eg_student_view=self.student_view,
                         eg_student_view_after_grades=self.student_view_after_grades,
                         eg_student_download=self.student_download,
                         eg_student_submit=self.student_submit,
                         eg_config_path=self.config_path,
                         eg_late_days=self.late_days, eg_precision=self.precision, eg_peer_grading=self.peer_grading,
                         eg_grade_inquiry_start_date=self.grade_inquiry_start_date,
                         eg_grade_inquiry_due_date=self.grade_inquiry_due_date)

        for component in self.components:
            component.create(self.id, conn, component_table, mark_table)

    def create_form(self):
        form_json = OrderedDict()
        form_json['gradeable_id'] = self.id
        if self.type == 0:
            form_json['config_path'] = self.config_path
        if self.is_repository:
            form_json['date_due'] = dateutils.write_submitty_date(self.submission_due_date)
            form_json['upload_type'] = 'repository'
            form_json['vcs_partial_path'] = self.vcs_partial_path
            form_json['using_subdirectory'] = self.using_subdirectory
            form_json['subdirectory'] = self.subdirectory
            return form_json
        form_json['gradeable_title'] = self.title
        form_json['gradeable_type'] = self.get_gradeable_type_text()
        form_json['instructions_url'] = self.instructions_url
        form_json['ta_view_date'] = dateutils.write_submitty_date(self.ta_view_date)
        if self.type == 0:
            form_json['date_submit'] = dateutils.write_submitty_date(self.submission_open_date)
            form_json['date_due'] = dateutils.write_submitty_date(self.submission_due_date)
            form_json['grade_inquiry_start_date'] = dateutils.write_submitty_date(self.grade_inquiry_start_date)
            form_json['grade_inquiry_due_date'] = dateutils.write_submitty_date(self.grade_inquiry_due_date)
        form_json['date_grade'] = dateutils.write_submitty_date(self.grade_start_date)
        form_json['date_grade_due'] = dateutils.write_submitty_date(self.grade_due_date)
        form_json['date_released'] = dateutils.write_submitty_date(self.grade_released_date)

        if self.type == 0:
            form_json['section_type'] = self.get_submission_type()
            form_json['eg_late_days'] = self.late_days
            form_json['upload_type'] = self.get_upload_type()
            form_json['upload_repo'] = ''
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
        self.is_peer_component = False
        self.page = 0
        self.order = order
        self.marks = []

        if 'gc_ta_comment' in component:
            self.ta_comment = component['gc_ta_comment']
        if 'gc_is_peer' in component:
            self.is_peer_component = component['gc_is_peer']
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

        if 'marks' in component:
            for i in range(len(component['marks'])):
                mark = component['marks'][i]
                self.marks.append(Mark(mark, i))
        else:
            self.marks = generate_random_marks(self.default, self.max_value)

        self.key = None

    def create(self, g_id, conn, table, mark_table):
        ins = table.insert().values(g_id=g_id, gc_title=self.title, gc_ta_comment=self.ta_comment,
                                    gc_student_comment=self.student_comment,
                                    gc_lower_clamp=self.lower_clamp, gc_default=self.default, gc_max_value=self.max_value,
                                    gc_upper_clamp=self.upper_clamp, gc_is_text=self.is_text,
                                    gc_is_peer=self.is_peer_component, gc_order=self.order, gc_page=self.page)
        res = conn.execute(ins)
        self.key = res.inserted_primary_key[0]

        for mark in self.marks:
            mark.create(self.key, conn, mark_table)





