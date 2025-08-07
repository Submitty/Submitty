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
from sqlalchemy import create_engine, MetaData, Table, bindparam, and_, insert, select, update, text

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


def apply_notification_defaults(connection, user_id, semester, course):
    metadata = MetaData()
    users_table = Table('users', metadata, autoload_with=connection.engine)
    query = select(users_table.c.notification_defaults).where(
        and_(
            users_table.c.user_id == user_id,
            users_table.c.notification_defaults.is_not(None)
        )
    )
    result = connection.execute(query).scalar()

    if not result:
        # No default notification settings are currently set for this user
        return

    # Parse the default course reference (term-course)
    default_term, default_course = result.split('-', 1)

    try:
        # Connect to the default course database to get notification settings
        default_db_name = f"submitty_{default_term}_{default_course}"
        default_conn_str = db_utils.generate_connect_string(
            DATABASE_HOST,
            DATABASE_PORT,
            default_db_name,
            DATABASE_USER,
            DATABASE_PASS
        )
        default_engine = create_engine(default_conn_str)
        default_connection = default_engine.connect()
        default_metadata = MetaData()

        # Get the user's notification settings from the default course
        notification_settings_table = Table(
            'notification_settings',
            default_metadata,
            autoload_with=default_engine
        )
        query = select(notification_settings_table).where(
            notification_settings_table.c.user_id == user_id
        )
        default_settings_row = default_connection.execute(query).mappings().fetchone()
        default_connection.close()

        if not default_settings_row:
            # User has a course falling back to default notification settings, so no settings exist in the default course
            return

        # Convert the row to a dictionary for easier handling
        settings_dict = {col: default_settings_row[col] for col in default_settings_row.keys() if col != 'user_id'}

        # Connect to the current course database to apply the default notification settings
        current_db_name = f"submitty_{semester}_{course}"
        current_conn_str = db_utils.generate_connect_string(
            DATABASE_HOST,
            DATABASE_PORT,
            current_db_name,
            DATABASE_USER,
            DATABASE_PASS
        )
        current_engine = create_engine(current_conn_str)
        current_connection = current_engine.connect()
        current_metadata = MetaData()

        # Check if notification_settings table exists in the current course
        notification_settings_table = Table(
            'notification_settings',
            current_metadata,
            autoload_with=current_engine
        )
        query = select(notification_settings_table.c.user_id).where(
            notification_settings_table.c.user_id == user_id
        )
        exists = current_connection.execute(query).first() is not None

        if exists:
            # Update existing settings using SQLAlchemy update
            stmt = update(notification_settings_table).where(
                notification_settings_table.c.user_id == user_id
            ).values(**settings_dict)
            current_connection.execute(stmt)
        else:
            # Insert new settings using SQLAlchemy insert
            stmt = insert(notification_settings_table).values(
                user_id=user_id,
                **settings_dict
            )
            current_connection.execute(stmt)

        current_connection.commit()
        current_connection.close()
    except Exception as e:
        print(f"Error applying notification defaults: {str(e)}", file=sys.stderr)


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
        apply_notification_defaults(connection, user_id, semester, course)
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
