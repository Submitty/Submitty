"""Delete a user's notifications and notification settings when the user is removed from the course."""


def up(config, database, semester, course):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    :param semester: Semester of the course being migrated
    :type semester: str
    :param course: Name of course being migrated
    :type course: str
    """
    database.execute("""
        ALTER TABLE notifications
        DROP CONSTRAINT IF EXISTS notifications_to_user_id_fkey,
        ADD CONSTRAINT notifications_to_user_id_fkey FOREIGN KEY (to_user_id)
            REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE
    """)
    database.execute("""
        ALTER TABLE notification_settings
        DROP CONSTRAINT IF EXISTS notification_settings_fkey,
        ADD CONSTRAINT notification_settings_fkey FOREIGN KEY (user_id)
            REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE
    """)


def down(config, database, semester, course):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    :param semester: Semester of the course being migrated
    :type semester: str
    :param course: Name of course being migrated
    :type course: str
    """
    database.execute("""
        ALTER TABLE notifications
        DROP CONSTRAINT IF EXISTS notifications_to_user_id_fkey,
        ADD CONSTRAINT notifications_to_user_id_fkey FOREIGN KEY (to_user_id)
            REFERENCES users(user_id) ON UPDATE CASCADE
    """)
    database.execute("""
        ALTER TABLE notification_settings
        DROP CONSTRAINT IF EXISTS notification_settings_fkey,
        ADD CONSTRAINT notification_settings_fkey FOREIGN KEY (user_id)
            REFERENCES users(user_id) ON UPDATE CASCADE
    """)
