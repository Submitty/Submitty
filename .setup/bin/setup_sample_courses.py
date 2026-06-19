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
from datetime import datetime
import glob
import os
import os.path
import random

from sqlalchemy import create_engine, Table, MetaData, insert

from sample_courses import (
    args, SUBMITTY_INSTALL_DIR,
    DB_HOST, DB_PORT,
    DB_USER, DB_PASS,
    NOW, NO_GRADING,
    TEST_ONLY_GRADING,
    SUBMITTY_DATA_DIR
)

from sample_courses.utils import (
    load_data_yaml,
    get_php_db_password
)
from sample_courses.models import generate_random_users
from sample_courses.utils.create_or_generate import create_group

from sample_courses.models import User
from sample_courses.models.course import Course


def main() -> None:
    """
    Main program execution. This gets us our commandline arguments, reads in the data files,
    and then sets us up to run the create methods for the users and courses.
    """

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

    courses: dict = {}  # dict[str, Course]
    users: dict = {}  # dict[str, User]
    for course_file in sorted(glob.iglob(os.path.join(args.courses_path, '*.yml'))):
        # only create the plagiarism course if we have a local LichenTestData repo
        if os.path.basename(course_file) == "plagiarism.yml" and not os.path.isdir(
                os.path.join(SUBMITTY_INSTALL_DIR, "GIT_CHECKOUT", "LichenTestData")):
            continue

        course_json = load_data_yaml(course_file)

        if len(use_courses) == 0 or course_json['code'] in use_courses:
            course = Course(course_json)
            courses[course.code] = course

    git_checkout_dir = os.path.join(SUBMITTY_INSTALL_DIR, "GIT_CHECKOUT")
    for course_json in [
        load_data_yaml(course_yml)
        for course_yml in [
            os.path.join(git_checkout_dir, repo, "course_config.yml")
            for repo in sorted(os.listdir(git_checkout_dir))
        ]
        if os.path.isfile(course_yml)
    ]:
        if len(use_courses) == 0 or course_json["code"] in use_courses:
            course = Course(course_json)
            courses[course.code] = course

    create_group("submitty_course_builders")

    for user_file in sorted(glob.iglob(os.path.join(args.users_path, '*.yml'))):
        user = User(load_data_yaml(user_file))
        if user.id in ['submitty_php', 'submitty_daemon', 'submitty_cgi',
                       'submitty_dbuser', 'vagrant', 'postgres'] or \
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
            course.no_rotating_students + course.no_registration_students

        extra_students = max(tmp, extra_students)
    extra_students = generate_random_users(extra_students, users)

    submitty_engine = create_engine("postgresql:///submitty?host={}&port={}&user={}&password={}"
                                    .format(DB_HOST, DB_PORT, DB_USER, DB_PASS))
    submitty_conn = submitty_engine.connect()
    submitty_metadata = MetaData()
    user_table = Table('users', submitty_metadata, autoload_with=submitty_engine)
    for user_id in sorted(users.keys()):
        user = users[user_id]
        submitty_conn.execute(insert(user_table).values(
            user_id=user.id,
            user_numeric_id=user.numeric_id,
            user_password=get_php_db_password(user.password),
            user_givenname=user.givenname,
            user_preferred_givenname=user.preferred_givenname,
            user_familyname=user.familyname,
            user_preferred_familyname=user.preferred_familyname,
            user_email=user.email,
            user_access_level=user.access_level,
            user_pronouns=user.pronouns,
            last_updated=NOW.strftime("%Y-%m-%d %H:%M:%S%z")
        ))
    submitty_conn.commit()

    for user in extra_students:
        submitty_conn.execute(insert(user_table).values(
            user_id=user.id,
            user_numeric_id=user.numeric_id,
            user_password=get_php_db_password(user.password),
            user_givenname=user.givenname,
            user_preferred_givenname=user.preferred_givenname,
            user_familyname=user.familyname,
            user_preferred_familyname=user.preferred_familyname,
            user_email=user.email,
            user_pronouns=user.pronouns,
            last_updated=NOW.strftime("%Y-%m-%d %H:%M:%S%z")
        ))
    submitty_conn.commit()

    # INSERT term into terms table, based on today's date.
    today = datetime.today()
    year = str(today.year)
    if today.month < 7:
        term_id = "s" + year[-2:]
        term_name = "Spring " + year
        term_start = "01/02/" + year
        term_end = "06/30/" + year
    else:
        term_id = "f" + year[-2:]
        term_name = "Fall " + year
        term_start = "07/01/" + year
        term_end = "12/23/" + year

    terms_table = Table("terms", submitty_metadata, autoload_with=submitty_engine)
    submitty_conn.execute(insert(terms_table).values(
        term_id=term_id,
        name=term_name,
        start_date=term_start,
        end_date=term_end
    ))
    submitty_conn.commit()
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
            students[key].courses[course.code] = {"registration_section": reg_section,
                                                  "rotating_section": rot_section}
            course.users.append(students[key])
            key += 1

        for i in range(course.no_rotating_students):
            reg_section = (i % course.registration_sections) + 1
            students[key].courses[course.code] = {"registration_section": reg_section,
                                                  "rotating_section": None}
            course.users.append(students[key])
            key += 1

        for i in range(course.no_registration_students):
            rot_section = (i % course.rotating_sections) + 1
            students[key].courses[course.code] = {"registration_section": None,
                                                  "rotating_section": rot_section}
            course.users.append(students[key])
            key += 1

        for _ in range(course.unregistered_students):
            students[key].courses[course.code] = {"registration_section": None,
                                                  "rotating_section": None}
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
    regrade_extras = ""
    if TEST_ONLY_GRADING:
        regrade_extras = "*/testing/"
    if (not NO_GRADING) or TEST_ONLY_GRADING:
        # queue up all of the newly created submissions to grade!
        os.system(f"{SUBMITTY_INSTALL_DIR}/bin/regrade.py --no_input {SUBMITTY_DATA_DIR}/courses/{regrade_extras}")


if __name__ == "__main__":
    main()
