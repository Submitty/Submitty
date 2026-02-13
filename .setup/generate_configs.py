import argparse
import os
import shutil
import json
from collections import OrderedDict
import pwd

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
parser.add_argument('--debug', action='store_true', default=False, help='Configure Submitty to be in debug mode. '
                                                                        'This should not be used in production!')
parser.add_argument('--worker', action='store_true', default=False, help='Configure Submitty with autograding only')
parser.add_argument('--install-dir', default='/usr/local/submitty', help='Set the install directory for Submitty')
parser.add_argument('--data-dir', default='/var/local/submitty', help='Set the data directory for Submitty')

args = parser.parse_args()

SUBMITTY_DATA_DIR = args.data_dir
os.makedirs(SUBMITTY_DATA_DIR, exist_ok=True)
CONFIG_INSTALL_DIR = os.path.join(args.install_dir, 'config')
os.makedirs(CONFIG_INSTALL_DIR, exist_ok=True)
CONFIG_REPOSITORY = os.path.join(args.install_dir, 'GIT_CHECKOUT/Submitty/.setup/data/configs')

if not args.worker:
    for item in os.listdir(CONFIG_REPOSITORY):
        source_path = os.path.join(CONFIG_REPOSITORY, item)
        destination_path = os.path.join(CONFIG_INSTALL_DIR, item)
        # Check if the item is a file before copying
        if os.path.isfile(source_path) and not os.path.exists(destination_path):
            try:
                shutil.copy(source_path, destination_path)
                print(f"Copied '{item}'")
            except PermissionError:
                print(f"Permission denied for '{item}'")
            except Exception as e:
                print(f"An error occurred while copying '{item}': {e}")


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
else:
    config['supervisor_user'] = SUPERVISOR_USER

with open(os.path.join(CONFIG_INSTALL_DIR, 'submitty_users.json'), 'w') as users_file:
    json.dump(config, users_file, indent=2)
