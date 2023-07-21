--
-- Name: gradeable_version_change(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.gradeable_version_change() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
        #variable_conflict use_variable
        DECLARE
            g_id varchar;
            user_id varchar;
            team_id varchar;
            version RECORD;
        BEGIN
            g_id = CASE WHEN TG_OP = 'DELETE' THEN OLD.g_id ELSE NEW.g_id END;
            user_id = CASE WHEN TG_OP = 'DELETE' THEN OLD.user_id ELSE NEW.user_id END;
            team_id = CASE WHEN TG_OP = 'DELETE' THEN OLD.team_id ELSE NEW.team_id END;
            
            --- Remove all lade day cache for all gradeables past this submission due date
            --- for every user associated with the gradeable
            DELETE FROM late_day_cache ldc
            WHERE late_day_date >= (SELECT eg.eg_submission_due_date 
                                    FROM electronic_gradeable eg
                                    WHERE eg.g_id = g_id)
                AND (
                    ldc.user_id IN (SELECT t.user_id FROM teams t WHERE t.team_id = team_id)
                    OR
                    ldc.user_id = user_id
                );

            RETURN NEW;
        END;
    $$;
