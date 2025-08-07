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
    """
    Apply default notification settings for a user when they are added to a course.

    This function checks if the user has default notification settings defined,
    and if so, applies them to the newly added course.
    """
    # First, check if the user has default notification settings
    query = text("""
        SELECT notification_defaults
        FROM users
        WHERE user_id = :user_id AND notification_defaults IS NOT NULL
    """)
    result = connection.execute(query, {"user_id": user_id}).fetchone()
    if not result:
        return

    # Parse the default course reference (term-course)
    default_course_ref = result[0]
    try:
        default_term, default_course = default_course_ref.split('-', 1)
    except ValueError:
        print(f"Invalid default course reference: {default_course_ref}", file=sys.stderr)
        return

    # Connect to the default course database to get notification settings
    default_db_name = f"submitty_{default_term}_{default_course}"
    try:
        default_conn_str = db_utils.generate_connect_string(
            DATABASE_HOST,
            DATABASE_PORT,
            default_db_name,
            DATABASE_USER,
            DATABASE_PASS
        )
        default_engine = create_engine(default_conn_str)
        default_connection = default_engine.connect()

        # Get the user's notification settings from the default course
        query = text("""
            SELECT *
            FROM notification_settings
            WHERE user_id = :user_id
        """)
        default_settings = default_connection.execute(query, {"user_id": user_id}).mappings().fetchone()
        default_connection.close()

        if not default_settings:
            print(f"No notification settings found in default course for user {user_id}", file=sys.stderr)
            return

        # Convert the row to a dictionary for easier handling
        settings_dict = {col: default_settings[col] for col in default_settings if col != 'user_id'}

        # Check if the user already has notification settings in the new course
        query = text("""
            SELECT COUNT(*)
            FROM notification_settings
            WHERE user_id = :user_id
        """)
        exists = connection.execute(query, {"user_id": user_id}).scalar()

        if exists:
            # Update existing settings
            set_clause = ", ".join([f"{col} = :{col}" for col in settings_dict.keys()])
            query = text(f"""
                UPDATE notification_settings
                SET {set_clause}
                WHERE user_id = :user_id
            """)
        else:
            # Insert new settings
            columns_str = "user_id, " + ", ".join(settings_dict.keys())
            values_str = ":user_id, " + ", ".join([f":{col}" for col in settings_dict.keys()])
            query = text(f"""
                INSERT INTO notification_settings ({columns_str})
                VALUES ({values_str})
            """)

        # Execute the query with all parameters
        params = {"user_id": user_id, **settings_dict}
        connection.execute(query, params)
        connection.commit()

        print(f"Applied default notification settings for user {user_id} in course {semester}-{course}")
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

        # Apply default notification settings for newly added users
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
