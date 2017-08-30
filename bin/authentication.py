#!/usr/bin/env python3

import json
import os
import uuid

import requests
from sqlalchemy import create_engine, MetaData, Table, bindparam
from submitty_utils.user import get_php_db_password

CGI_URL = '__INSTALL__FILLIN__CGI_URL__'

DATABASE_HOST = '__INSTALL__FILLIN__DATABASE_HOST__'
DATABASE_USER = '__INSTALL__FILLIN__DATABASE_USER__'
DATABASE_PASS = '__INSTALL__FILLIN__DATABASE_PASSWORD__'

AUTHENTICATION_METHOD = '__INSTALL__FILLIN__AUTHENTICATION_METHOD__'
DATA_DIR = '__INSTALL__FILLIN__SUBMITTY_DATA_DIR__'


def check_password(environ, user, password):
    """

    :param environ:  Dictionary that contains the apache environment variables.
                     There's a lot of overlap of what you get from this dictionary
                     and http://php.net/manual/en/reserved.variables.server.php
    :param user:     String containing the username passed to the apache authentication box
    :param password: String containing the password passed to the apache authentication box
    :return:         Boolean for whether user was properly authenticated (True) or not (False)
    """
    # REQUEST_URI
    params = list(filter(lambda x: len(x) > 0, environ['REQUEST_URI'].split("/")))
    if len(params) == 5:
        vcs, semester, course, user_id = params[:5]
    elif len(params) == 6:
        vcs, semester, course, gradeable, user_id = params[:6]
    else:
        return None

    engine = connection = metadata = None
    authenticated = False

    if AUTHENTICATION_METHOD == 'PamAuthentication':
        authenticated = check_pam(user, password)
        # print(authenticated)
    elif AUTHENTICATION_METHOD == 'DatabaseAuthentication':
        engine, connection, metadata = open_database()
        authenticated = check_database(user, password, connection, metadata)

    if authenticated is not True or user == user_id:
        close_database(engine, connection)
        return authenticated

    if engine is None:
        engine, connection, metadata = open_database()

    users_table = Table('courses_users', metadata, autoload=True)
    select = users_table.select().where(users_table.c.user_id == bindparam('user_id'))\
        .where(users_table.c.semester == bindparam('semester')).where(users_table.c.course == bindparam('course'))
    course_user = connection.execute(select, user_id=user, semester=semester, course=course).fetchone()
    if course_user is None:
        authenticated = None
    else:
        if course_user['user_group'] <= 2:
            authenticated = True
        else:
            authenticated = False

    close_database(engine, connection)

    return authenticated


def check_pam(username, password):
    """

    :param username:
    :param password:
    :return: boolean if PAM succeeded (True) or failed (False) to authenticate the username/password
    """
    authenticated = False
    filename = uuid.uuid4().hex
    filepath = os.path.join('/tmp', filename)
    with os.fdopen(os.open(filepath, os.O_CREAT | os.O_RDWR, 0o640), 'w') as fd:
        json.dump({'username': username, 'password': password}, fd)

    # noinspection PyBroadException
    try:
        r = requests.get(CGI_URL.rstrip('/') + '/pam_check.cgi?file=' + filename)
        response = r.json()
        authenticated = response['authenticated']
    except:
        pass
    finally:
        # print(filepath)
        os.remove(filepath)
        pass
    return authenticated


def open_database():
    db = 'submitty'
    if os.path.isdir(DATABASE_HOST):
        conn_string = "postgresql://{}:{}@/{}?host={}".format(DATABASE_USER, DATABASE_PASS, db, DATABASE_HOST)
    else:
        conn_string = "postgresql://{}:{}@{}/{}".format(DATABASE_USER, DATABASE_PASS, DATABASE_HOST, db)

    engine = create_engine(conn_string)
    connection = engine.connect()
    metadata = MetaData(bind=engine)
    return engine, connection, metadata


def close_database(engine, connection):
    if engine is not None:
        connection.close()
        engine.dispose()


def check_database(username, password, connection, metadata):
    password = get_php_db_password(password)

    users_table = Table('users', metadata, autoload=True)
    select = users_table.select().where(users_table.c.user_id == bindparam('user_id'))
    user = connection.execute(select, user_id=username).fetchone()
    if user is None:
        authenticated = None
    else:
        authenticated = user['user_password'] == password

    return authenticated

if __name__ == "__main__":
    print(check_password({'REQUEST_URI': '/git/f17/sample/open_homework/instructor'}, 'instructor', 'instructor'))
    print(check_password({'REQUEST_URI': '/git/f17/sample/open_homework/instructor'}, 'ta', 'ta'))
    print(check_password({'REQUEST_URI': '/git/f17/sample/open_homework/instructor'}, 'student', 'student'))
    print(check_password({'REQUEST_URI': '/git/f17/sample/open_homework/student'}, 'student', 'student'))
