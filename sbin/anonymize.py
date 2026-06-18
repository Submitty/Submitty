#!/usr/bin/env python3
"""
This script can be used to assign gradeable-specific anonymous ids
to the members of a course that don't already have anon ids.
"""

from sqlalchemy import create_engine, Table, MetaData, bindparam, insert, select, update
import string
import random
import json
import sys
from os import path

from submitty_utils import db_utils


# function taken from setup_sample_courses
def generate_random_user_id(length=15):
    pick_from = string.ascii_lowercase + string.ascii_uppercase + string.digits
    return ''.join(random.choice(pick_from) for _ in range(length))


def main():
    max_retries = 3
    CONFIG_PATH = path.join(path.dirname(path.realpath(__file__)), '..', 'config')
    with open(path.join(CONFIG_PATH, 'database.json')) as open_file:
        DATABASE_DETAILS = json.load(open_file)
    # COURSE = input("Course: ")
    # SEMESTER = input("Semester: ")
    DATABASE_HOST = DATABASE_DETAILS['database_host']
    DATABASE_PORT = DATABASE_DETAILS['database_port']
    DB_USER = DATABASE_DETAILS['database_user']
    DB_PASS = DATABASE_DETAILS['database_password']
    DB_COURSE_USER = DATABASE_DETAILS['database_course_user']
    DB_COURSE_PASS = DATABASE_DETAILS['database_course_password']

    conn_str = db_utils.generate_connect_string(
        DATABASE_HOST,
        DATABASE_PORT,
        "submitty",
        DB_USER,
        DB_PASS,
    )

    for i in range(max_retries):
        try:
            db_engine = create_engine(conn_str)
            db_conn = db_engine.connect()
            db_metadata = MetaData()
            break
        except Exception as e:
            if i == (max_retries - 1):
                print(e)
                print(f"Attempted {max_retries} times but failed to "
                      "establish a connection with main Submitty database.\n")
                sys.exit()
    courses = Table("courses", db_metadata, autoload_with=db_engine)
    courses_select = select(courses)
    courses_rows = db_conn.execute(courses_select)
    num_rows = 0
    for course_row in courses_rows.mappings():
        temp_num_rows = num_rows
        print(f"Course: {course_row['course']}\nSemester: {course_row['term']}")
        DB_NAME = f"submitty_{course_row['term']}_{course_row['course']}"
        course_conn_str = db_utils.generate_connect_string(
            DATABASE_HOST,
            DATABASE_PORT,
            DB_NAME,
            DB_COURSE_USER,
            DB_COURSE_PASS,
        )

        connected = False
        for i in range(max_retries):
            try:
                course_engine = create_engine(course_conn_str)
                conn = course_engine.connect()
                metadata = MetaData()
                connected = True
                break
            except Exception as e:
                if i == (max_retries - 1):
                    print(e)
        if not connected:
            print(f"Attempted {max_retries} times but failed to "
                  f"establish a connection with database '{DB_NAME}'.\n")
            continue

        users = Table("users", metadata, autoload_with=course_engine)
        user_select = select(users)
        user_rows_obj = conn.execute(user_select)
        user_rows = user_rows_obj.mappings().all()

        gradeable = Table("gradeable", metadata, autoload_with=course_engine)
        g_select = select(gradeable)
        gradeable_rows = conn.execute(g_select)

        gradeable_anon = Table("gradeable_anon", metadata, autoload_with=course_engine)
        print("Performing anonymization...")
        for g_row in gradeable_rows.mappings():
            gradeable_id = g_row["g_id"]
            select_query = select(gradeable_anon).where(
                gradeable_anon.c.g_id == bindparam('gradeable_id')
            )
            existing_rows = conn.execute(select_query, {"gradeable_id": gradeable_id})
            existing_user_ids = []
            anon_ids = []
            users_to_update = []
            for row in existing_rows.mappings():
                existing_user_ids.append(row['user_id'])
                anon_ids.append(row['anon_id'])
                if row['anon_id'] == '':
                    users_to_update.append(row['user_id'])
                    user_id = row['user_id']
                    print(f"  Need to update: {user_id} for gradeable: {gradeable_id}")
            for row in user_rows:
                user_id = row["user_id"]
                if (user_id not in existing_user_ids) or (user_id in users_to_update):
                    anon = generate_random_user_id()
                    while (anon in anon_ids):
                        anon = generate_random_user_id()
                    anon_ids.append(anon)
                    if user_id not in existing_user_ids:
                        new_row = {'user_id': user_id, 'g_id': gradeable_id, 'anon_id': anon}
                        insert_query = insert(gradeable_anon).values(new_row)
                        conn.execute(insert_query)
                        conn.commit()
                        print(f"  Insert user: {user_id} for gradeable: {gradeable_id}")
                        num_rows += 1
                    elif user_id in users_to_update:
                        new_info = {'anon_id': anon}
                        update_query = update(gradeable_anon).values(new_info).where(
                            gradeable_anon.c.user_id == bindparam('b_user_id'),
                            gradeable_anon.c.g_id == bindparam('b_g_id')
                        )
                        print(f"  Update: {user_id} for gradeable: {gradeable_id}")
                        conn.execute(update_query, {"b_user_id": user_id, "b_g_id": gradeable_id})
                        conn.commit()
                        num_rows += 1
        conn.close()
        print(f"Rows created/updated: {num_rows-temp_num_rows}\n")
        print("...done\n")
    db_conn.close()
    print(f"Total rows created/updated: {num_rows}\n")


if __name__ == "__main__":
    main()
