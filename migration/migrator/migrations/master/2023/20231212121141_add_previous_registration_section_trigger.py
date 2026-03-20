"""Migration for the Submitty master database."""


def up(config, database):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    database.execute("""
        ALTER TABLE courses_users -- Create column
        ADD COLUMN IF NOT EXISTS previous_registration_section VARCHAR(255);

        -- Create empty trigger function that is replaced by new trigger function file
        CREATE OR REPLACE FUNCTION public.update_previous_registration_section()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
        BEGIN
            RETURN NEW;
        END;
        $$;

        -- Attatch trigger function
        CREATE TRIGGER before_update_courses_update_previous_registration_section
        BEFORE UPDATE OF registration_section ON public.courses_users
        FOR EACH ROW EXECUTE PROCEDURE update_previous_registration_section();
    """)


def down(config, database):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    database.execute("""
        DROP TRIGGER IF EXISTS
            before_update_courses_update_previous_registration_section
            ON courses_users;
    """)
