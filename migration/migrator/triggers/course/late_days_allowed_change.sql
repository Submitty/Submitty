--
-- Name: late_days_allowed_change(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.late_days_allowed_change() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    #variable_conflict use_variable
    DECLARE
        g_id varchar;
        user_id varchar;
        team_id varchar;
        version RECORD;
    BEGIN
        version = CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
        -- since_timestamp = CASE WHEN TG_OP = 'DELETE' THEN OLD.since_timestamp ELSE NEW.since_timestamp END;
        -- user_id = CASE WHEN TG_OP = 'DELETE' THEN OLD.user_id ELSE NEW.user_id END;

        DELETE FROM late_day_cache ldc WHERE ldc.late_day_date >= version.since_timestamp AND ldc.user_id = version.user_id;
        RETURN NEW;
    END;
    $$;
