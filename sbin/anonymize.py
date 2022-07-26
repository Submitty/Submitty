#!/usr/bin/env python3
"""
This script can be used to assign new anonymous ids to all the members of a course.
"""

from sqlalchemy import create_engine, Table, MetaData, bindparam
import string
import getpass
import random

# function taken from setup_sample_courses
def generate_random_user_id(length=15):
    return ''.join(random.choice(string.ascii_lowercase + string.ascii_uppercase +string.digits) for _ in range(length))


def main():
    COURSE = input("Course: ")
    SEMESTER = input("Semester: ")
    DB_HOST = input("Database Host: ")
    DB_USER = input("Database User: ")
    DB_PASS = getpass.getpass("Database Password: ")
    database = "submitty_"+SEMESTER+"_"+COURSE


    course_engine = create_engine("postgresql://{}:{}@{}/{}".format(DB_USER, DB_PASS, DB_HOST, database))
    conn = course_engine.connect()
    metadata = MetaData(bind=course_engine)

    users = Table("users", metadata, autoload=True)
    user_select = users.select()
    user_rows_obj = conn.execute(user_select)
    user_rows = []
    for row in user_rows_obj:
        user_rows.append(row._mapping)

    gradeable = Table("gradeable", metadata, autoload=True)
    g_select = gradeable.select()
    gradeable_rows = conn.execute(g_select)

    gradeable_anon = Table("gradeable_anon", metadata, autoload=True)
    for g_row in gradeable_rows:
        gradeable_id = g_row["g_id"]
        anon_ids = {}
        for row in user_rows:
            user_id = row["user_id"]
            anon = generate_random_user_id()
            while(anon in anon_ids):
                anon = generate_random_user_id()
            anon_ids[anon] = user_id
            new_info = {'anon_id':anon}
            update = gradeable_anon.update(values=new_info).where(gradeable_anon.c.user_id == bindparam('b_user_id'), gradeable_anon.c.g_id == bindparam('b_g_id'))
            result = conn.execute(update, b_user_id = user_id, b_g_id = gradeable_id)
            if result.rowcount == 0:
                new_row = {'user_id':user_id, 'g_id': gradeable_id, 'anon_id': anon}
                insert = gradeable_anon.insert().values(new_row)
                conn.execute(insert)
    conn.close()

if __name__ == "__main__":
    main()
