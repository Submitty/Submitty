def up(conn):
    with conn.cursor() as cursor:
        cursor.execute("""
CREATE TABLE public.courses_registration_sections (
    semester character varying(255) NOT NULL,
    course character varying(255) NOT NULL,
    registration_section_id character varying(255) NOT NULL,
    PRIMARY KEY (semester, course, registration_section_id),
    FOREIGN KEY (semester, course) REFERENCES public.courses(semester, course) ON UPDATE CASCADE);""")

        cursor.execute("""
CREATE FUNCTION public.sync_registration_section() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
  registration_row RECORD;
  db_conn VARCHAR;
  query_string TEXT;
BEGIN
  FOR registration_row IN SELECT semester, course FROM courses_registration_sections WHERE registration_section_id=NEW.registration_section_id LOOP
    RAISE NOTICE 'Semester: %, Course: %, Registration Section: %', registration_row.semester, registration_row.course, NEW.registration_section_id;
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
$$;""")

        cursor.execute('CREATE TRIGGER registration_sync_registration_id AFTER INSERT ON public.courses_registration_sections FOR EACH ROW EXECUTE PROCEDURE public.sync_registration_section();')

def down(conn):
    with conn.cursor() as cursor:
        cursor.execute('DROP TRIGGER registration_sync_registration_id;')
        cursor.execute('DROP FUNCTION public.sync_registration_section();')
        cursor.execute('DROP TABLE public.courses_registration_sections;')
