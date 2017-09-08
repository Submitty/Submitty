#!usr/bin/env python3
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
    COURSE = raw_input("Course: ")
    SEMESTER = raw_input("Semester: ")
    DB_HOST = raw_input("Database Host: ")
    DB_USER = raw_input("Database User: ")
    DB_PASS = getpass.getpass("Database Password: ")
    database = "submitty_"+SEMESTER+"_"+COURSE


    course_engine = create_engine("postgresql://{}:{}@{}/{}".format(DB_USER, DB_PASS, DB_HOST, database))
    conn = course_engine.connect()
    metadata = MetaData(bind=course_engine)

    users = Table("users", metadata, autoload=True)
    select = users.select()
    rows = conn.execute(select)

    anon_ids = {}

    for row in rows:
        user = row["user_id"]
        anon = generate_random_user_id()
        while(anon in anon_ids) :
            anon = generate_random_user_id()
        anon_ids[anon] = user;
        new_info = {'anon_id':anon}
        update = users.update(values=new_info).where(users.c.user_id == bindparam('b_user_id'))
        conn.execute(update, b_user_id = user)
        
    conn.close()

if __name__ == "__main__":
    main()
