parser = argparse.ArgumentParser(description='Submitty config validation script',
                                 formatter_class=argparse.ArgumentDefaultsHelpFormatter)
parser.add_argument('--debug', action='store_true', default=False, help='Configure Submitty to be in debug mode. '
                                                                        'This should not be used in production!')
parser.add_argument('--worker', action='store_true', default=False, help='Configure Submitty with autograding only')
parser.add_argument('--install-dir', default='/usr/local/submitty', help='Set the install directory for Submitty')
parser.add_argument('--data-dir', default='/var/local/submitty', help='Set the data directory for Submitty')


SUBMITTY_DATA_DIR = args.data_dir
os.mkdirs(SUBMITTY_DATA_DIR)
CONFIG_INSTALL_DIR = os.path.join(args.install_dir, 'config')
os.mkdirs(SUBMITTY_INSTALL_DIR)
DATABASE_JSON = os.path.join(CONFIG_INSTALL_DIR, 'database.json')
SUBMITTY_JSON = os.path.join(CONFIG_INSTALL_DIR, 'submitty.json')
SUBMITTY_USERS_JSON = os.path.join(CONFIG_INSTALL_DIR, 'submitty_users.json')
WORKERS_JSON = os.path.join(CONFIG_INSTALL_DIR, 'autograding_workers.json')
CONTAINERS_JSON = os.path.join(CONFIG_INSTALL_DIR, 'autograding_containers.json')
SECRETS_PHP_JSON = os.path.join(CONFIG_INSTALL_DIR, 'secrets_submitty_php.json')


{
    "num_grading_scheduler_workers": 5,
    "num_untrusted": 60
    "first_untrusted_uid": FIRST_UNTRUSTED_UID
    "first_untrusted_gid": FIRST_UNTRUSTED_UID
    "daemon_uid": DAEMON_UID
    "daemon_gid": DAEMON_GID
    "daemon_user": DAEMON_USER
    "course_builders_group": COURSE_BUILDERS_GROUP
}
if not args.worker 
{
    "php_uid": PHP_UID
    "php_gid": PHP_GID
    "php_user": PHP_USER
    "cgi_user": CGI_USER
    "daemonphp_group": DAEMONPHP_GROUP
    "daemoncgi_group": DAEMONCGI_GROUP
    "daemonphpcgi_group": DAEMONPHPCGI_GROUP
    
}