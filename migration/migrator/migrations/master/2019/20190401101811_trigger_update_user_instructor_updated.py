"""Migration for the Submitty system."""

# NOTE: This migration is intended to be used with course/20190401101720_user_instructor_updated_fields.py
#       Once this migration and the course migration is done, you may issue the following SQL command in Postgres to sync user data:
#
#       UPDATE users SET user_updated=user_updated AND instructor_updated=instructor_updated;

def up(config, database):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """

    database.execute("""
CREATE OR REPLACE FUNCTION sync_courses_user() RETURNS TRIGGER AS
-- TRIGGER function to sync users data on INSERT or UPDATE of user_record in
-- table courses_user.
$$
DECLARE
    user_row record;
    db_conn varchar;
    query_string text;
BEGIN
    db_conn := format('dbname=submitty_%s_%s', NEW.semester, NEW.course);

    IF (TG_OP = 'INSERT') THEN
        -- FULL data sync on INSERT of a new user record.
        SELECT * INTO user_row FROM users WHERE user_id=NEW.user_id;
        query_string := 'INSERT INTO users (user_id, user_firstname, user_preferred_firstname, user_lastname, user_preferred_lastname, user_email, user_updated, instructor_updated, user_group, registration_section, manual_registration) ' ||
                        'VALUES (' || quote_literal(user_row.user_id) || ', ' || quote_literal(user_row.user_firstname) || ', ' || quote_nullable(user_row.user_preferred_firstname) || ', ' || quote_literal(user_row.user_lastname) || ', ' ||
                        '' || quote_nullable(user_row.user_preferred_lastname) || ', ' || quote_literal(user_row.user_email) || ', ' || user_row.user_updated || ', ' || user_row.instructor_updated || ', ' || NEW.user_group || ', ' ||
                        '' || quote_nullable(NEW.registration_section) || ', ' || NEW.manual_registration || ')';
        IF query_string IS NULL THEN
            RAISE EXCEPTION 'query_string error in trigger function sync_courses_user() when doing INSERT';
        END IF;
        PERFORM dblink_exec(db_conn, query_string);
    ELSIF (TG_OP = 'UPDATE') THEN
        -- User update on registration_section
        -- CASE clause ensures user's rotating section is set NULL when
        -- registration is updated to NULL.  (e.g. student has dropped)
        query_string = 'UPDATE users SET user_group=' || NEW.user_group || ', registration_section=' || quote_nullable(NEW.registration_section) || ', rotating_section=' || CASE WHEN NEW.registration_section IS NULL THEN 'null' ELSE 'rotating_section' END || ', manual_registration=' || NEW.manual_registration || ' WHERE user_id=' || QUOTE_LITERAL(NEW.user_id);
        IF query_string IS NULL THEN
            RAISE EXCEPTION 'query_string error in trigger function sync_courses_user() when doing UPDATE';
        END IF;
        PERFORM dblink_exec(db_conn, query_string);
    END IF;

    -- All done.
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;""")

    database.execute("""
CREATE OR REPLACE FUNCTION sync_user() RETURNS trigger AS
-- TRIGGER function to sync users data on INSERT or UPDATE of user_record in
-- table users.  NOTE: INSERT should not trigger this function as function
-- sync_courses_users will also sync users -- but only on INSERT.
$$
DECLARE
    course_row RECORD;
    db_conn VARCHAR;
    query_string TEXT;
BEGIN
    FOR course_row IN SELECT semester, course FROM courses_users WHERE user_id=NEW.user_id LOOP
        RAISE NOTICE 'Semester: %, Course: %', course_row.semester, course_row.course;
        db_conn := format('dbname=submitty_%s_%s', course_row.semester, course_row.course);
        query_string := 'UPDATE users SET user_firstname=' || quote_literal(NEW.user_firstname) || ', user_preferred_firstname=' || quote_nullable(NEW.user_preferred_firstname) || ', user_lastname=' || quote_literal(NEW.user_lastname) || ', user_preferred_lastname=' || quote_nullable(NEW.user_preferred_lastname) || ', user_email=' || quote_literal(NEW.user_email) || ', user_updated=' || quote_literal(NEW.user_updated) || ', instructor_updated=' || quote_literal(NEW.instructor_updated) || ' WHERE user_id=' || quote_literal(NEW.user_id);
        -- Need to make sure that query_string was set properly as dblink_exec will happily take a null and then do nothing
        IF query_string IS NULL THEN
            RAISE EXCEPTION 'query_string error in trigger function sync_user()';
        END IF;
        PERFORM dblink_exec(db_conn, query_string);
    END LOOP;

    -- All done.
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;""")

def down(config, database):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    database.execute("""
CREATE OR REPLACE FUNCTION sync_user() RETURNS trigger AS
-- TRIGGER function to sync users data on INSERT or UPDATE of user_record in
-- table users.  NOTE: INSERT should not trigger this function as function
-- sync_courses_users will also sync users -- but only on INSERT.
$$
DECLARE
    course_row RECORD;
    db_conn VARCHAR;
    query_string TEXT;
BEGIN
    FOR course_row IN SELECT semester, course FROM courses_users WHERE user_id=NEW.user_id LOOP
        RAISE NOTICE 'Semester: %, Course: %', course_row.semester, course_row.course;
        db_conn := format('dbname=submitty_%s_%s', course_row.semester, course_row.course);
        query_string := 'UPDATE users SET user_firstname=' || quote_literal(NEW.user_firstname) || ', user_preferred_firstname=' || quote_nullable(NEW.user_preferred_firstname) || ', user_lastname=' || quote_literal(NEW.user_lastname) || ', user_preferred_lastname=' || quote_nullable(NEW.user_preferred_lastname) || ', user_email=' || quote_literal(NEW.user_email) || ' WHERE user_id=' || quote_literal(NEW.user_id);
        -- Need to make sure that query_string was set properly as dblink_exec will happily take a null and then do nothing
        IF query_string IS NULL THEN
            RAISE EXCEPTION 'query_string error in trigger function sync_user()';
        END IF;
        PERFORM dblink_exec(db_conn, query_string);
    END LOOP;

    -- All done.
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;""")

    database.execute("""
CREATE OR REPLACE FUNCTION sync_courses_user() RETURNS TRIGGER AS
-- TRIGGER function to sync users data on INSERT or UPDATE of user_record in
-- table courses_user.
$$
DECLARE
  user_row record;
  db_conn varchar;
  query_string text;
BEGIN
  db_conn := format('dbname=submitty_%s_%s', NEW.semester, NEW.course);

  IF (TG_OP = 'INSERT') THEN
    -- FULL data sync on INSERT of a new user record.
    SELECT * INTO user_row FROM users WHERE user_id=NEW.user_id;
    query_string := 'INSERT INTO users (user_id, user_firstname, user_preferred_firstname, user_lastname, user_preferred_lastname, user_email, user_group, registration_section, manual_registration) ' ||
                    'VALUES (' || quote_literal(user_row.user_id) || ', ' || quote_literal(user_row.user_firstname) || ', ' || quote_nullable(user_row.user_preferred_firstname) || ', ' ||
                    '' || quote_literal(user_row.user_lastname) || ', ' || quote_nullable(user_row.user_preferred_lastname) || ', ' || quote_literal(user_row.user_email) || ', ' || NEW.user_group || ', ' || quote_nullable(NEW.registration_section) || ', ' || NEW.manual_registration || ')';
    IF query_string IS NULL THEN
      RAISE EXCEPTION 'query_string error in trigger function sync_courses_user() when doing INSERT';
    END IF;
    PERFORM dblink_exec(db_conn, query_string);

  ELSE
    -- User update on registration_section
    -- CASE clause ensures user's rotating section is set NULL when
    -- registration is updated to NULL.  (e.g. student has dropped)
    query_string = 'UPDATE users SET user_group=' || NEW.user_group || ', registration_section=' || quote_nullable(NEW.registration_section) || ', rotating_section=' || CASE WHEN NEW.registration_section IS NULL THEN 'null' ELSE 'rotating_section' END || ', manual_registration=' || NEW.manual_registration || ' WHERE user_id=' || QUOTE_LITERAL(NEW.user_id);
    IF query_string IS NULL THEN
      RAISE EXCEPTION 'query_string error in trigger function sync_courses_user() when doing UPDATE';
    END IF;
    PERFORM dblink_exec(db_conn, query_string);
  END IF;

  -- All done.
  RETURN NULL;
END;
$$ LANGUAGE plpgsql;""")
