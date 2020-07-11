"""Migration for the Submitty master database."""


def up(config, database):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """

    # Insert database function which propagates time zone data from master to courses
    # Required in this case because master already contains some data that wasn't synced to courses
    sql = """CREATE OR REPLACE FUNCTION propagate_users() RETURNS VOID AS $$
    DECLARE
        query_string TEXT;
        db_conn TEXT;
        row RECORD;
    BEGIN

        -- Add time_zone to course databases
        FOR row IN SELECT datname FROM pg_database WHERE datname ILIKE 'submitty\_%' LOOP

            db_conn := format('dbname=%s', row.datname);

            query_string := 'ALTER TABLE users ADD COLUMN IF NOT EXISTS time_zone VARCHAR NOT NULL DEFAULT ''NOT_SET/NOT_SET'';';
            PERFORM dblink_exec(db_conn, query_string);

        END LOOP;

        -- Propagate time zone from master to courses for any users that may have already set their time zone
        FOR row IN SELECT courses_users.user_id, courses_users.semester, courses_users.course, users.time_zone FROM courses_users, users WHERE users.user_id = courses_users.user_id LOOP

            db_conn := format('dbname=submitty_%s_%s', row.semester, row.course);
            query_string := 'UPDATE users SET time_zone = ' || quote_literal(row.time_zone) || ' WHERE user_id = ' || quote_literal(row.user_id);
            PERFORM dblink_exec(db_conn, query_string);

        END LOOP;

    END;
    $$ LANGUAGE plpgsql;"""
    database.execute(sql)

    # Call function to propagate user time zones
    database.execute('select propagate_users();')

    # Clean up by removing the function
    database.execute('DROP FUNCTION IF EXISTS propagate_users();')

    # Modify sync_users trigger
    sql = """CREATE OR REPLACE FUNCTION sync_user() RETURNS trigger AS
    -- TRIGGER function to sync users data on UPDATE of user_record in table users.
    -- NOTE: INSERT should not trigger this function as function sync_courses_users
    -- will also sync users -- but only on INSERT.
    $$
    DECLARE
        course_row RECORD;
        db_conn VARCHAR;
        query_string TEXT;
        preferred_name_change_details TEXT;
    BEGIN
        -- Check for changes in users.user_preferred_firstname and users.user_preferred_lastname.
        IF coalesce(OLD.user_preferred_firstname, '') <> coalesce(NEW.user_preferred_firstname, '') THEN
            preferred_name_change_details := format('PREFERRED_FIRSTNAME OLD: "%s" NEW: "%s" ', OLD.user_preferred_firstname, NEW.user_preferred_firstname);
        END IF;
        IF coalesce(OLD.user_preferred_lastname, '') <> coalesce(NEW.user_preferred_lastname, '') THEN
            preferred_name_change_details := format('%sPREFERRED_LASTNAME OLD: "%s" NEW: "%s"', preferred_name_change_details, OLD.user_preferred_lastname, NEW.user_preferred_lastname);
        END IF;
        -- If any preferred_name data has changed, preferred_name_change_details will not be NULL.
        IF preferred_name_change_details IS NOT NULL THEN
            preferred_name_change_details := format('USER_ID: "%s" %s', NEW.user_id, preferred_name_change_details);
            RAISE LOG USING MESSAGE = 'PREFERRED_NAME DATA UPDATE', DETAIL = preferred_name_change_details;
        END IF;
        -- Propagate UPDATE to course DBs
        FOR course_row IN SELECT semester, course FROM courses_users WHERE user_id=NEW.user_id LOOP
            RAISE NOTICE 'Semester: %, Course: %', course_row.semester, course_row.course;
            db_conn := format('dbname=submitty_%s_%s', course_row.semester, course_row.course);
            query_string := 'UPDATE users SET user_numeric_id=' || quote_nullable(NEW.user_numeric_id) || ', user_firstname=' || quote_literal(NEW.user_firstname) || ', user_preferred_firstname=' || quote_nullable(NEW.user_preferred_firstname) || ', user_lastname=' || quote_literal(NEW.user_lastname) || ', user_preferred_lastname=' || quote_nullable(NEW.user_preferred_lastname) || ', user_email=' || quote_literal(NEW.user_email) || ', time_zone=' || quote_literal(NEW.time_zone)  || ', user_updated=' || quote_literal(NEW.user_updated) || ', instructor_updated=' || quote_literal(NEW.instructor_updated) || ' WHERE user_id=' || quote_literal(NEW.user_id);
            -- Need to make sure that query_string was set properly as dblink_exec will happily take a null and then do nothing
            IF query_string IS NULL THEN
                RAISE EXCEPTION 'query_string error in trigger function sync_user()';
            END IF;
            PERFORM dblink_exec(db_conn, query_string);
        END LOOP;

        -- All done.
        RETURN NULL;
    END;
    $$ LANGUAGE plpgsql;"""
    database.execute(sql)


def down(config, database):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    pass
