import json
from pathlib import Path
from subprocess import DEVNULL, STDOUT, check_call


DAEMONCGI_GROUP = 'submitty_daemoncgi'


def up(config):
    check_call(['addgroup', DAEMONCGI_GROUP], stdout=DEVNULL, stderr=STDOUT)
    check_call(['usermod', '-a', '-G', DAEMONCGI_GROUP, config.submitty_users['cgi_user']])
    check_call(['usermod', '-a', '-G', DAEMONCGI_GROUP, config.submitty_users['daemon_user']])
    config.submitty_users['daemoncgi_group'] = DAEMONCGI_GROUP
    with Path(config.config_path, 'submitty_users.json').open('w') as open_file:
        json.dump(config.submitty_users, open_file, indent=2)


def down(config):
    check_call(['groupdel', DAEMONCGI_GROUP])
    del config.submitty_users['daemoncgi_group']
    with Path(config.config_path, 'submitty_users.json').open('w') as open_file:
        json.dump(config.submitty_users, open_file, indent=2)
