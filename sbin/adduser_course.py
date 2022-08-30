#!/usr/bin/env python3

"""
Use this script to add a user to courses. Any user added to a course
will be an instructor.
"""

import argparse
import json
from os import path
import sys
from sqlalchemy import create_engine, MetaData, Table, bindparam, and_

from submitty_utils import db_utils

CONFIG_PATH = path.join(path.dirname(path.realpath(__file__)), '..', 'config')
with open(path.join(CONFIG_PATH, 'database.json')) as open_file:
    DATABASE_DETAILS = json.load(open_file)
DATABASE_HOST = DATABASE_DETAILS['database_host']
DATABASE_PORT = DATABASE_DETAILS['database_port']
DATABASE_USER = DATABASE_DETAILS['database_user']
DATABASE_PASS = DATABASE_DETAILS['database_password']


def parse_args():
    parser = argparse.ArgumentParser(description='Adds a user to courses')

    parser.add_argument('user_id', help='user_id of the user to create')
    parser.add_argument('semester', help='semester of the course')
    parser.add_argument('course', help='title of the course')
    parser.add_argument('registration_section', nargs='?', default=None,
                        help='registration section that the user is added into')

    return parser.parse_args()


def main():
    args = parse_args()
    user_id = args.user_id
    semester = args.semester
    course = args.course
    registration_section = args.registration_section

    conn_str = db_utils.generate_connect_string(
        DATABASE_HOST,
        DATABASE_PORT,
        "submitty",
        DATABASE_USER,
        DATABASE_PASS,
    )

    engine = create_engine(conn_str)
    connection = engine.connect()
    metadata = MetaData(bind=engine)
    users_table = Table('users', metadata, autoload=True)
    select = users_table.select().where(users_table.c.user_id == bindparam('user_id'))
    user = connection.execute(select, user_id=user_id).fetchone()
    if user is None:
        print("User does not exist.", file=sys.stderr)
        return False

    courses_table = Table('courses', metadata, autoload=True)
    if registration_section and not registration_section.isdigit():
        registration_section = None
    select = courses_table.select().where(and_(
        courses_table.c.semester == bindparam('semester'),
        courses_table.c.course == bindparam('course')
    ))
    row = connection.execute(select, semester=semester, course=course).fetchone()
    # course does not exist, so just skip this argument
    if row is None:
        print("Course does not exist.", file=sys.stderr)
        return False

    courses_u_table = Table('courses_users', metadata, autoload=True)
    select = courses_u_table.select().where(and_(
        and_(
            courses_u_table.c.semester == bindparam('semester'),
            courses_u_table.c.course == bindparam('course')
        ),
        courses_u_table.c.user_id == bindparam('user_id')
    ))
    row = connection.execute(
        select,
        semester=semester,
        course=course,
        user_id=user_id
    ).fetchone()
    # does this user have a row in courses_users for this semester and course?
    if row is None:
        query = courses_u_table.insert()
        connection.execute(
            query,
            user_id=user_id,
            semester=semester,
            course=course,
            user_group=1,
            registration_section=registration_section
        )
    else:
        query = courses_u_table.update(values={
            courses_u_table.c.registration_section: bindparam('registration_section')
        }).where(courses_u_table.c.user_id == bindparam('b_user_id'))
        connection.execute(
            query,
            b_user_id=user_id,
            registration_section=registration_section
        )


if __name__ == '__main__':
    main()
