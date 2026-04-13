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
CREATE OR REPLACE FUNCTION sync_delete_user() RETURNS TRIGGER AS $$
-- BEFORE DELETE trigger function to DELETE users from course DB.
DECLARE
    db_conn VARCHAR;
    query_string TEXT;
BEGIN
    db_conn := format('dbname=submitty_%s_%s', OLD.semester, OLD.course);
    -- Need to delete anon_id entry from gradeable_anon otherwise foreign key constraint will be violated and execution will fail
    query_string := 'DELETE FROM gradeable_anon WHERE user_id = ' || quote_literal(OLD.user_id) || '; '
                    || 'DELETE FROM users WHERE user_id = ' || quote_literal(OLD.user_id);
    -- Need to make sure that query_string was set properly as dblink_exec will happily take a null and then do nothing
    IF query_string IS NULL THEN
        RAISE EXCEPTION 'query_string error in trigger function sync_delete_user()';
    END IF;
    PERFORM dblink_exec(db_conn, query_string);

    -- All done.  As this is a BEFORE DELETE trigger, RETURN OLD allows original triggering DELETE query to proceed.
    RETURN OLD;

-- Trying to delete a user with existing data (via foreign keys) will raise an integrity constraint violation exception.
-- We should catch this exception and stop execution with no rows processed.
-- No rows processed will indicate that deletion had an error and did not occur.
EXCEPTION WHEN integrity_constraint_violation THEN
    -- Show that an exception occurred, and what was the exception.
    RAISE NOTICE 'User ''%'' still has existing data in course DB ''%''', OLD.user_id, substring(db_conn FROM 8);
    RAISE NOTICE '%', SQLERRM;
    -- Return NULL so we do not proceed with original triggering DELETE query.
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;""")


def down(config, database):
    """
    Run down migration (rollback).
    """
    database.execute("""
CREATE OR REPLACE FUNCTION sync_delete_user() RETURNS TRIGGER AS $$
-- BEFORE DELETE trigger function to DELETE users from course DB.
DECLARE
    db_conn VARCHAR;
    query_string TEXT;
BEGIN
    db_conn := format('dbname=submitty_%s_%s', OLD.semester, OLD.course);
    query_string := 'DELETE FROM users WHERE user_id = ' || quote_literal(OLD.user_id);
    -- Need to make sure that query_string was set properly as dblink_exec will happily take a null and then do nothing
    IF query_string IS NULL THEN
        RAISE EXCEPTION 'query_string error in trigger function sync_delete_user()';
    END IF;
    PERFORM dblink_exec(db_conn, query_string);

    -- All done.  As this is a BEFORE DELETE trigger, RETURN OLD allows original triggering DELETE query to proceed.
    RETURN OLD;

-- Trying to delete a user with existing data (via foreign keys) will raise an integrity constraint violation exception.
-- We should catch this exception and stop execution with no rows processed.
-- No rows processed will indicate that deletion had an error and did not occur.
EXCEPTION WHEN integrity_constraint_violation THEN
    -- Show that an exception occurred, and what was the exception.
    RAISE NOTICE 'User ''%'' still has existing data in course DB ''%''', OLD.user_id, substring(db_conn FROM 8);
    RAISE NOTICE '%', SQLERRM;
    -- Return NULL so we do not proceed with original triggering DELETE query.
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;""")
