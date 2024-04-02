#!/usr/bin/env python3

"""
Use this script to create new users.
"""

import argparse
import json
from os import path
import subprocess
import requests
from submitty_utils import db_utils

CONFIG_PATH = path.join(path.dirname(path.realpath(__file__)), '..', 'config')
with open(path.join(CONFIG_PATH, 'database.json')) as open_file:
    DATABASE_DETAILS = json.load(open_file)
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

    defaults = {
        'user_givenname': None,
        'user_preferred_givenname': None,
        'user_familyname': None,
        'user_email': None
    }
    givenname = get_input('User givenname', defaults['user_givenname'])
    preferred = get_input(
        'User preferred name',
        defaults['user_preferred_givenname'],
        True
    )
    familyname = get_input('User familyname', defaults['user_familyname'])
    numeric_id = get_input('User Numeric ID')
    email = get_input('User email', defaults['user_email'], True)

    data = {
        'given_name': givenname,
        'preferred_given_name': preferred,
        'family_name': familyname,
        'email': email,
        'numeric_id': numeric_id,
        'user_id': user_id
    }

    extra = ""
    if AUTHENTICATION_METHOD == 'DatabaseAuthentication':
        extra = ' (Leave blank to use previous password)'
    while AUTHENTICATION_METHOD == 'DatabaseAuthentication':
        password = input('User password{}: '.format(extra))
        if password != '':
            data['password'] = get_php_db_password(password)
            break
        elif password == '':
            break
    user = get_input('Enter your username')
    admin_password = get_input('Enter your password')
    key = requests.post('http://localhost:1511/api/token', data={'user_id': user, 'password': admin_password})
    print(key.json())
    request = requests.post('http://localhost:1511/api/users/add', headers={'Authorization' : key.json()['data']['token']}, data=data)
    print(request.text)
    # else:
    #     update['user_id'] = user_id
    #     query = users_table.insert()
    #     connection.execute(query, **update)


if __name__ == '__main__':
    main()
