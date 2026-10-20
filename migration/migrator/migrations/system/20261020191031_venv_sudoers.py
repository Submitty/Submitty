"""Migration for the Submitty system."""

import json
import os
import shutil
import subprocess
import tempfile

from pathlib import Path


def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    sudoers_path = "/etc/sudoers"

    COURSE_BUILDERS_GROUP = "submitty_course_builders"
    SUBMITTY_INSTALL_DIR = Path(config.submitty["submitty_install_dir"])

    WORKER = os.environ.get("IS_WORKER", "0")

    users_json_path = SUBMITTY_INSTALL_DIR / "config" / "submitty_users.json"
    with open(users_json_path, "r") as f:
        users_data = json.load(f)

    SUPERVISOR_USER = users_data.get("supervisor_user")

    temp_fd, temp_sudoers_path = tempfile.mkstemp()
    try:
        shutil.copy2(sudoers_path, temp_sudoers_path)

        # Append the new rules to the temp file
        with open(temp_sudoers_path, "a") as file:
            file.write(
                f"\n%{COURSE_BUILDERS_GROUP} ALL=(ALL:ALL) /usr/local/submitty/venv/bin/python3 {SUBMITTY_INSTALL_DIR}/bin/generate_repos.py\n"
            )
            file.write(
                f"%{COURSE_BUILDERS_GROUP} ALL=(ALL:ALL) /usr/local/submitty/venv/bin/python3 {SUBMITTY_INSTALL_DIR}/bin/grading_done.py\n"
            )
            file.write(
                f"%{COURSE_BUILDERS_GROUP} ALL=(ALL:ALL) /usr/local/submitty/venv/bin/python3 {SUBMITTY_INSTALL_DIR}/bin/regrade.py\n"
            )
            # If in worker mode, also append submitty user to /etc/sudoers
            if WORKER == "1" and SUPERVISOR_USER:
                file.write(
                    f"{SUPERVISOR_USER} ALL = (root) NOPASSWD: /usr/local/submitty/venv/bin/python3 {SUBMITTY_INSTALL_DIR}/sbin/shipper_utils/systemctl_wrapper.py\n"
                )

        # Use visudo to validate the temp file using -c to check syntax
        result =  subprocess.run(
            ["visudo", "-c", "-f", temp_sudoers_path], capture_output=True, text=True
        )

        if result.returncode != 0:
            raise RuntimeError(f"visudo validation failed, sudoers changes aborted:\n{result.stderr}")

        # Overwrite /etc/sudoers
        shutil.copy2(temp_sudoers_path, sudoers_path)

    finally:
        # Clean up temporary file
        os.close(temp_fd)
        if os.path.exists(temp_sudoers_path):
            os.remove(temp_sudoers_path)


def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    pass
