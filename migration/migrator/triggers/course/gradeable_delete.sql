--
-- Name: gradeable_delete(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.gradeable_delete() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
        BEGIN
            DELETE FROM late_day_cache WHERE late_day_date >= (SELECT eg_submission_due_date 
                                                                FROM electronic_gradeable 
                                                                WHERE g_id = OLD.g_id);
            RETURN OLD;
        END;
    $$;
