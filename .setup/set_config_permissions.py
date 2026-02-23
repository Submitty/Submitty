import argparse
from collections import OrderedDict
import json
import os
import pwd
import secrets
import shutil
import string

def get_uid(user):
    return pwd.getpwnam(user).pw_uid


def get_gid(user):
    return pwd.getpwnam(user).pw_gid


def get_ids(user):
    try:
        return get_uid(user), get_gid(user)
    except KeyError:
        raise SystemExit("ERROR: Could not find user: " + user)

parser = argparse.ArgumentParser(description='Submitty config validation script',
                                 formatter_class=argparse.ArgumentDefaultsHelpFormatter)
parser.add_argument('--install-dir', default='/usr/local/submitty', help='Set the install directory for Submitty')
parser.add_argument('--worker', action='store_true', default=False, help='Configure Submitty with autograding only')

args = parser.parse_args()

SUBMITTY_INSTALL_DIR = args.install_dir

CONFIG_INSTALL_DIR = os.path.join(SUBMITTY_INSTALL_DIR, 'config')
DATABASE_JSON = os.path.join(CONFIG_INSTALL_DIR, 'database.json')
SUBMITTY_ADMIN_JSON = os.path.join(CONFIG_INSTALL_DIR, 'submitty_admin.json')
AUTHENTICATION_JSON = os.path.join(CONFIG_INSTALL_DIR, 'authentication.json')
SUBMITTY_JSON = os.path.join(CONFIG_INSTALL_DIR, 'submitty.json')
SUBMITTY_USERS_JSON = os.path.join(CONFIG_INSTALL_DIR, 'submitty_users.json')
EMAIL_JSON = os.path.join(CONFIG_INSTALL_DIR, 'email.json')
WORKERS_JSON = os.path.join(CONFIG_INSTALL_DIR, 'autograding_workers.json')
CONTAINERS_JSON = os.path.join(CONFIG_INSTALL_DIR, 'autograding_containers.json')
SECRETS_PHP_JSON = os.path.join(CONFIG_INSTALL_DIR, 'secrets_submitty_php.json')


PHP_USER = 'submitty_php'
PHP_GROUP = 'submitty_php'
CGI_USER = 'submitty_cgi'
DAEMON_USER = 'submitty_daemon'
DAEMON_GROUP = 'submitty_daemon'
DAEMONPHP_GROUP = 'submitty_daemonphp'
DAEMONPHPCGI_GROUP = 'submitty_daemonphpcgi'
DAEMONCGI_GROUP = 'submitty_daemoncgi'

FIRST_UNTRUSTED_UID, FIRST_UNTRUSTED_GID = get_ids('untrusted00')
DAEMON_UID, DAEMON_GID = get_ids(DAEMON_USER)

if not args.worker:
    for file in [WORKERS_JSON, CONTAINERS_JSON]:
        os.chmod(file, 0o660)
    shutil.chown(WORKERS_JSON, PHP_USER, DAEMONPHP_GROUP)
    shutil.chown(CONTAINERS_JSON, group=DAEMONPHP_GROUP)

os.chmod(SUBMITTY_JSON, 0o444)

os.chmod(SUBMITTY_USERS_JSON, 0o440)

shutil.chown(SUBMITTY_USERS_JSON, 'root', DAEMON_GROUP if args.worker else DAEMONPHP_GROUP)


if not args.worker:
    config = OrderedDict()
    characters = string.ascii_letters + string.digits
    config['session'] = ''.join(secrets.choice(characters) for _ in range(64))
    with open(SECRETS_PHP_JSON, 'w') as json_file:
        json.dump(config, json_file, indent=2)
shutil.chown(SECRETS_PHP_JSON, 'root', PHP_GROUP)
os.chmod(SECRETS_PHP_JSON, 0o440)

if not args.worker:
    shutil.chown(DATABASE_JSON, 'root', DAEMONPHP_GROUP)
    os.chmod(DATABASE_JSON, 0o440)

    shutil.chown(AUTHENTICATION_JSON, 'root', DAEMONPHP_GROUP)
    os.chmod(AUTHENTICATION_JSON, 0o440)

    shutil.chown(SUBMITTY_ADMIN_JSON, 'root', DAEMON_GROUP)
    os.chmod(SUBMITTY_ADMIN_JSON, 0o440)

    shutil.chown(EMAIL_JSON, 'root', DAEMONPHP_GROUP)
    os.chmod(EMAIL_JSON, 0o440)


config = OrderedDict()
config['num_grading_scheduler_workers'] = 5
config['num_untrusted'] = 60
config['first_untrusted_uid'] = FIRST_UNTRUSTED_UID
config['first_untrusted_gid'] = FIRST_UNTRUSTED_GID
config['daemon_uid'] = DAEMON_UID
config['daemon_gid'] = DAEMON_GID
config['daemon_user'] = DAEMON_USER
config['course_builders_group'] = 'submitty_course_builders'

if not args.worker:
    PHP_UID, PHP_GID = get_ids(PHP_USER)
    CGI_UID, CGI_GID = get_ids(CGI_USER)
    config['php_uid'] = PHP_UID
    config['php_gid'] = PHP_GID
    config['php_user'] = PHP_USER
    config['cgi_user'] = CGI_USER
    config['daemonphp_group'] = DAEMONPHP_GROUP
    config['daemoncgi_group'] = DAEMONCGI_GROUP
    config['daemonphpcgi_group'] = DAEMONPHPCGI_GROUP

with open(os.path.join(CONFIG_INSTALL_DIR, 'submitty_users.json'), 'w') as users_file:
    json.dump(config, users_file, indent=2)
