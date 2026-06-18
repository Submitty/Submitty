--
-- Name: late_day_extension_change(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.late_day_extension_change() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
        #variable_conflict use_variable
        DECLARE
            g_id varchar;
            user_id varchar;
        BEGIN
            -- Grab values for delete/update/insert
            g_id = CASE WHEN TG_OP = 'DELETE' THEN OLD.g_id ELSE NEW.g_id END;
            user_id = CASE WHEN TG_OP = 'DELETE' THEN OLD.user_id ELSE NEW.user_id END;

            DELETE FROM late_day_cache ldc 
            WHERE ldc.late_day_date >= (SELECT eg_submission_due_date 
                                        FROM electronic_gradeable eg 
                                        WHERE eg.g_id = g_id)
            AND ldc.user_id = user_id;
            RETURN NEW;
        END;
    $$;
