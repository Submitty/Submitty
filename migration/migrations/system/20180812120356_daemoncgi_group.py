from collections import OrderedDict
import json
from pathlib import Path
from subprocess import DEVNULL, STDOUT, check_call


def up(config):
    daemoncgi_group = 'submitty_daemoncgi'
    submitty_users = Path(config.submitty['submitty_install_dir'], 'config', 'submitty_users.json')
    with submitty_users.open() as open_file:
        users_json = json.load(open_file, object_pairs_hook=OrderedDict)
    if 'daemoncgi_group' not in users_json:
        check_call(['addgroup', daemoncgi_group], stdout=DEVNULL, stderr=STDOUT)
        check_call(['usermod', '-a', '-G', daemoncgi_group, users_json['cgi_user']])
        check_call(['usermod', '-a', '-G', daemoncgi_group, users_json['daemon_user']])
        users_json['daemoncgi_group'] = daemoncgi_group
        with submitty_users.open('w') as open_file:
            json.dump(users_json, open_file, indent=2)
