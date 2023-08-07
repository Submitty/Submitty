--
-- Name: sync_delete_registration_section(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.sync_delete_registration_section() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
-- BEFORE DELETE trigger function to DELETE registration sections from course DB, as needed.
DECLARE
    registration_row RECORD;
    db_conn VARCHAR;
    query_string TEXT;
BEGIN
    db_conn := format('dbname=submitty_%s_%s', OLD.term, OLD.course);
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
$$;
