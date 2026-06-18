#!/usr/bin/env python3

import json
import os

import requests
from requests.exceptions import RequestException

CONFIG_PATH = os.path.join(os.path.dirname(os.path.realpath(__file__)), '..', 'config')

with open(os.path.join(CONFIG_PATH, 'submitty.json')) as open_file:
    OPEN_JSON = json.load(open_file)
SUBMISSION_URL = OPEN_JSON['submission_url']


def check_password(environ, user, password):
    """

    :param environ:  Dictionary that contains the apache environment variables.
                     There's a lot of overlap of what you get from this dictionary
                     and http://php.net/manual/en/reserved.variables.server.php
    :param user:     String containing the username passed to the apache authentication
    :param password: String containing the password passed to the apache authentication
    :return:         Boolean for whether user was properly authenticated or not
    """
    # The REQUEST_URI will contain stuff after the usual
    # /<VCS>/<SEMESTER>/<COURSE>/<G_ID>/<USER_ID> that have
    # to do with the GIT and whether it's pushing, pulling, cloning, etc.

    params = list(filter(lambda x: len(x) > 0, environ['REQUEST_URI'].split("/")))
    vcs = params[0]

    vcs_paths = []
    if vcs == 'git':
        # info/refs?service=git-upload-pack
        vcs_paths = [
            'info',
            'git-upload-pack',
            'refs?service=git-upload-pack',
            'refs?service=git-receive-pack',
            'git-receive-pack'
        ]

    params = list(filter(lambda x: x not in vcs_paths, params))
    if len(params) == 5:
        semester, course, gradeable, unknown_id = params[1:]
    else:
        return None

    data = {
        'user_id': user,
        'password': password,
        'gradeable_id': gradeable,
        'id': unknown_id,
    }

    try:
        req = requests.post(
            SUBMISSION_URL + f'/{semester}/{course}/authentication/vcs_login',
            data=data
        )
        response = req.json()
        if response['status'] == 'error':
            return None
        else:
            return response['status'] == 'success'
    except RequestException:
        pass
    return False


if __name__ == "__main__":
    """
    To test this script, you'll have to run this as www-data or PHP_USER or CGI_USER so
    that when it creates the temp files, pam_check.cgi has access to them. Run it like
    this:
    sudo -u www-data /usr/local/submitty/sbin/authentication.py

    The output should be:
    True
    True
    False
    True
    None
    """
    #
    request_uri = '/git/s19/sample/open_homework/instructor'
    print(check_password({'REQUEST_URI': request_uri}, 'instructor', 'instructor'))
    print(check_password({'REQUEST_URI': request_uri}, 'ta', 'ta'))
    print(check_password({'REQUEST_URI': request_uri}, 'student', 'student'))
    print(check_password(
        {'REQUEST_URI': '/git/s19/sample/open_homework/student'},
        'student',
        'student')
    )

    # Wrong URI. Returns None.
    print(check_password(
        {'REQUEST_URI': '/git/s19/sample/instructor'},
        'instructor',
        'instructor'
    ))
