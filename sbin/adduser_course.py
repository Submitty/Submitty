#!/usr/bin/env python3

"""
Use this script to add a user to courses. By default, the user
will be an instructor but you can specify a different group
with an argument.
"""

import argparse
import json
from os import path
import sys
from sqlalchemy import create_engine, MetaData, Table, bindparam, and_, insert, select, update

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
    parser.add_argument('--user_group', default=1,
                        help='group the user belongs to 1:Instructor 2:Full Access Grader 3:Limited Access Grader 4:Student')

    return parser.parse_args()


def main():
    args = parse_args()
    user_id = args.user_id
    semester = args.semester
    course = args.course
    user_group = args.user_group
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
    metadata = MetaData()
    users_table = Table('users', metadata, autoload_with=engine)
    select_query = select(users_table).where(users_table.c.user_id == bindparam('user_id'))
    user = connection.execute(select_query, {"user_id": user_id}).fetchone()
    if user is None:
        print("User does not exist.", file=sys.stderr)
        return False

    courses_table = Table('courses', metadata, autoload_with=engine)
    if registration_section and not registration_section.isdigit():
        registration_section = None
    select_query = select(courses_table).where(and_(
        courses_table.c.term == bindparam('term'),
        courses_table.c.course == bindparam('course')
    ))
    row = connection.execute(select_query, {"term": semester, "course": course}).fetchone()
    # course does not exist, so just skip this argument
    if row is None:
        print("Course does not exist.", file=sys.stderr)
        return False

    courses_u_table = Table('courses_users', metadata, autoload_with=engine)
    select_query = select(courses_u_table).where(and_(
        and_(
            courses_u_table.c.term == bindparam('term'),
            courses_u_table.c.course == bindparam('course')
        ),
        courses_u_table.c.user_id == bindparam('user_id')
    ))
    row = connection.execute(
        select_query,
        {"term": semester, "course": course, "user_id": user_id}
    ).fetchone()
    # does this user have a row in courses_users for this semester and course?
    if row is None:
        connection.execute(
            insert(courses_u_table).values(
                user_id=user_id,
                term=semester,
                course=course,
                user_group=user_group,
                registration_section=registration_section
            )
        )
    else:
        query = update(courses_u_table).where(
            courses_u_table.c.user_id == bindparam("b_user_id")
        ).values(registration_section=bindparam("registration_section"))

        connection.execute(
            query,
            {"b_user_id": user_id, "registration_section": registration_section}
        )
    connection.commit()


if __name__ == '__main__':
    main()
