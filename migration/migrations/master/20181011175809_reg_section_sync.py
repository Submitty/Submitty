def up(config, conn):
    with conn.cursor() as cursor:
        cursor.execute("DROP TRIGGER IF EXISTS registration_sync_registration_id ON courses_registration_sections;")
        cursor.execute("DROP FUNCTION IF EXISTS sync_registration_section();")
        cursor.execute("""
CREATE OR REPLACE FUNCTION sync_insert_registration_section() RETURNS trigger AS $$
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
$$ LANGUAGE plpgsql;""")

        cursor.execute("""
CREATE OR REPLACE FUNCTION sync_delete_registration_section() RETURNS TRIGGER AS $$
-- BEFORE DELETE trigger function to DELETE registration sections from course DB, as needed.
DECLARE
    registration_row RECORD;
    db_conn VARCHAR;
    query_string TEXT;
BEGIN
    db_conn := format('dbname=submitty_%s_%s', OLD.semester, OLD.course);
    query_string := 'DELETE FROM sections_registration WHERE sections_registration_id = ' || quote_literal(OLD.registration_section_id);
    -- Need to make sure that query_string was set properly as dblink_exec will happily take a null and then do nothing
    IF query_string IS NULL THEN
        RAISE EXCEPTION 'query_string error in trigger function sync_delete_registration_section()';
    END IF;
    PERFORM dblink_exec(db_conn, query_string);

    -- All done.  As this is a BEFORE DELETE trigger, RETURN OLD allows original triggering DELETE query to proceed.
    RETURN OLD;

-- Trying to delete a registration section while users are still enrolled will raise an integrity constraint violation exception.
-- We should catch this exception and stop execution with no rows processed.
-- No rows processed will indicate to the UsersController that deletion had an error and did not occur.
EXCEPTION WHEN integrity_constraint_violation THEN
    RAISE NOTICE 'Users are still enrolled in registration section ''%''', OLD.registration_section_id;
    -- Return NULL so we do not proceed with original triggering DELETE query.
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;""")

        cursor.execute("CREATE TRIGGER insert_sync_registration_id AFTER INSERT OR UPDATE ON courses_registration_sections FOR EACH ROW EXECUTE PROCEDURE sync_insert_registration_section();")
        cursor.execute("CREATE TRIGGER delete_sync_registration_id BEFORE DELETE ON courses_registration_sections FOR EACH ROW EXECUTE PROCEDURE sync_delete_registration_section();")

def down(config, conn):
    with conn.cursor() as cursor:
        cursor.execute("DROP TRIGGER IF EXISTS insert_registration_sync_registration_id ON courses_registration_sections;")
        cursor.execute("DROP TRIGGER IF EXISTS delete_registration_sync_registration_id ON courses_registration_sections;")
        cursor.execute("DROP FUNCTION IF EXISTS sync_insert_registration_section();")
        cursor.execute("DROP FUNCTION IF EXISTS sync_delete_registration_section();")
        cursor.execute("""
CREATE OR REPLACE FUNCTION sync_registration_section() RETURNS trigger AS
-- TRIGGER function to INSERT registration sections to course DB, as needed.
$$
DECLARE
  registration_row RECORD;
  db_conn VARCHAR;
  query_string TEXT;
BEGIN
  FOR registration_row IN SELECT semester, course FROM courses_registration_sections WHERE registration_section_id=NEW.registration_section_id LOOP
    db_conn := format('dbname=submitty_%s_%s', registration_row.semester, registration_row.course);
    query_string := 'INSERT INTO sections_registration VALUES(' || quote_literal(NEW.registration_section_id) || ') ON CONFLICT DO NOTHING';
    -- Need to make sure that query_string was set properly as dblink_exec will happily take a null and then do nothing
    IF query_string IS NULL THEN
      RAISE EXCEPTION 'query_string error in trigger function sync_registration_section()';
    END IF;
    PERFORM dblink_exec(db_conn, query_string);
  END LOOP;

  -- All done.
  RETURN NULL;
END;
$$ LANGUAGE plpgsql;""")

        cursor.execute("CREATE TRIGGER registration_sync_registration_id AFTER INSERT ON courses_registration_sections FOR EACH ROW EXECUTE PROCEDURE sync_registration_section();")
