#!/usr/bin/env python3

"""
Use this script to create new users.
"""

import argparse
import json
from os import path
import subprocess
from sqlalchemy import create_engine, MetaData, Table, bindparam, insert, select, update

from submitty_utils import db_utils

CONFIG_PATH = path.join(path.dirname(path.realpath(__file__)), '..', 'config')
with open(path.join(CONFIG_PATH, 'database.json')) as open_file:
    DATABASE_DETAILS = json.load(open_file)
DATABASE_HOST = DATABASE_DETAILS['database_host']
DATABASE_PORT = DATABASE_DETAILS['database_port']
DATABASE_USER = DATABASE_DETAILS['database_user']
DATABASE_PASS = DATABASE_DETAILS['database_password']
AUTHENTICATION_METHOD = DATABASE_DETAILS['authentication_method']


def get_php_db_password(password):
    """
    Generates a password to be used within the site for database authentication. The
    password_hash function (http://php.net/manual/en/function.password-hash.php)
    generates us a nice secure password and takes care of things like salting and
    hashing.
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
    parser = argparse.ArgumentParser(
        description='Utility that given a user will create them into the database'
    )

    parser.add_argument('user_id', help='user_id of the user to create')

    return parser.parse_args()


def main():
    args = parse_args()
    user_id = args.user_id

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
    user = connection.execute(select_query, {"user_id": user_id}).mappings().fetchone()
    defaults = {
        'user_givenname': None,
        'user_preferred_givenname': None,
        'user_familyname': None,
        'user_email': None
    }
    if user is not None:
        print(
            'User already exists! Hit enter on any question to use '
            'existing value for that field.'
        )
        defaults = user

    givenname = get_input('User givenname', defaults['user_givenname'])
    preferred = get_input(
        'User preferred name',
        defaults['user_preferred_givenname'],
        True
    )
    familyname = get_input('User familyname', defaults['user_familyname'])
    email = get_input('User email', defaults['user_email'], True)

    update_data = {
        'user_givenname': givenname,
        'user_preferred_givenname': preferred,
        'user_familyname': familyname,
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
        query = update(users_table).where(
            users_table.c.user_id == bindparam('b_user_id')
        ).values(update_data)
        connection.execute(query, {"b_user_id": user_id})
    else:
        update_data['user_id'] = user_id
        query = insert(users_table)
        connection.execute(query, update_data)
    connection.commit()


if __name__ == '__main__':
    main()
