--
-- Name: sync_delete_user(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.sync_delete_user() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
-- BEFORE DELETE trigger function to DELETE users from course DB.
DECLARE
    db_conn VARCHAR;
    query_string TEXT;
BEGIN
    db_conn := format('dbname=submitty_%s_%s', OLD.term, OLD.course);
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
$$;
