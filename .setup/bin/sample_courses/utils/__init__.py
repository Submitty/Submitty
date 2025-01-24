"""
This file contains functions that are independent of other functions and this file
should not import functions from other files in Utils package. 
This is done to avoid circular imports.
"""

# flake8: noqa
import json
import os
import random
import shutil
import subprocess
from datetime import datetime

from sample_courses import SUBMITTY_DATA_DIR, SETUP_DATA_PATH, yaml


def get_random_text_from_file(filename):
    line: str = ""
    with open(os.path.join(SETUP_DATA_PATH, 'random', filename)) as comment:
        line = next(comment)
        for num, alternate_line in enumerate(comment):
            if random.randrange(num + 2):
                continue
            line = alternate_line
    return line.strip()


def load_data_json(file_name):
    """
    Loads json file from the .setup/data directory returning the parsed structure
    :param file_name: name of file to load
    :return: parsed JSON structure from loaded file
    """
    file_path: str = os.path.join(SETUP_DATA_PATH, file_name)
    if not os.path.isfile(file_path):
        raise IOError(f"Missing the json file .setup/data/{file_name}")
    with open(file_path) as open_file:
        json_file = json.load(open_file)
    return json_file


def load_data_yaml(file_path):
    """
    Loads yaml file from the .setup/data directory returning the parsed structure
    :param file_path: name of file to load
    :return: parsed YAML structure from loaded file
    """
    if not os.path.isfile(file_path):
        raise IOError(f"Missing the yaml file {file_path}")
    with open(file_path) as open_file:
        yaml_file = yaml.load(open_file)
    return yaml_file


def get_php_db_password(password):
    """
    Generates a password to be used within the site for database authentication. The password_hash
    function (http://php.net/manual/en/function.password-hash.php) generates us a nice secure
    password and takes care of things like salting and hashing.
    :param password:
    :return: password hash to be inserted into the DB for a user
    """
    proc = subprocess.Popen(
        ["php", "-r", f"print(password_hash('{password}', PASSWORD_DEFAULT));"],
        stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    (out, err) = proc.communicate()
    return out.decode('utf-8')


def get_current_semester():
    """
    Given today's date, generates a three character code that represents the semester to use for
    courses such that the first half of the year is considered "Spring" and the last half is
    considered "Fall". The "Spring" semester  gets an S as the first letter while "Fall" gets an
    F. The next two characters are the last two digits in the current year.
    :return:
    """
    today: datetime = datetime.today()
    semester: str = "f" + str(today.year)[-2:]
    if today.month < 7:
        semester = "s" + str(today.year)[-2:]
    return semester


def mimic_checkout(repo_path, checkout_path, vcs_subdirectory):
    os.system(f"su -c 'git clone {SUBMITTY_DATA_DIR}/vcs/git/{repo_path} {checkout_path}/tmp -b main' submitty_daemon")
    if vcs_subdirectory != '':
        if vcs_subdirectory[0] == '/':
            vcs_subdirectory = vcs_subdirectory[1:]
        file_path = os.path.join(f'{checkout_path}/tmp', vcs_subdirectory)
    else:
        file_path = os.path.join(f'{checkout_path}/tmp')

    shutil.copytree(file_path, f'{checkout_path}', dirs_exist_ok=True)
    shutil.rmtree(f'{checkout_path}/tmp')
