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
        req = requests.post(SUBMISSION_URL + '/index.php?semester={}&course={}&component=authentication&page=vcs_login'.format(semester, course), data=data)
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
    To test this script, you'll have to run this as www-data or PHP_USER or CGI_USER so that when it creates the temp
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

