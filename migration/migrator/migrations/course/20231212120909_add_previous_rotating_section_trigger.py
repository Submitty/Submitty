"""Migration for a given Submitty course database."""


def up(config, database, semester, course):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    :param semester: Semester of the course being migrated
    :type semester: str
    :param course: Code of course being migrated
    :type course: str
    """
    database.execute("""
        ALTER TABLE users -- Create column
        ADD COLUMN IF NOT EXISTS previous_rotating_section INTEGER;

        -- Create empty trigger function that is replaced by new trigger function file
        CREATE OR REPLACE FUNCTION public.update_previous_rotating_section()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
        BEGIN
            RETURN NEW;
        END;
        $$;

        -- Attatch trigger function
        CREATE TRIGGER before_update_users_update_previous_rotating_section
        BEFORE UPDATE OF rotating_section ON public.users
        FOR EACH ROW EXECUTE PROCEDURE update_previous_rotating_section();
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
    :param course: Code of course being migrated
    :type course: str
    """
    database.execute("""
        DROP TRIGGER IF EXISTS
            before_update_users_update_previous_rotating_section
            ON users;
    """)
