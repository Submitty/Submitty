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
import json

from submitty_utils import dateutils

from sqlalchemy import create_engine, Table, MetaData, bindparam, select, join

CURRENT_PATH = os.path.dirname(os.path.realpath(__file__))
SETUP_DATA_PATH = os.path.join(CURRENT_PATH, "..", "data")

SUBMITTY_INSTALL_DIR = "/usr/local/submitty"
SUBMITTY_DATA_DIR = "/var/local/submitty"
SUBMITTY_REPOSITORY = os.path.join(SUBMITTY_INSTALL_DIR, "GIT_CHECKOUT/Submitty")
MORE_EXAMPLES_DIR = os.path.join(SUBMITTY_INSTALL_DIR, "more_autograding_examples")
TUTORIAL_DIR = os.path.join(SUBMITTY_INSTALL_DIR, "GIT_CHECKOUT/Tutorial", "examples")

DB_HOST = "localhost"

# To stress test the email database, change this variable to a greater number
EMAIL_NUM = 20000

with open(os.path.join(SUBMITTY_INSTALL_DIR,"config","database.json")) as database_config:
    database_config_json = json.load(database_config)
    DB_USER = database_config_json["database_user"]
    DB_PASS = database_config_json["database_password"]

def main():
    random.seed(8430571)

    submitty_engine = create_engine("postgresql://{}:{}@{}/submitty".format(DB_USER, DB_PASS, DB_HOST))
    submitty_conn = submitty_engine.connect()
    submitty_metadata = MetaData(bind=submitty_engine)
    email_table = Table('emails', submitty_metadata, autoload=True)

    courses = list(submitty_conn.execute("SELECT semester, course FROM courses"))
    users = {}
    courses_user_table = Table('courses_users', submitty_metadata, autoload=True)
    user_table = Table('users', submitty_metadata, autoload=True)

    for course in courses:
        users[course.course + ' ' + course.semester] = list(submitty_conn.execute("SELECT DISTINCT users.user_id, users.user_email FROM users INNER JOIN courses_users\
                                                ON courses_users.user_id = users.user_id\
                                                WHERE courses_users.semester = '{}'\
                                                AND courses_users.course = '{}'".format(course.semester, course.course)))
    users["superuser"] = list(submitty_conn.execute("SELECT DISTINCT user_id, user_email FROM users"))

    print(users)

    courses_subject = []
    courses_body = []
    superuser_subject = []
    superuser_body = []

    # These are not realistic emails as the email content does not check who owns the course and the body is often times nonsensical
    for i in range(EMAIL_NUM):
        course_selected = random.randint(0, len(courses))
        print ("Adding email entry #", i)
        # superuser email
        if course_selected == len(courses):
            emails = generateRandomSuperuserEmail(users["superuser"])
            for email in emails:
                submitty_conn.execute(email_table.insert(),
                                    user_id=email["user_id"],
                                    subject=email["subject"],
                                    body=email["body"],
                                    created=email["created"],
                                    email_address=email["email_address"],
                                    semester=email["semester"],
                                    course=email["course"])
        # course email
        else:
            course = courses[course_selected]
            emails = generateRandomCourseEmail(users[course.course + ' ' + course.semester], course)
            for email in emails:
                submitty_conn.execute(email_table.insert(),
                                    user_id=email["user_id"],
                                    subject=email["subject"],
                                    body=email["body"],
                                    created=email["created"],
                                    email_address=email["email_address"],
                                    semester=email["semester"],
                                    course=email["course"])

def generateRandomSuperuserEmail(recipients):
    with open(os.path.join(SETUP_DATA_PATH, 'random', 'SuperuserEmailBody.txt')) as body_file, \
        open(os.path.join(SETUP_DATA_PATH, 'random', 'SuperuserSubject.txt')) as subject_file:
        body = random.choice(body_file.read().strip().split('\n'))
        subject = random.choice(subject_file.read().strip().split('\n'))
    #print("Inserting {} emails".format(len(recipients)))
    now = dateutils.get_current_time()
    emails = []
    for recipient in recipients:
        emails.append(
            {
                "user_id": recipient.user_id,
                "subject": "[Submitty Admin Announcement]: " + subject,
                "body": body,
                "created": now,
                "email_address": recipient.user_email,
                "semester": None,
                "course": None
            }
        )
    return emails

def generateRandomCourseEmail(recipients, course):
    with open(os.path.join(SETUP_DATA_PATH, 'random', 'CourseEmailBody.txt')) as body_file, \
        open(os.path.join(SETUP_DATA_PATH, 'random', 'CourseSubject.txt')) as subject_file:
        body = random.choice(body_file.read().strip().split('\n'))
        subject = random.choice(subject_file.read().strip().split('\n'))
    #print("Inserting {} emails".format(len(recipients)))
    now = dateutils.get_current_time()
    emails = []
    for recipient in recipients:
        emails.append(
            {
                "user_id": recipient.user_id,
                "subject": subject,
                "body": body,
                "created": now,
                "email_address": recipient.user_email,
                "semester": course.semester,
                "course": course.course
            }
        )

    return emails

if __name__ == "__main__":
    main()
