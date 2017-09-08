#!/usr/bin/env python3
"""
This script will generate the repositories for a specified course and semester
for each student that currently does not have a repository. You can either make
the repositories at a per course level (for a repo that would carry through
all gradeables for example) or on a per gradeable level.
"""

import argparse
import os
import shutil
from sqlalchemy import create_engine, MetaData, Table, bindparam

DATABASE_HOST = '__INSTALL__FILLIN__DATABASE_HOST__'
DATABASE_USER = '__INSTALL__FILLIN__DATABASE_USER__'
DATABASE_PASS = '__INSTALL__FILLIN__DATABASE_PASSWORD__'

VCS_FOLDER = os.path.join('__INSTALL__FILLIN__SUBMITTY_DATA_DIR__', 'vcs')

parser = argparse.ArgumentParser(description="Generate git repositories for a specific course and homework")
parser.add_argument("semester", help="semester")
parser.add_argument("course", help="course code")
parser.add_argument("gradeable_id", help="gradeable id", nargs='?')
args = parser.parse_args()

db = 'submitty'
if os.path.isdir(DATABASE_HOST):
    conn_string = "postgresql://{}:{}@/{}?host={}".format(DATABASE_USER, DATABASE_PASS, db, DATABASE_HOST)
else:
    conn_string = "postgresql://{}:{}@{}/{}".format(DATABASE_USER, DATABASE_PASS, DATABASE_HOST, db)

engine = create_engine(conn_string)
connection = engine.connect()
metadata = MetaData(bind=engine)

courses_table = Table('courses', metadata, autoload=True)
select = courses_table.select().where(courses_table.c.semester == bindparam('semester')).where(courses_table.c.course == bindparam('course'))
course = connection.execute(select, semester=args.semester, course=args.course).fetchone()

if course is None:
    raise SystemExit("Semester '{}' and Course '{}' not found".format(args.semester, args.course))

vcs_course = os.path.join(VCS_FOLDER, args.semester, args.course)

if not os.path.isdir(vcs_course):
    os.makedirs(vcs_course, mode=0o770, exist_ok=True)
    shutil.chown(VCS_FOLDER, group='www-data')
    for root, dirs, files in os.walk(VCS_FOLDER):
        for entry in dirs:
            shutil.chown(os.path.join(root, entry), group='www-data')

users_table = Table('courses_users', metadata, autoload=True)
select = users_table.select().where(users_table.c.semester == bindparam('semester')).where(users_table.c.course == bindparam('course')).order_by(users_table.c.user_id)
users = connection.execute(select, semester=args.semester, course=args.course)

if args.gradeable_id is not None:
    if not os.path.isdir(os.path.join(vcs_course, args.gradeable_id)):
        os.makedirs(os.path.join(vcs_course, args.gradeable_id), mode=0o770)
        shutil.chown(os.path.join(vcs_course, args.gradeable_id), group='www-data')

for user in users:
    if args.gradeable_id is not None:
        folder = os.path.join(vcs_course, args.gradeable_id, user.user_id)
    else:
        folder = os.path.join(vcs_course, user.user_id)

    if not os.path.isdir(folder):
        os.makedirs(folder, mode=0o770)
        os.chdir(folder)
        os.system('git init --bare --shared')
        for root, dirs, files in os.walk(folder):
            for entry in files + dirs:
                shutil.chown(os.path.join(root, entry), group='www-data')
