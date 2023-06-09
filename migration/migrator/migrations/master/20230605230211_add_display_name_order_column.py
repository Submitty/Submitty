"""Migration for the Submitty master database."""


def up(config, database):
    """
    Run up migration.
    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    # add column
    sql0 = "ALTER TABLE users ADD COLUMN IF NOT EXISTS display_name_order character varying(255) NOT NULL DEFAULT 'GIVEN_F';"

    # update user trigger
    sql1 = """CREATE OR REPLACE FUNCTION public.sync_user() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
        DECLARE
            course_row RECORD;
            db_conn VARCHAR;
            query_string TEXT;
            preferred_name_change_details TEXT;
        BEGIN
            -- Check for changes in users.user_preferred_givenname and users.user_preferred_familyname.
            IF coalesce(OLD.user_preferred_givenname, '') <> coalesce(NEW.user_preferred_givenname, '') THEN
                preferred_name_change_details := format('PREFERRED_GIVENNAME OLD: "%s" NEW: "%s" ', OLD.user_preferred_givenname, NEW.user_preferred_givenname);
            END IF;
            IF coalesce(OLD.user_preferred_familyname, '') <> coalesce(NEW.user_preferred_familyname, '') THEN
                preferred_name_change_details := format('%sPREFERRED_FAMILYNAME OLD: "%s" NEW: "%s"', preferred_name_change_details, OLD.user_preferred_familyname, NEW.user_preferred_familyname);
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
                query_string := 'UPDATE users SET user_numeric_id=' || quote_nullable(NEW.user_numeric_id) || ', user_pronouns=' || quote_literal(NEW.user_pronouns) || ', user_givenname=' || quote_literal(NEW.user_givenname) || ', user_preferred_givenname=' || quote_nullable(NEW.user_preferred_givenname) || ', user_familyname=' || quote_literal(NEW.user_familyname) || ', user_preferred_familyname=' || quote_nullable(NEW.user_preferred_familyname) || ', user_last_initial_format=' || quote_literal(NEW.user_last_initial_format) || ', user_email=' || quote_literal(NEW.user_email) || ', user_email_secondary=' || quote_literal(NEW.user_email_secondary) || ',user_email_secondary_notify=' || quote_literal(NEW.user_email_secondary_notify) || ', time_zone=' || quote_literal(NEW.time_zone)  || ', display_image_state=' || quote_literal(NEW.display_image_state)  || ', display_name_order=' || quote_literal(NEW.display_name_order)  || ', user_updated=' || quote_literal(NEW.user_updated) || ', instructor_updated=' || quote_literal(NEW.instructor_updated) || ' WHERE user_id=' || quote_literal(NEW.user_id);
                
                -- Need to make sure that query_string was set properly as dblink_exec will happily take a null and then do nothing
                IF query_string IS NULL THEN
                    RAISE EXCEPTION 'query_string error in trigger function sync_user()';
                END IF;
                PERFORM dblink_exec(db_conn, query_string);
            END LOOP;
            -- All done.
            RETURN NULL;
        END;
        $$;"""


    # update courses_user trigger
    sql2 = """CREATE OR REPLACE FUNCTION public.sync_courses_user() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    DECLARE
        user_row record;
        db_conn varchar;
        query_string text;
    BEGIN
        db_conn := format('dbname=submitty_%s_%s', NEW.semester, NEW.course);
        IF (TG_OP = 'INSERT') THEN
            -- FULL data sync on INSERT of a new user record.
            SELECT * INTO user_row FROM users WHERE user_id=NEW.user_id;
            query_string := 'INSERT INTO users (user_id, user_pronouns, user_numeric_id, user_givenname, user_preferred_givenname, user_familyname, user_preferred_familyname, user_last_initial_format, user_email, user_email_secondary, user_email_secondary_notify, user_updated, instructor_updated, user_group, registration_section, registration_type, manual_registration, display_name_order) ' ||
                    'VALUES (' || quote_literal(user_row.user_id) || ', ' || quote_literal(user_row.user_pronouns) || ', ' || quote_nullable(user_row.user_numeric_id) || ', ' || quote_literal(user_row.user_givenname) || ', ' || quote_nullable(user_row.user_preferred_givenname) || ', ' || quote_literal(user_row.user_familyname) || ', ' ||
                    '' || quote_nullable(user_row.user_preferred_familyname) || ', ' || quote_literal(user_row.user_last_initial_format) || ', ' || quote_literal(user_row.user_email) || ', ' || quote_literal(user_row.user_email_secondary) || ', ' || quote_literal(user_row.user_email_secondary_notify) || ', ' || quote_literal(user_row.user_updated) || ', ' || quote_literal(user_row.instructor_updated) || ', ' ||
                    '' || NEW.user_group || ', ' || quote_nullable(NEW.registration_section) || ', ' || quote_literal(NEW.registration_type) || ', ' || NEW.manual_registration || ', ' || quote_literal(user_row.display_name_order) || ')';
            IF query_string IS NULL THEN
                RAISE EXCEPTION 'query_string error in trigger function sync_courses_user() when doing INSERT';
            END IF;
            PERFORM dblink_exec(db_conn, query_string);
        ELSIF (TG_OP = 'UPDATE') THEN
            -- User update on registration_section
            -- CASE clause ensures user's rotating section is set NULL when
            -- registration is updated to NULL.  (e.g. student has dropped)
            query_string = 'UPDATE users SET user_group=' || NEW.user_group || ', registration_section=' || quote_nullable(NEW.registration_section) || ', rotating_section=' || CASE WHEN NEW.registration_section IS NULL THEN 'null' ELSE 'rotating_section' END || ', registration_type=' || quote_literal(NEW.registration_type) || ', manual_registration=' || NEW.manual_registration || ' WHERE user_id=' || QUOTE_LITERAL(NEW.user_id);
            IF query_string IS NULL THEN
                RAISE EXCEPTION 'query_string error in trigger function sync_courses_user() when doing UPDATE';
            END IF;
            PERFORM dblink_exec(db_conn, query_string);
        END IF;
        -- All done.
        RETURN NULL;
    END;
    $$;"""
    sql = sql0 + sql1 + sql2
    database.execute(sql)
    pass


def down(config, database):
    """
    Run down migration (rollback).
    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    # update user trigger
    sql1 = """CREATE OR REPLACE FUNCTION public.sync_user() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
        DECLARE
            course_row RECORD;
            db_conn VARCHAR;
            query_string TEXT;
            preferred_name_change_details TEXT;
        BEGIN
            -- Check for changes in users.user_preferred_givenname and users.user_preferred_familyname.
            IF coalesce(OLD.user_preferred_givenname, '') <> coalesce(NEW.user_preferred_givenname, '') THEN
                preferred_name_change_details := format('PREFERRED_GIVENNAME OLD: "%s" NEW: "%s" ', OLD.user_preferred_givenname, NEW.user_preferred_givenname);
            END IF;
            IF coalesce(OLD.user_preferred_familyname, '') <> coalesce(NEW.user_preferred_familyname, '') THEN
                preferred_name_change_details := format('%sPREFERRED_FAMILYNAME OLD: "%s" NEW: "%s"', preferred_name_change_details, OLD.user_preferred_familyname, NEW.user_preferred_familyname);
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
                query_string := 'UPDATE users SET user_numeric_id=' || quote_nullable(NEW.user_numeric_id) || ', user_pronouns=' || quote_literal(NEW.user_pronouns) || ', user_givenname=' || quote_literal(NEW.user_givenname) || ', user_preferred_givenname=' || quote_nullable(NEW.user_preferred_givenname) || ', user_familyname=' || quote_literal(NEW.user_familyname) || ', user_preferred_familyname=' || quote_nullable(NEW.user_preferred_familyname) || ', user_email=' || quote_literal(NEW.user_email) || ', user_email_secondary=' || quote_literal(NEW.user_email_secondary) || ',user_email_secondary_notify=' || quote_literal(NEW.user_email_secondary_notify) || ', time_zone=' || quote_literal(NEW.time_zone)  || ', display_image_state=' || quote_literal(NEW.display_image_state)  || ', user_updated=' || quote_literal(NEW.user_updated) || ', instructor_updated=' || quote_literal(NEW.instructor_updated) || ' WHERE user_id=' || quote_literal(NEW.user_id);
                -- Need to make sure that query_string was set properly as dblink_exec will happily take a null and then do nothing
                IF query_string IS NULL THEN
                    RAISE EXCEPTION 'query_string error in trigger function sync_user()';
                END IF;
                PERFORM dblink_exec(db_conn, query_string);
            END LOOP;
            -- All done.
            RETURN NULL;
        END;
        $$;"""

    # update courses_user trigger
    sql2 = """CREATE OR REPLACE FUNCTION public.sync_courses_user() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    DECLARE
        user_row record;
        db_conn varchar;
        query_string text;
    BEGIN
        db_conn := format('dbname=submitty_%s_%s', NEW.semester, NEW.course);
        IF (TG_OP = 'INSERT') THEN
            -- FULL data sync on INSERT of a new user record.
            SELECT * INTO user_row FROM users WHERE user_id=NEW.user_id;
            query_string := 'INSERT INTO users (user_id, user_pronouns, user_numeric_id, user_givenname, user_preferred_givenname, user_familyname, user_preferred_familyname, user_last_initial_format, user_email, user_email_secondary, user_email_secondary_notify, user_updated, instructor_updated, user_group, registration_section, registration_type, manual_registration, display_name_order) ' ||
                    'VALUES (' || quote_literal(user_row.user_id) || ', ' || quote_literal(user_row.user_pronouns) || ', ' || quote_nullable(user_row.user_numeric_id) || ', ' || quote_literal(user_row.user_givenname) || ', ' || quote_nullable(user_row.user_preferred_givenname) || ', ' || quote_literal(user_row.user_familyname) || ', ' ||
                    '' || quote_nullable(user_row.user_preferred_familyname) || ', ' || quote_literal(user_row.user_last_initial_format) || ', ' || quote_literal(user_row.user_email) || ', ' || quote_literal(user_row.user_email_secondary) || ', ' || quote_literal(user_row.user_email_secondary_notify) || ', ' || quote_literal(user_row.user_updated) || ', ' || quote_literal(user_row.instructor_updated) || ', ' ||
                    '' || NEW.user_group || ', ' || quote_nullable(NEW.registration_section) || ', ' || quote_literal(NEW.registration_type) || ', ' || NEW.manual_registration || ')';
            IF query_string IS NULL THEN
                RAISE EXCEPTION 'query_string error in trigger function sync_courses_user() when doing INSERT';
            END IF;
            PERFORM dblink_exec(db_conn, query_string);
        ELSIF (TG_OP = 'UPDATE') THEN
            -- User update on registration_section
            -- CASE clause ensures user's rotating section is set NULL when
            -- registration is updated to NULL.  (e.g. student has dropped)
            query_string = 'UPDATE users SET user_group=' || NEW.user_group || ', registration_section=' || quote_nullable(NEW.registration_section) || ', rotating_section=' || CASE WHEN NEW.registration_section IS NULL THEN 'null' ELSE 'rotating_section' END || ', registration_type=' || quote_literal(NEW.registration_type) || ', manual_registration=' || NEW.manual_registration || ' WHERE user_id=' || QUOTE_LITERAL(NEW.user_id);
            IF query_string IS NULL THEN
                RAISE EXCEPTION 'query_string error in trigger function sync_courses_user() when doing UPDATE';
            END IF;
            PERFORM dblink_exec(db_conn, query_string);
        END IF;
        -- All done.
        RETURN NULL;
    END;
    $$;"""
    sql = sql1 + sql2
    database.execute(sql)

    pass