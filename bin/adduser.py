#!/usr/bin/env python3

"""
Use this script to create new users and add them to courses. Any user added to a course
will be an instructor.
"""

import argparse
import subprocess
from sqlalchemy import create_engine, MetaData, Table, bindparam, and_

DATABASE_HOST = '__INSTALL__FILLIN__DATABASE_HOST__'
DATABASE_USER = '__INSTALL__FILLIN__DATABASE_USER__'
DATABASE_PASS = '__INSTALL__FILLIN__DATABASE_PASSWORD__'
AUTHENTICATION_METHOD = '__INSTALL__FILLIN__AUTHENTICATION_METHOD__'


def get_php_db_password(password):
    """
    Generates a password to be used within the site for database authentication. The password_hash
    function (http://php.net/manual/en/function.password-hash.php) generates us a nice secure
    password and takes care of things like salting and hashing.
    :param password:
    :return: password hash to be inserted into the DB for a user
    """
    proc = subprocess.Popen(
        ["php", "-r", "print(password_hash('{}', PASSWORD_DEFAULT));".format(password)],
        stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    (out, err) = proc.communicate()
    return out.decode('utf-8')


def get_input(question, default="", blank=False):
    add = "[{}] ".format(default) if default != "" or default is not None else ""
    add += " (Leave blank to set to null) " if blank else ""
    while True:
        user = input("{}: {}".format(question, add)).strip()
        if user == "":
            user = default
        if blank or (user != "" and user is not None):
            break
    return user


def parse_args():
    parser = argparse.ArgumentParser(description='Utility that given a user will create him into the'
                                                 'database and add them to the course if specified')

    parser.add_argument('user_id', help='user_id of the user to create')
    parser.add_argument('--course', metavar='help', action='append', nargs=3, help='[SEMESTER] [COURSE] [REGISTRATION_SECTION]')

    return parser.parse_args()


def main():
    args = parse_args()
    user_id = args.user_id

    engine = create_engine("postgresql://{}:{}@{}/submitty".format(DATABASE_USER, DATABASE_PASS, DATABASE_HOST))
    connection = engine.connect()
    metadata = MetaData(bind=engine)
    users_table = Table('users', metadata, autoload=True)
    select = users_table.select().where(users_table.c.user_id == bindparam('user_id'))
    user = connection.execute(select, user_id=user_id).fetchone()
    defaults = {'user_firstname': None,
                'user_preferred_firstname': None,
                'user_lastname': None,
                'user_email': None
                }
    if user is not None:
        print('User with this user-id already exists! Press enter on any question to use the current values of this user for that field.')
        defaults = user

    firstname = get_input('User firstname', defaults['user_firstname'])
    preferred = get_input('User preferred name', defaults['user_preferred_firstname'], True)
    lastname = get_input('User lastname', defaults['user_lastname'])
    email = get_input('User email', defaults['user_email'])

    update = {
        'user_firstname': firstname,
        'user_preferred_firstname': preferred,
        'user_lastname': lastname,
        'user_email': email
    }

    extra = ""
    if user is not None and AUTHENTICATION_METHOD == 'DatabaseAuthentication':
        extra = ' (Leave blank to use previous password)'
    while AUTHENTICATION_METHOD == 'DatabaseAuthentication':
        password = input('User password{}: '.format(extra))
        if password != '':
            update['user_password'] = get_php_db_password(password)
            break
        elif user is not None and password == '':
            break

    if user is not None:
        query = users_table.update(values=update).where(users_table.c.user_id == bindparam('b_user_id'))
        connection.execute(query, b_user_id=user_id)
    else:
        update['user_id'] = user_id
        query = users_table.insert()
        connection.execute(query, **update)

    if 'course' in args and args.course is not None and len(args.course) > 0:
        courses_table = Table('courses', metadata, autoload=True)
        for course in args.course:
            if not course[2].isdigit():
                course[2] = None
            select = courses_table.select().where(and_(courses_table.c.semester == bindparam('semester'),
                                                       courses_table.c.course == bindparam('course')))
            row = connection.execute(select, semester=course[0], course=course[1]).fetchone()
            # course does not exist, so just skip this argument
            if row is None:
                continue

            courses_u_table = Table('courses_users', metadata, autoload=True)
            select = courses_u_table.select().where(and_(and_(courses_u_table.c.semester == bindparam('semester'),
                                                              courses_u_table.c.course == bindparam('course')),
                                                         courses_u_table.c.user_id == bindparam('user_id')))
            row = connection.execute(select, semester=course[0], course=course[1], user_id=user_id).fetchone()
            # does this user have a row in courses_users for this semester and course?
            if row is None:
                query = courses_u_table.insert()
                connection.execute(query, user_id=user_id, semester=course[0], course=course[1], user_group=1,
                                   registration_section=course[2])
            else:
                query = courses_u_table.update(values={
                    courses_u_table.c.registration_section: bindparam('registration_section')
                }).where(courses_u_table.c.user_id == bindparam('user_id'))
                connection.execute(query, registration_section=course[2])


if __name__ == '__main__':
    main()
