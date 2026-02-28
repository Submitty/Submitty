import os
import json
from pathlib import Path


def up(config):

    # And give the submitty_php user read access to the
    # submitty_users.json file (previously was only submitty_daemon)

    submitty_users_config_file = Path(config.submitty['submitty_install_dir'], 'config', 'submitty_users.json')
    daemonphp_group = config.submitty_users['daemonphp_group']

    os.system("sudo chgrp "+daemonphp_group+" "+str(submitty_users_config_file))

    pass


def down(config):
    pass
