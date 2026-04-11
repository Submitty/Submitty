"""Migration for the Submitty master database."""

def up(config, database):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    database.execute(
        """
        CREATE TABLE IF NOT EXISTS default_notification_settings (
            user_id character varying NOT NULL,
            merge_threads BOOLEAN DEFAULT FALSE NOT NULL,
            all_new_threads BOOLEAN DEFAULT FALSE NOT NULL,
            all_new_posts BOOLEAN DEFAULT FALSE NOT NULL,
            all_modifications_forum BOOLEAN DEFAULT FALSE NOT NULL,
            reply_in_post_thread BOOLEAN DEFAULT FALSE NOT NULL,
            team_invite BOOLEAN DEFAULT TRUE NOT NULL,
            team_joined BOOLEAN DEFAULT TRUE NOT NULL,
            team_member_submission BOOLEAN DEFAULT TRUE NOT NULL,
            self_notification BOOLEAN DEFAULT FALSE NOT NULL,
            all_released_grades BOOLEAN DEFAULT TRUE NOT NULL,
            all_gradeable_releases BOOLEAN DEFAULT TRUE NOT NULL,
            merge_threads_email BOOLEAN DEFAULT FALSE NOT NULL,
            all_new_threads_email BOOLEAN DEFAULT FALSE NOT NULL,
            all_new_posts_email BOOLEAN DEFAULT FALSE NOT NULL,
            all_modifications_forum_email BOOLEAN DEFAULT FALSE NOT NULL,
            reply_in_post_thread_email BOOLEAN DEFAULT FALSE NOT NULL,
            team_invite_email BOOLEAN DEFAULT TRUE NOT NULL,
            team_joined_email BOOLEAN DEFAULT TRUE NOT NULL,
            team_member_submission_email BOOLEAN DEFAULT TRUE NOT NULL,
            self_notification_email BOOLEAN DEFAULT FALSE NOT NULL,
            self_registration_email BOOLEAN DEFAULT TRUE NOT NULL,
            all_released_grades_email BOOLEAN DEFAULT TRUE NOT NULL,
            all_gradeable_releases_email BOOLEAN DEFAULT FALSE NOT NULL,
            CONSTRAINT default_notification_settings_pkey PRIMARY KEY (user_id),
            CONSTRAINT default_notification_settings_fkey FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE
        );
        """
    )


def down(config, database):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    database.execute("DROP TABLE IF EXISTS default_notification_settings;")
