"""Migration for the Submitty master database."""


def up(config, database):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    database.execute("ALTER TABLE ONLY emails DROP CONSTRAINT IF EXISTS emails_user_id_fk;")
    database.execute("ALTER TABLE ONLY emails ADD CONSTRAINT emails_user_id_fk FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE;")

    database.execute("""
CREATE OR REPLACE FUNCTION sync_delete_user() RETURNS TRIGGER AS $$
-- BEFORE DELETE trigger function to DELETE users from course DB.
DECLARE
    user_courses INTEGER;
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

    SELECT COUNT(*) INTO user_courses FROM courses_users WHERE user_id = OLD.user_id;
    IF user_courses = 1 THEN
        DELETE FROM users WHERE user_id = OLD.user_id;
    END IF;

    -- All done.  As this is a BEFORE DELETE trigger, RETURN OLD allows original triggering DELETE query to proceed.
    RETURN OLD;

-- Trying to delete a user with existing data (via foreign keys) will raise an integrity constraint violation exception.
-- We should catch this exception and stop execution with no rows processed.
-- No rows processed will indicate that deletion had an error and did not occur.
EXCEPTION WHEN integrity_constraint_violation THEN
    RAISE NOTICE 'User still has existing data in course DB ''%''', substring(db_conn FROM 7);
    -- Return NULL so we do not proceed with original triggering DELETE query.
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;""")

    database.execute("CREATE TRIGGER before_delete_sync_delete_user BEFORE DELETE ON courses_users FOR EACH ROW EXECUTE PROCEDURE sync_delete_user();")

def down(config, database):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    database.execute("DROP TRIGGER IF EXISTS before_delete_sync_user_delete ON courses_users;")
    database.execute("DROP FUNCTION IF EXISTS sync_delete_user();")
    database.execute("ALTER TABLE ONLY emails DROP CONSTRAINT IF EXISTS emails_user_id_fk;")
    database.execute("ALTER TABLE ONLY emails ADD CONSTRAINT emails_user_id_fk FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE;")
