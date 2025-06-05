"""Migration for the Submitty system."""
import json
import os
from pathlib import Path

def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    CONFIG_PATH = Path(config.submitty['submitty_install_dir'], 'config', 'submitty.json')
    
    with CONFIG_PATH.open() as f:
        data = json.load(f)

    # Add the new setting if it doesn't exist
    if "course_material_file_upload_limit_mb" not in data:
        data["course_material_file_upload_limit_mb"] = 100  # Default limit: 100MB

        # Save the updated config
        with open(CONFIG_PATH, "w") as f:
            json.dump(data, f, indent=4)

def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    pass
