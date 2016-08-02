#!/usr/bin/env python

"""
Script to reset the vagrant box and the configurations that install_system does
to set up the submitty system with regards to apache, postgresql, etc. This then
allows for install_system.sh to run cleanly and not end up with duplicate lines
in configuration files or pre-existing databses.
"""

import os
import shutil
import subprocess
import tempfile


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
    if not isinstance(lines, list) or len(lines) == 0:
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


if __name__ == '__main__':
    # Remove the MOT.D
    remove_file("/etc/motd");

    # Scrub the hosts file
    hosts = ["192.168.56.101    test-submit test-submit.cs.rpi.edu", "192.168.56.102    test-svn test-svn.cs.rpi.edu",
             "192.168.56.103    test-hwgrading test-hwgrading.cs.rpi.edu hwgrading"]
    remove_lines("/etc/hosts", hosts)

    # Scrub out the network interfaces that were created for Vagrant
    subprocess.call(["ifdown", "eth1", "eth1:1", "eth1:2", "eth1:3", "eth1:4"])
    remove_file("/etc/network/interfaces.d/eth1.cfg")

    # Remove the data directories for submitty
    shutil.rmtree('/usr/local/submitty/.setup', True)
    shutil.rmtree('/var/local/submitty', True)
    shutil.rmtree('/var/lib/svn', True)

    # If we have psql cmd available, then PostgreSQL is installed so we should scrub out any submitty DBs
    if (cmd_exists('psql')):
        os.environ['PGPASSWORD'] = 'hsdbu'
        os.system("psql -h localhost -U hsdbu --list | grep submitty_* | awk '{print $1}' | xargs -I \"@@\" dropdb -h localhost -U hsdbu \"@@\"")
        del os.environ['PGPASSWORD']

        psql_version = subprocess.check_output("psql -V | egrep -o '[0-9]{1,}\.[0-9]{1,}'", shell=True).strip()
        lines = ["hostssl    all    all    192.168.56.0/24    pam", "host       all    all    192.168.56.0/24    pam",
                 "host       all    all    all                md5"]
        remove_lines('/etc/postgresql/' + psql_version + '/main/pg_hba.conf', lines)

    for i in range(0, 60):
        j = str(i).zfill(2)
        os.system("userdel untrusted" + j)

    lines = ["#%PAM-1.0", "auth required pam_unix.so", "account required pam_unix.so"]
    remove_lines('/etc/pam.d/httpd', lines)

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

    remove_lines('/etc/apache2/apache2.conf', ["ServerName 10.0.2.15"])

    shutil.rmtree('/root/bin', True)

    users = ["instructor", "ta", "developer", "student", "hwphp", "hwphp-cgi", "hwcron", "hsdbu"]
    for user in users:
        os.system("userdel " + user)
        if os.path.isdir("/home/" + user):
            shutil.rmtree("/home/" + user)

    groups = ["hwcronphp", "course_builders"]
    courses = ["csci1000", "csci1100", "csci1200", "csci2600"]
    for course in courses:
        groups.append(course)
        groups.append(course + "_tas_www")

    for group in groups:
        os.system('groupdel ' + group)
