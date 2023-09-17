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
        ALTER TABLE courses_users
        ADD COLUMN last_nonnull_registration_section VARCHAR;

        CREATE OR REPLACE FUNCTION public.update_last_nonnull_section()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
        BEGIN
            RETURN NEW;
        END;
        $$;

        CREATE TRIGGER before_update_courses_update_last_nonnull_section
        BEFORE UPDATE ON public.courses_users
        FOR EACH ROW EXECUTE PROCEDURE update_last_nonnull_section();
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
        ALTER TABLE courses_users
        DROP COLUMN last_nonnull_registration_section;

        DROP TRIGGER IF EXISTS
            before_update_courses_update_last_nonnull_section
            ON courses_users;
    """)
