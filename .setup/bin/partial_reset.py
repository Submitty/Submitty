#!/usr/bin/env python3
"""
This does a more partial reset of the system compared to reset_system.py, primarily not wiping
various changes like removing DB users, all created users (including system ones like hwphp,
hwcgi, etc.), removing networking stuff, etc.

This script acts more like the inverse of "setup_sample_courses.py" so that we could only run
these two scripts in opposition and not end up in a corrupted system state. This gives us a
nice balance for developing when we're not actively changing integral parts of the system that
would require a new vagrant install (or at least a rerun of install_system.sh).
"""
import argparse
import glob
import os
import platform
import pwd
import shutil
import json
import yaml

CURRENT_PATH = os.path.dirname(os.path.realpath(__file__))
SETUP_DATA_PATH = os.path.join(CURRENT_PATH, "..", "data")
SUBMITTY_REPOSITORY = "/usr/local/submitty/GIT_CHECKOUT_Submitty"
SUBMITTY_INSTALL_DIR = "/usr/local/submitty"
SUBMITTY_DATA_DIR = "/var/local/submitty"


def load_data_yaml(file_path):
    """
    Loads yaml file from the .setup/data directory returning the parsed structure
    :param file_path: name of file to load
    :return: parsed YAML structure from loaded file
    """
    if not os.path.isfile(file_path):
        raise IOError("Missing the yaml file {}".format(file_path))
    with open(file_path) as open_file:
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
        if os.path.isdir("/home/" + user_id):
            shutil.rmtree("/home/" + user_id)
        return True
    except KeyError:
        pass
    return False


def cmd_exists(cmd):
    """
    Given a command name, checks to see if it exists on the system, return True or False
    """
    return os.system("type " + str(cmd)) == 0


def parse_args():
    """Generate arguments for the CLI"""
    parser = argparse.ArgumentParser(description="")
    parser.add_argument("--force", action="store_true",
                        help="Run this script skipping the 'are you sure' prompts. These are also "
                             "bypassed if .vagrant folder is detected.")
    parser.add_argument("--users_path", default=os.path.join(SETUP_DATA_PATH, "users"),
                        help="Path to folder that contains .yml files to use for user creation. "
                             "Defaults to ../data/users")
    parser.add_argument("--courses_path", default=os.path.join(SETUP_DATA_PATH, "courses"),
                        help="Path to the folder that contains .yml files to use for course "
                             "creation. Defaults to ../data/courses")
    return parser.parse_args()


def main():
    """Primary function"""
    args = parse_args()
    if not os.path.isdir(os.path.join(CURRENT_PATH, "..", "..", ".vagrant")) and not args.force:
        inp = input("Do you really want to reset the system? There's no undo! [y/n]")
        if inp.lower() not in ["yes", "y"]:
            raise SystemExit("Aborting...")

    shutil.rmtree('/var/local/submitty', True)
    os.system("mkdir -p {}/courses".format(SUBMITTY_DATA_DIR))
    os.system("mkdir -p {}/instructors".format(SUBMITTY_DATA_DIR))
    os.system("ls /home | sort > {}/instructors/valid".format(SUBMITTY_DATA_DIR))
    os.system("{}/.setup/INSTALL_SUBMITTY.sh".format(SUBMITTY_INSTALL_DIR))
    distro = platform.linux_distribution()[0].lower()
    if os.path.isdir(os.path.join(CURRENT_PATH, "..", "..", ".vagrant")):
        os.system("rm -rf {}/logs".format(SUBMITTY_DATA_DIR))
        os.system('rm -rf {}/.vagrant/{}/logs'.format(SUBMITTY_REPOSITORY, distro))

        os.system('mkdir {}/.vagrant/{}/logs'.format(SUBMITTY_REPOSITORY, distro))
        os.system('ln -s {}/.vagrant/{}/logs {}'.format(SUBMITTY_REPOSITORY, distro, SUBMITTY_DATA_DIR))


        os.system('mkdir {}/.vagrant/{}/logs/autograding'.format(SUBMITTY_REPOSITORY, distro))
        os.system('mkdir {}/.vagrant/{}/logs/access'.format(SUBMITTY_REPOSITORY, distro))
        os.system('mkdir {}/.vagrant/{}/logs/site_errors'.format(SUBMITTY_REPOSITORY, distro))

    if cmd_exists('psql'):
        with open(os.path.join(SUBMITTY_INSTALL_DIR,".setup","submitty_conf.json")) as submitty_config:
            submitty_config_json = json.load(submitty_config)
            os.environ['PGPASSWORD'] = submitty_config_json["database_password"]
        os.system('psql -d postgres -U hsdbu -c "SELECT pg_terminate_backend(pg_stat_activity.pid) '
                  'FROM pg_stat_activity WHERE pg_stat_activity.datname LIKE \'Submitty%\' AND '
                  'pid <> pg_backend_pid();"')
        os.system("psql -U hsdbu --list | grep submitty* | awk '{print $1}' | "
                  "xargs -I \"@@\" dropdb -h localhost -U hsdbu \"@@\"")
        os.system('psql -d postgres -U hsdbu -c "CREATE DATABASE submitty"')
        os.system('psql -d submitty -U hsdbu -f {}/site/data/submitty_db.sql'.format(SUBMITTY_REPOSITORY))
        del os.environ['PGPASSWORD']

    for user_file in glob.iglob(os.path.join(args.users_path, "*.yml")):
        user = load_data_yaml(user_file)
        delete_user(user['user_id'])

    if os.path.isfile(os.path.join(SETUP_DATA_PATH, "random_users.txt")):
        with open(os.path.join(SETUP_DATA_PATH, "random_users.txt")) as open_file:
            for line in open_file:
                delete_user(line.strip())

    groups = []
    for course_file in glob.iglob(os.path.join(args.courses_path, "*.yml")):
        course = load_data_yaml(course_file)
        groups.append(course['code'])
        groups.append(course['code'] + "_archive")
        groups.append(course['code'] + "_tas_www")
        for queue in ["to_be_graded_queue"]:
            path = os.path.join(SUBMITTY_DATA_DIR, queue, "*__{}__*".format(course['code']))
            for queue_file in glob.iglob(path):
                os.remove(queue_file)

    for group in groups:
        os.system('groupdel ' + group)

if __name__ == "__main__":
    main()
