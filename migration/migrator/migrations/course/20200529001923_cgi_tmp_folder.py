import os
import grp
from pathlib import Path


def up(config, database, semester, course):
    tmp_cgi_dir = Path(config.submitty['submitty_data_dir'], 'tmp', 'cgi')

    # create the directories
    os.makedirs(str(tmp_cgi_dir), exist_ok=True)

    php_user = config.submitty_users['php_user']
    daemoncgi_group = config.submitty_users['daemoncgi_group']

    # set the owner/group/permissions
    chown_res = subprocess.check_output(["chown", "-R", php_user + ":" + daemoncgi_group + " ", str(tmp_cgi_dir)])
    chmod_user_res = subprocess.check_output(["chmod", "-R", "u+rwx", str(tmp_cgi_dir)])
    chmod_group_res = subprocess.check_output(["chmod", "-R", "g+rwxs", str(tmp_cgi_dir)])
    chmod_other_res = subprocess.check_output(["chmod", "-R", "o-rwx", str(tmp_cgi_dir)])

def down(config, database, semester, course):
    pass
