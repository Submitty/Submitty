"""Migration for the Submitty system."""
import json
import os

def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    CONFIG_PATH = "/usr/local/submitty/config/submitty.json"

    with open(CONFIG_PATH, "r") as f:
        data = json.load(f)

    # Add the new setting if it doesn't exist
    if "file_upload_limit_mb" not in data:
        data["file_upload_limit_mb"] = 100  # Default limit: 50MB

        # Save the updated config
        with open(CONFIG_PATH, "w") as f:
            json.dump(data, f, indent=4)

        print("Migration complete: Added 'file_upload_limit_mb' to submitty.json")


def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    pass
