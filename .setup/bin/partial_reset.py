#!/usr/bin/env python3
"""
This does a more partial reset of the system compared to reset_system.py, primarily not wiping
various changes like removing DB users, all created users (including system ones like PHP_USER,
CGI_USER, etc.), removing networking stuff, etc.

This script acts more like the inverse of "setup_sample_courses.py" so that we could only run
these two scripts in opposition and not end up in a corrupted system state. This gives us a
nice balance for developing when we're not actively changing integral parts of the system that
would require a new vagrant install (or at least a rerun of install_system.sh).
"""
import argparse
import os
from pathlib import Path
import pwd
import shutil
import json
import subprocess

import yaml

CURRENT_PATH = Path(__file__).resolve().parent
SETUP_DATA_PATH = Path(CURRENT_PATH, "..", "data").resolve()
SUBMITTY_REPOSITORY = Path("/usr/local/submitty/GIT_CHECKOUT/Submitty")
SUBMITTY_INSTALL_DIR = Path("/usr/local/submitty")
SUBMITTY_DATA_DIR = Path("/var/local/submitty")


def load_data_yaml(file_path):
    """
    Loads yaml file from the .setup/data directory returning the parsed structure
    :param file_path: name of file to load
    :type file_path: Path
    :return: parsed YAML structure from loaded file
    """
    if not file_path.is_file():
        raise IOError("Missing the yaml file {}".format(file_path))
    with file_path.open() as open_file:
        yaml_file = yaml.safe_load(open_file)
    return yaml_file


def delete_user(user_id):
    """
    Checks to see if the user_id exists on the linux filesystem, and if so, delete the user
    and remove the home directory for it.

    :param user_id:
    :return: boolean on if the user_id existed and was removed
    """
    try:
        pwd.getpwnam(user_id)
        os.system("userdel " + user_id)
        if Path('/home', user_id).is_dir():
            shutil.rmtree(str(Path('/home', user_id)))
        return True
    except KeyError:
        pass
    return False


def cmd_exists(cmd):
    """
    Given a command name, checks to see if it exists on the system, return True or False
    """
    return shutil.which(cmd) is not None


def parse_args():
    """Generate arguments for the CLI"""
    parser = argparse.ArgumentParser(description="")
    parser.add_argument("--force", action="store_true",
                        help="Run this script skipping the 'are you sure' prompts. These are also "
                             "bypassed if .vagrant folder is detected.")
    parser.add_argument("--users_path", default=str(SETUP_DATA_PATH / "users"),
                        help="Path to folder that contains .yml files to use for user creation. "
                             "Defaults to ../data/users")
    parser.add_argument("--courses_path", default=str(SETUP_DATA_PATH / "courses"),
                        help="Path to the folder that contains .yml files to use for course "
                             "creation. Defaults to ../data/courses")
    return parser.parse_args()


def main():
    """Primary function"""
    if not cmd_exists('psql'):
        raise SystemExit('Postgresql must be installed for this script to run!')

    args = parse_args()
    if not Path(CURRENT_PATH, '..', '..', '.vagrant').is_dir() and not args.force:
        inp = input("Do you really want to reset the system? There's no undo! [y/n]")
        if inp.lower() not in ["yes", "y"]:
            raise SystemExit("Aborting...")

    services = subprocess.check_output(
        ["systemctl", "list-units", "--type=service"],
        universal_newlines=True
    ).strip().split("\n")
    running_services = []
    for service in services:
        service = service[2:].strip()
        if "submitty_" not in service:
            continue
        if "running" not in service:
            continue
        service = service.split()[0]
        running_services.append(service)
        subprocess.check_call(["systemctl", "stop", service])

    shutil.rmtree('/var/local/submitty', True)
    Path(SUBMITTY_DATA_DIR, 'courses').mkdir(parents=True)

    with Path(SUBMITTY_INSTALL_DIR, 'config', 'database.json').open() as submitty_config:
        submitty_config_json = json.load(submitty_config)
        os.environ['PGPASSWORD'] = submitty_config_json["database_password"]
        db_user = submitty_config_json["database_user"]
    query = """
SELECT pg_terminate_backend(pg_stat_activity.pid)
FROM pg_stat_activity
WHERE pg_stat_activity.datname LIKE \'Submitty%\' AND pid <> pg_backend_pid();
"""
    subprocess.check_call(['psql', '-d', 'postgres', '-U', db_user, '-c', query])
    db_list = subprocess.check_output(
        ['psql', '-U', db_user, '--list'],
        universal_newlines=True
    ).split("\n")[3:]
    for db_row in db_list:
        db_name = db_row.strip().split('|')[0].strip()
        if not db_name.startswith('submitty'):
            continue
        subprocess.check_call(['dropdb', '-h', 'localhost', '-U', db_user, db_name])
    subprocess.check_call(
        ['psql', '-d', 'postgres', '-U', db_user, '-c', 'CREATE DATABASE submitty']
    )
    migrator_script = str(SUBMITTY_REPOSITORY / 'migration' / 'run_migrator.py')
    subprocess.check_call(
        ['python3', migrator_script, '-e', 'system', '-e', 'master', 'migrate', '--initial']
    )
    del os.environ['PGPASSWORD']

    subprocess.check_call(['bash', str(SUBMITTY_INSTALL_DIR / '.setup' / 'INSTALL_SUBMITTY.sh')])

    for user_file in Path(args.users_path).glob('*.yml'):
        user = load_data_yaml(user_file)
        delete_user(user['user_id'])

    random_users = SETUP_DATA_PATH / 'random_users.txt'
    if random_users.is_file():
        with random_users.open() as open_file:
            for line in open_file:
                delete_user(line.strip())

    groups = []
    for course_file in Path(args.courses_path).glob('*.yml'):
        course = load_data_yaml(course_file)
        groups.append(course['code'])
        groups.append(course['code'] + "_archive")
        groups.append(course['code'] + "_tas_www")
        for queue in ['to_be_graded_queue']:
            queue_path = Path(SUBMITTY_DATA_DIR, queue)
            for queue_file in queue_path.glob("*__{}__*".format(course['code'])):
                queue_file.unlink()

    for group in groups:
        os.system('groupdel ' + group)


if __name__ == "__main__":
    main()
