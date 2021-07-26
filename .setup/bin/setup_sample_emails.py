#!/usr/bin/env python3
"""
Setup script that reads in the users.yml and courses.yml files in the ../data directory and then
creates sample emails entry in the database.

Usage: ./setup_sample_emails.py
"""

import os
import pwd
import random
from datetime import datetime
import glob

from submitty_utils import dateutils

from ruamel.yaml import YAML
from sqlalchemy import create_engine, Table, MetaData, bindparam, select, join

yaml = YAML(typ='safe')

CURRENT_PATH = os.path.dirname(os.path.realpath(__file__))
SETUP_DATA_PATH = os.path.join(CURRENT_PATH, "..", "data")

SUBMITTY_INSTALL_DIR = "/usr/local/submitty"
SUBMITTY_DATA_DIR = "/var/local/submitty"
SUBMITTY_REPOSITORY = os.path.join(SUBMITTY_INSTALL_DIR, "GIT_CHECKOUT/Submitty")
MORE_EXAMPLES_DIR = os.path.join(SUBMITTY_INSTALL_DIR, "more_autograding_examples")
TUTORIAL_DIR = os.path.join(SUBMITTY_INSTALL_DIR, "GIT_CHECKOUT/Tutorial", "examples")

DB_HOST = "localhost"

EMAIL_NUM = 20000

with open(os.path.join(SUBMITTY_INSTALL_DIR,"config","database.json")) as database_config:
    database_config_json = json.load(database_config)
    DB_USER = database_config_json["database_user"]
    DB_PASS = database_config_json["database_password"]

NOW = dateutils.get_current_time()

def main():
    users = {}  # dict[str, User]
    courses = {}  # dict[str, Course]

    random.seed(8431571)

    submitty_engine = create_engine("postgresql://{}:{}@{}/submitty".format(DB_USER, DB_PASS, DB_HOST))
    submitty_conn = submitty_engine.connect()
    submitty_metadata = MetaData(bind=submitty_engine)
    user_table = Table('users', submitty_metadata, autoload=True)

    courses = submitty_conn.execute("SELECT semester, course FROM courses")
    courses_subject = []
    courses_body = []
    superuser_subject = []
    superuser_body = []

    # These are not realistic emails as the email content does not check who owns the course and the body is often times nonsensical
    for i in range(EMAIL_NUM):
        course_selected = random.randint(0, len(courses))
        # superuser email
        if course_selected is len(courses):

        # course email
        else:
            course = courses[course_selected]
            

def generateRandomSuperuserEmail(recipients):
    with open(os.path.join(SETUP_DATA_PATH, 'random', 'SuperuserEmailBody.txt')) as body_file, \
        open(os.path.join(SETUP_DATA_PATH, 'random', 'SuperuserSubject.txt')) as subject_file:
        body = random.choice(body_file.read().strip().split())
        subject = random.choice(subject_file.read().strip().split())

    emails = []
    for recipient in recipients:
        emails.append(
            {
                "user_id": recipient.id,
                "subject": "[Submitty Admin Announcement]: " + subject,
                "body": body,
                "created": NOW,
                "email_address": recipient.email,
                "semester": None,
                "course": None
            }
        )
    return emails

def generateRandomCourseEmail(recipients, course):
    with open(os.path.join(SETUP_DATA_PATH, 'random', 'CourseEmailBody.txt')) as body_file, \
        open(os.path.join(SETUP_DATA_PATH, 'random', 'CourseSubject.txt')) as subject_file:
        body = random.choice(body_file.read().strip().split())
        subject = random.choice(subject_file.read().strip().split())

    emails = []
    for recipient in recipients:
        emails.append(
            {
                "user_id": recipient.id,
                "subject": subject,
                "body": body,
                "created": NOW,
                "email_address": recipient.email,
                "semester": course.semester,
                "course": course.course
            }
        )
    return emails
