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
        ADD COLUMN last_nonnull_rotating_section VARCHAR;

        -- Create empty trigger function that is replaced by new trigger function file
        CREATE OR REPLACE FUNCTION public.update_last_nonnull_rotating_section()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
        BEGIN
            RETURN NEW;
        END;
        $$;

        -- Attatch trigger function
        CREATE TRIGGER before_update_users_update_last_nonnull_rotating_section
        BEFORE UPDATE ON public.users
        FOR EACH ROW EXECUTE PROCEDURE update_last_nonnull_rotating_section();

        -- Set each existing user's last nonnull rotating section.
        -- Choose top section if in null as we have no other information.
        UPDATE users us
        SET last_nonnull_rotating_section=(
            CASE
                WHEN us.rotating_section IS NOT NULL
                    THEN us.rotating_section
                ELSE (
                    SELECT sections_rotating_id
                    FROM sections_rotating
                    ORDER BY sections_rotating_id ASC
                    LIMIT 1
                )
            END
        );

        -- No nonnull constraint on rotating_section as its possible no rotating sections.
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
        ALTER TABLE users
        DROP COLUMN last_nonnull_rotating_section;

        DROP TRIGGER IF EXISTS
            before_update_users_update_last_nonnull_rotating_section
            ON users;
    """)
