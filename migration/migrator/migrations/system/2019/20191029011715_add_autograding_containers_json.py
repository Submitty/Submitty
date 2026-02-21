"""
Migration for the Submitty system.
Adds bool to specify if the machine is a worker to Submitty.config
"""
from pathlib import Path
import json
import os
import pwd
import shutil

def up(config):
    DAEMON_USER = 'submitty_daemon'

    autograding_containers = str(Path(config.submitty['submitty_install_dir'], 'config', 'autograding_containers.json'))

    if os.path.exists(autograding_containers):
        return

    container_dict = {
            "default": [
                          "submitty/clang:6.0",
                          "submitty/autograding-default:latest",
                          "submitty/java:8",
                          "submitty/java:11",
                          "submitty/python:3.6"
                       ]
        }

    with open(autograding_containers, 'w') as container_file:
        json.dump(container_dict, container_file, indent=4)

    shutil.chown(autograding_containers, 'root', pwd.getpwnam(DAEMON_USER).pw_gid)
    os.chmod(autograding_containers, 0o460)

# no need for down as email_enabled is not used in previous builds
def down(config):
    pass
