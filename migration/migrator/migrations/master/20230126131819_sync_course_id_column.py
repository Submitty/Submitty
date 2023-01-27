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
CREATE OR REPLACE FUNCTION public.sync_insert_registration_section() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
-- AFTER INSERT trigger function to INSERT registration sections to course DB, as needed.
DECLARE
    registration_row RECORD;
    db_conn VARCHAR;
    query_string TEXT;
BEGIN
    db_conn := format('dbname=submitty_%s_%s', NEW.semester, NEW.course);

    IF (TG_OP = 'INSERT') THEN
        query_string := 'INSERT INTO sections_registration (sections_registration_id, course_id) VALUES(' || quote_literal(NEW.registration_section_id) || ',' || quote_literal(NEW.course_id) || ')';
        -- Need to make sure that query_string was set properly as dblink_exec will happily take a null and then do nothing
        IF query_string IS NULL THEN
            RAISE EXCEPTION 'query_string error in trigger function sync_insert_registration_section() when doing INSERT';
        END IF;
        PERFORM dblink_exec(db_conn, query_string);

    ELSIF (TG_OP = 'UPDATE') THEN
        query_string := 'UPDATE sections_registration SET course_id=' || quote_literal(NEW.course_id) || ' WHERE sections_registration_id=' || quote_literal(NEW.registration_section_id);
        -- Need to make sure that query_string was set properly as dblink_exec will happily take a null and then do nothing
        IF query_string IS NULL THEN
            RAISE EXCEPTION 'query_string error in trigger function sync_insert_registration_section() when doing UPDATE';
        END IF;
        PERFORM dblink_exec(db_conn, query_string);
    END IF;

    -- All done.
    RETURN NULL;
END;
$$;
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
CREATE OR REPLACE FUNCTION public.sync_insert_registration_section() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
-- AFTER INSERT trigger function to INSERT registration sections to course DB, as needed.
DECLARE
    registration_row RECORD;
    db_conn VARCHAR;
    query_string TEXT;
BEGIN
    db_conn := format('dbname=submitty_%s_%s', NEW.semester, NEW.course);
    query_string := 'INSERT INTO sections_registration VALUES(' || quote_literal(NEW.registration_section_id) || ') ON CONFLICT DO NOTHING';
    -- Need to make sure that query_string was set properly as dblink_exec will happily take a null and then do nothing
    IF query_string IS NULL THEN
        RAISE EXCEPTION 'query_string error in trigger function sync_insert_registration_section()';
    END IF;
    PERFORM dblink_exec(db_conn, query_string);

    -- All done.
    RETURN NULL;
END;
$$;
""")
