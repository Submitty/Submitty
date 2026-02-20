"""Migration for the Submitty system."""

import os
import json

def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """

    preserve_path = "/usr/local/submitty/config/preserve_files_list.json"

    paths_to_preserve = [
        "/usr/local/submitty/site/public/img/submitty_logo.png",
        "/usr/local/submitty/site/public/img/submitty_banner.png",
    ]

    os.makedirs(os.path.dirname(preserve_path), exist_ok=True)
    with open(preserve_path, "w", encoding="utf-8") as file:
        json.dump(paths_to_preserve, file, indent=2)
        file.write("\n")

    pass


def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    pass
