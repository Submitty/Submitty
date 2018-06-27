def up(conn):
    with conn.cursor() as cursor:
        cursor.execute("""
CREATE TABLE public.courses_registration_sections (
    semester character varying(255) NOT NULL,
    course character varying(255) NOT NULL,
    registration_section_id character varying(255) NOT NULL,
    CONSTRAINT courses_registration_sections_pkey PRIMARY KEY (semester, course, registration_section_id),
    CONSTRAINT courses_registration_sections_fkey FOREIGN KEY (semester, course) REFERENCES courses(semester, course) ON UPDATE CASCADE);""")

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
      RAISE EXCEPTION 'dblink_query set as NULL';
    END IF;
    PERFORM dblink_exec(db_conn, query_string);
  END LOOP;

  -- All done.
  RETURN NULL;
END;
$$ LANGUAGE plpgsql;""")

        cursor.execute('CREATE TRIGGER registration_sync_registration_id AFTER INSERT ON courses_registration_sections FOR EACH ROW EXECUTE PROCEDURE sync_registration_section();')

def down(conn):
    with conn.cursor() as cursor:
        cursor.execute('DROP TRIGGER registration_sync_registration_id;')
        cursor.execute('DROP FUNCTION sync_registration_section();')
        cursor.execute('DROP TABLE courses_registration_sections;')
