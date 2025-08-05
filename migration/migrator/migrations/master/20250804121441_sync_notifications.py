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
        ALTER TABLE users ADD COLUMN notifications_synced BOOLEAN DEFAULT FALSE NOT NULL;
        ALTER TABLE users ADD COLUMN notifications_synced_update TIMESTAMP WITH TIME ZONE DEFAULT NULL;

        ALTER TABLE users ADD COLUMN notification_defaults VARCHAR(255) DEFAULT NULL;

        CREATE OR REPLACE FUNCTION update_notification_defaults_on_course_updates()
        RETURNS TRIGGER AS $$
        BEGIN
            -- Handle dangling tuples within notification_defaults for updated or missing courses
            IF TG_OP = 'UPDATE' THEN
                IF OLD.term != NEW.term OR OLD.course != NEW.course THEN
                    UPDATE users
                    SET notification_defaults = NEW.term || '-' || NEW.course
                    WHERE notification_defaults = OLD.term || '-' || OLD.course;
                END IF;
                RETURN NEW;
            END IF;

            IF TG_OP = 'DELETE' THEN
                UPDATE users
                SET notification_defaults = NULL
                WHERE notification_defaults = OLD.term || '-' || OLD.course;
                RETURN OLD;
            END IF;
            RETURN NULL;
        END;
        $$ LANGUAGE plpgsql;

        CREATE TRIGGER course_notification_defaults_update_trigger
            AFTER UPDATE ON courses
            FOR EACH ROW
            EXECUTE FUNCTION update_notification_defaults_on_course_updates();

        CREATE TRIGGER course_notification_defaults_delete_trigger
            AFTER DELETE ON courses
            FOR EACH ROW
            EXECUTE FUNCTION update_notification_defaults_on_course_updates();
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
    database.execute(
        """
        DROP TRIGGER IF EXISTS course_notification_defaults_update_trigger ON courses;
        DROP TRIGGER IF EXISTS course_notification_defaults_delete_trigger ON courses;

        DROP FUNCTION IF EXISTS update_notification_defaults_on_course_updates();

        ALTER TABLE users DROP COLUMN IF EXISTS notifications_synced;
        ALTER TABLE users DROP COLUMN IF EXISTS notifications_synced_update;
        ALTER TABLE users DROP COLUMN IF EXISTS notification_defaults;
        """
    )
