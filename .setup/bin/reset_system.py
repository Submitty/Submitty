#!/usr/bin/env python3

"""
Script to reset the vagrant box and the configurations that install_system does
to set up the submitty system with regards to apache, postgresql, etc. This then
allows for install_system.sh to run cleanly and not end up with duplicate lines
in configuration files or pre-existing databses.
"""

from __future__ import print_function
import glob
import os
import pwd
import shutil
import subprocess
import tempfile

import yaml

CURRENT_PATH = os.path.dirname(os.path.realpath(__file__))
SETUP_DATA_PATH = os.path.join(CURRENT_PATH, "..", "data")


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


def remove_lines(filename, lines):
    """
    Given a file, go through and remove a line if it's contained in the lines
    list

    :param filename: file to remove lines from
    :type filename: str
    :param lines: list of strings to remove from the filename
    :type lines: list
    """
    if not os.path.isfile(filename):
        return
    if not isinstance(lines, list) or not lines:
        return
    stat = os.stat(filename)
    with tempfile.NamedTemporaryFile(mode='w', delete=False) as tmp_file:
        with open(filename, "r") as read_file:
            for line in read_file:
                if line.strip() in lines:
                    continue
                tmp_file.write(line)
        shutil.copystat(filename, tmp_file.name)
        shutil.move(tmp_file.name, filename)
    os.chown(filename, stat.st_uid, stat.st_gid)


def cmd_exists(cmd):
    """
    Given a command name, checks to see if it exists on the system, return True or False
    """
    return os.system("type " + str(cmd)) == 0


def remove_file(filename):
    if os.path.isfile(filename):
        os.remove(filename)


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


def main():
    if not os.path.isdir(os.path.join(CURRENT_PATH, "..", "..", ".vagrant")):
        raise SystemExit("This script can only be run against the vagrant installation")

    # Remove the MOT.D
    remove_file("/etc/motd")

    # Scrub out the network interfaces that were created for Vagrant
    #subprocess.call(["ifdown", "enp0s8", "enp0s8:1", "enp0s8:2"])
    #remove_file("/etc/network/interfaces.d/enp0s8.cfg")

    # Remove the data directories for submitty
    shutil.rmtree('/usr/local/submitty/.setup', True)
    shutil.rmtree('/var/local/submitty', True)

    # If we have psql cmd available, then PostgreSQL is installed so we should scrub out any
    # submitty DBs
    if cmd_exists('psql'):
        os.environ['PGPASSWORD'] = 'hsdbu'
        os.system("psql -h localhost -U hsdbu --list | grep submitty_* | awk '{print $1}' | "
                  "xargs -I \"@@\" dropdb -h localhost -U hsdbu \"@@\"")
        del os.environ['PGPASSWORD']

        psql_version = subprocess.check_output("psql -V | egrep -o '[0-9]{1,}\.[0-9]{1,}'",
                                               shell=True).strip()

        try:
            shutil.move('/etc/postgresql/' + str(psql_version) + '/main/pg_hba.conf.backup', '/etc/postgresql/' + str(psql_version) + '/main/pg_hba.conf')
        except FileNotFoundError:
            pass

    for i in range(0, 60):
        j = str(i).zfill(2)
        os.system("userdel untrusted" + j)

    auths = ["#%PAM-1.0", "auth required pam_unix.so", "account required pam_unix.so"]
    remove_lines('/etc/pam.d/httpd', auths)

    # Scrub some stuff from apache
    shutil.rmtree('/etc/apache2/ssl', True)
    remove_file('/etc/apache2/suexec/www-data')

    for folder in ["/etc/apache2/sites-enabled", "/etc/apache2/sites-available"]:
        if os.path.isdir(folder):
            for the_file in os.listdir(folder):
                if folder == "/etc/apache2/sites-available":
                    os.system("a2dissite " + the_file.replace(".conf", ""))
                file_path = os.path.join(folder, the_file)
                remove_file(file_path)

    #remove_lines('/etc/apache2/apache2.conf', ["ServerName 10.0.2.15"])

    shutil.rmtree('/root/bin', True)

    for user_file in glob.iglob(os.path.join(SETUP_DATA_PATH, "users", "*.yml")):
        user = load_data_yaml(user_file)
        delete_user(user['user_id'])

    os.system('pkill -u hwcron')
    os.system('crontab -u hwcron -r')
    for user in ["hwcgi", "hwphp", "hwcron", "hsdbu"]:
        delete_user(user)

    groups = ["hwcronphp", "course_builders"]
    for course_file in glob.iglob(os.path.join(SETUP_DATA_PATH, "courses", "*.yml")):
        course = load_data_yaml(course_file)
        groups.append(course['code'])
        groups.append(course['code'] + "_archive")
        groups.append(course['code'] + "_tas_www")

    for group in groups:
        os.system('groupdel ' + group)

if __name__ == '__main__':
    main()
