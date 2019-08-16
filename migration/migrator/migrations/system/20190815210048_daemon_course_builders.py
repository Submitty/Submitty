import os
import json
from pathlib import Path


def up(config):

    # Add the daemon_user to the course_builders_group so that it can
    # list the contents of the /var/local/submitty/courses directory
    # when searching for courses that subscribe to nightly rainbow
    # grades update.

    course_builders_group = config.submitty_users['course_builders_group']
    daemon_user = config.submitty_users['daemon_user']
    
    os.system("sudo usermod -a -G "+course_builders_group+" "+daemon_user)


    # And give the submitty_php user read access to the
    # submitty_users.json file (previously was only submitty_daemon)

    submitty_users_config_file = Path(config.submitty['submitty_install_dir'], 'config', 'submitty_users.json')
    daemonphp_group = config.submitty_users['daemonphp_group']

    os.system("sudo chgrp "+daemonphp_group+" "+str(submitty_users_config_file))

    pass


def down(config):
    pass
