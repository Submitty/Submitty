"""Migration for deleting old crontab usage."""

from pathlib import Path


def up(config):
    """
    Deletes the old crontab file we used to use if it exists.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    cron_file = Path("/var", "spool", "cron", "crontabs", "submitty_daemon")
    if cron_file.exists():
        cron_file.unlink()


def down(config):
    """
    Run down migration (rollback).

    Delete the new style crontab

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    cron_file = Path("/etc", "cron.d", "submitty")
    if cron_file.exists():
        cron_file.unlink()
