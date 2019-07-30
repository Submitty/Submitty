import os
import json
import grp
from pathlib import Path


def up(config):

    # email json now needs read access by the php user to know if emails are enabled
    email_json = Path(config.submitty['submitty_install_dir'], 'config', 'email.json')
    daemonphp_group = config.submitty_users['daemonphp_group']
    daemonphp_gid = grp.getgrnam(daemonphp_group).gr_gid

    # -r--r-----  1 root submitty_daemonphp     email.json
    os.chown(str(email_json),0,daemonphp_gid)
    os.chmod(str(email_json),0o440)


def down(config):
    pass
