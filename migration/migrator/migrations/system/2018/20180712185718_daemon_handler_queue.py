import os
from pathlib import Path
import shutil


def up(config):
    jobs_dir = Path(config.submitty['submitty_data_dir'], 'daemon_job_queue')
    os.makedirs(str(jobs_dir), exist_ok=True)
    daemon_user = config.submitty_users['daemon_user']
    daemonphp_group = config.submitty_users['daemonphp_group']
    shutil.chown(str(jobs_dir), daemon_user, daemonphp_group)
    # sticky bit is the leading 2.
    # equivalent to u+rwx,g+rws,o-rwx
    os.chmod(str(jobs_dir),0o2770)
    pass


def down(config):
    pass
