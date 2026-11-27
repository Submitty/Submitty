"""Migration for the Submitty master database."""


def up(config, database):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    # sbin/send_email.py runs every minute. It looks up unsent emails in id order
    # and counts the emails sent in the last hour, and without these indexes both
    # scan every email kept on the server.
    database.execute(
        "CREATE INDEX IF NOT EXISTS emails_unsent_idx ON emails (id) WHERE sent IS NULL"
    )
    database.execute("CREATE INDEX IF NOT EXISTS emails_sent_idx ON emails (sent)")


def down(config, database):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    database.execute("DROP INDEX IF EXISTS emails_unsent_idx")
    database.execute("DROP INDEX IF EXISTS emails_sent_idx")
