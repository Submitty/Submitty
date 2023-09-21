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

        UPDATE courses_users cu
        SET last_nonnull_registration_section=(
            CASE
                WHEN cu.registration_section IS NOT NULL
                    THEN cu.registration_section
                ELSE (
                    SELECT registration_section_id
                    FROM courses_registration_sections crs
                    WHERE crs.course = cu.course
                    ORDER BY registration_section_id ASC
                    LIMIT 1
                )
            END
        );

        ALTER TABLE courses_users
        ALTER COLUMN last_nonnull_registration_section
        SET NOT NULL;
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
