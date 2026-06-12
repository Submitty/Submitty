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
        CREATE TABLE IF NOT EXISTS notification_default (
            user_id character varying NOT NULL PRIMARY KEY,
            active boolean NOT NULL,
            merge_threads boolean DEFAULT false NOT NULL,
            all_new_threads boolean DEFAULT false NOT NULL,
            all_new_posts boolean DEFAULT false NOT NULL,
            all_modifications_forum boolean DEFAULT false NOT NULL,
            reply_in_post_thread boolean DEFAULT false NOT NULL,
            team_invite boolean DEFAULT true NOT NULL,
            team_joined boolean DEFAULT true NOT NULL,
            team_member_submission boolean DEFAULT true NOT NULL,
            self_notification boolean DEFAULT false NOT NULL,
            merge_threads_email boolean DEFAULT false NOT NULL,
            all_new_threads_email boolean DEFAULT false NOT NULL,
            all_new_posts_email boolean DEFAULT false NOT NULL,
            all_modifications_forum_email boolean DEFAULT false NOT NULL,
            reply_in_post_thread_email boolean DEFAULT false NOT NULL,
            team_invite_email boolean DEFAULT true NOT NULL,
            team_joined_email boolean DEFAULT true NOT NULL,
            team_member_submission_email boolean DEFAULT true NOT NULL,
            self_notification_email boolean DEFAULT false NOT NULL,
            self_registration_email boolean DEFAULT true NOT NULL,
            all_released_grades boolean DEFAULT true NOT NULL,
            all_released_grades_email boolean DEFAULT true NOT NULL,
            all_gradeable_releases boolean DEFAULT true NOT NULL,
            all_gradeable_releases_email boolean DEFAULT false NOT NULL
            );
        """
        )
    pass


def down(config, database):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    pass
