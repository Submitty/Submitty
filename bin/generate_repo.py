#!/usr/bin/env python3
"""
This script will generate the repository structure necessary
"""
import argparse
import pwd
import os
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

www_data = pwd.getpwnam('www-data')
vcs_course = os.path.join(VCS_FOLDER, args.semester, args.course)

if not os.path.isdir(vcs_course):
    os.makedirs(vcs_course, mode=0o750, exist_ok=True)

    for root, dirs, files in os.walk(vcs_course):
        for entry in dirs:
            os.chown(os.path.join(root, entry), www_data.pw_uid, www_data.pw_gid)

users_table = Table('courses_users', metadata, autoload=True)
select = users_table.select().where(users_table.c.semester == bindparam('semester')).where(users_table.c.course == bindparam('course'))
users = connection.execute(select, semester=args.semester, course=args.course)

if args.gradeable_id is not None:
    if not os.path.isdir(os.path.join(vcs_course, args.gradeable_id)):
        os.makedirs(os.path.join(vcs_course, args.gradeable_id), mode=0o750)
        os.chown(os.path.join(vcs_course, args.gradeable_id), www_data.pw_uid, www_data.pw_gid)

for user in users:
    if args.gradeable_id is not None:
        folder = os.path.join(vcs_course, args.gradeable_id, user)
    else:
        folder = os.path.join(vcs_course, user)

    if not os.path.isdir(folder):
        os.makedirs(folder)
        os.chdir(folder)
        os.system('git init --bare --shared')
        for root, dirs, files in os.walk(folder):
            for entry in files + dirs:
                os.chown(os.path.join(root, entry), www_data.pw_uid, www_data.pw_gid)
