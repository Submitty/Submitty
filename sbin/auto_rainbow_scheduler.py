#!/usr/bin/env python3

"""
Script to be called by cron job, easily schedules auto rainbow grade jobs.

This script scans over all courses and reads the course config.json file to determine
if the auto_rainbow_grades bool is set to true.  If true the script will add a job to
the jobs daemon to automatically build or update rainbow grades for that course.
"""

import os
import json
from sqlalchemy import create_engine, Table, MetaData, select
import getpass


# Get path to current file directory
dir = os.path.dirname(__file__)


# Confirm that submitty_daemon user is running this script
users_config_file = dir + '/../config/submitty_users.json'

if not os.path.exists(users_config_file):
    raise Exception('Unable to locate users_submitty.json configuration file')

with open(users_config_file, 'r') as f:
    data = json.load(f)
    if data['daemon_user'] != getpass.getuser():
        raise Exception('ERROR: This script must be run by the submitty_daemon user')


# Collect path information from configuration file
config_file = dir + '/../config/submitty.json'

if not os.path.exists(config_file):
    raise Exception('Unable to locate submitty.json configuration file')

with open(config_file, 'r') as f:
    data = json.load(f)
    data_dir = data['submitty_data_dir']

# Get path to jobs daemon directory for later
daemon_directory = os.path.join(data_dir, 'daemon_job_queue')

courses_path = os.path.join(data_dir, 'courses')


def process_course(semester, course):
    """Decide if we should run rainbow grades for this specific course."""
    course_config_path = os.path.join(courses_path, semester,
                                      course, 'config', 'config.json')

    # Retrieve the auto_rainbow_grades bool from the course config.json
    with open(course_config_path, 'r') as f:
        data = json.load(f)
        auto_rainbow_grades = data['course_details']['auto_rainbow_grades']

    # If true then schedule a RunAutoRainbowGrades job
    if auto_rainbow_grades:

        # Setup jobs daemon json
        jobs_json = {
            'job': 'RunAutoRainbowGrades',
            'semester': semester,
            'course': course
        }

        # Prepare filename
        job_filename = 'auto_scheduled_rainbow_' + semester + '_' + course + '.json'

        # Drop job into jobs queue
        with open(os.path.join(daemon_directory, job_filename), 'w') as f:
            json.dump(jobs_json, f, indent=4)


def find_all_unarchived_courses():
    """Loop over all unarchived courses in the database."""
    # setup connection to database
    database_config_file = dir + '/../config/database.json'
    with open(database_config_file) as open_file:
        OPEN_JSON = json.load(open_file)
    DB_HOST = OPEN_JSON['database_host']
    DB_USER = OPEN_JSON['database_user']
    DB_PASSWORD = OPEN_JSON['database_password']
    db_name = "submitty"
    # If using a UNIX socket, have to specify a slightly different connection string
    if os.path.isdir(DB_HOST):
        conn_string = "postgresql://{}:{}@/{}?host={}".format(DB_USER, DB_PASSWORD,
                                                              db_name, DB_HOST)
    else:
        conn_string = "postgresql://{}:{}@{}/{}".format(DB_USER, DB_PASSWORD,
                                                        DB_HOST, db_name)
    engine = create_engine(conn_string)
    db = engine.connect()
    metadata = MetaData(bind=db)

    # find all courses that have status == 1 (unarchived)
    courses_table = Table('courses', metadata, autoload=True)
    result = db.execute(select([courses_table]).where(courses_table.c.status == 1))

    for row in result:
        semester = row.semester
        course = row.course
        process_course(semester, course)


# Do the work!
find_all_unarchived_courses()
