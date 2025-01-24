--
-- Name: sync_delete_user_cleanup(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.sync_delete_user_cleanup() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
-- AFTER DELETE trigger function removes user from master.users if they have no
-- existing course enrollment.  (i.e. no entries in courses_users)
DECLARE
    user_courses INTEGER;
BEGIN
    SELECT COUNT(*) INTO user_courses FROM courses_users WHERE user_id = OLD.user_id;
    IF user_courses = 0 THEN
        DELETE FROM users WHERE user_id = OLD.user_id;
    END IF;
    RETURN NULL;

-- The SELECT Count(*) / If check should prevent this exception, but this
-- exception handling is provided 'just in case' so process isn't interrupted.
EXCEPTION WHEN integrity_constraint_violation THEN
    -- Show that an exception occurred, and what was the exception.
    RAISE NOTICE 'Integrity constraint prevented user ''%'' from being deleted from master.users table.', OLD.user_id;
    RAISE NOTICE '%', SQLERRM;
    RETURN NULL;
END;
$$;
