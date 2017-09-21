#!/usr/bin/env python3

import json
import os
import subprocess
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
    # The REQUEST_URI will contain stuff after the usual /<VCS>/<SEMESTER>/<COURSE>/<G_ID>/<USER_ID> that have
    # to do with the GIT and whether it's pushing, pulling, cloning, etc.

    params = list(filter(lambda x: len(x) > 0, environ['REQUEST_URI'].split("/")))
    vcs = params[0]

    vcs_paths = []
    if vcs == 'git':
        # info/refs?service=git-upload-pack
        vcs_paths = ['info', 'git-upload-pack', 'refs?service=git-upload-pack', 'refs?service=git-receive-pack',
                     'git-receive-pack']

    params = list(filter(lambda x: x not in vcs_paths, params))
    if len(params) == 4:
       # FIXME: Let's disallow this path, require gradeable/reponame
        semester, course, user_id = params[1:]
        is_team = False
        course_engine = course_connection = course_metadata = None
    elif len(params) == 5:
        # FIXME: Shouldn't this be [1:3]?
        semester, course, gradeable = params[1:4]

        # check if this is a team or individual gradeable
        course_db = "submitty_{}_{}".format(semester, course)
        if os.path.isdir(DATABASE_HOST):
            course_conn_string = "postgresql://{}:{}@/{}?host={}".format(DATABASE_USER, DATABASE_PASS, course_db, DATABASE_HOST)
        else:
            course_conn_string = "postgresql://{}:{}@{}/{}".format(DATABASE_USER, DATABASE_PASS, DATABASE_HOST, course_db)

        course_engine = create_engine(course_conn_string)
        course_connection = course_engine.connect()
        course_metadata = MetaData(bind=course_engine)

        eg_table = Table('electronic_gradeable', course_metadata, autoload=True)
        select = eg_table.select().where(eg_table.c.g_id == bindparam('gradeable_id'))
        eg = course_connection.execute(select, gradeable_id=gradeable).fetchone()

        if eg is None:
            close_database(course_engine, course_connection)
            return None
        is_team = eg.eg_team_assignment

        if is_team:
            user_id = None
            team_id = params[4]
        else:
            user_id = params[4]
            team_id = None
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
        close_database(course_engine, course_connection)
        return authenticated

    if is_team:
        teams_table = Table('teams', course_metadata, autoload=True)
        select = teams_table.select().where(teams_table.c.team_id == bindparam('team_id')).where(teams_table.c.user_id == bindparam('user_id'))
        team_user = course_connection.execute(select, team_id=team_id, user_id=user).fetchone()
        if team_user is not None:
            close_database(engine, connection)
            close_database(course_engine, course_connection)
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
    close_database(course_engine, course_connection)

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
        php = "print(password_verify('{}', '{}') == true ? 'true' : 'false');".format(password, user['password'])
        authenticated = subprocess.check_output(['php', '-r', php]) == 'true'

    return authenticated

if __name__ == "__main__":
    """
    To test this script, you'll have to run this as www-data or hwphp or hwcgi so that when it creates the temp
    files, pam_check.cgi has access to them. Run it like this:
    sudo -u www-data /usr/local/submitty/bin/authentication.py
    
    The output should be:
    True
    True
    False
    True
    True
    True
    False
    True
    """
    #
    print(check_password({'REQUEST_URI': '/git/f17/sample/open_homework/instructor'}, 'instructor', 'instructor'))
    print(check_password({'REQUEST_URI': '/git/f17/sample/open_homework/instructor'}, 'ta', 'ta'))
    print(check_password({'REQUEST_URI': '/git/f17/sample/open_homework/instructor'}, 'student', 'student'))
    print(check_password({'REQUEST_URI': '/git/f17/sample/open_homework/student'}, 'student', 'student'))

    print(check_password({'REQUEST_URI': '/git/f17/sample/instructor'}, 'instructor', 'instructor'))
    print(check_password({'REQUEST_URI': '/git/f17/sample/instructor'}, 'ta', 'ta'))
    print(check_password({'REQUEST_URI': '/git/f17/sample/instructor'}, 'student', 'student'))
    print(check_password({'REQUEST_URI': '/git/f17/sample/student'}, 'student', 'student'))

