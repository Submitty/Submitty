--
-- Name: electronic_gradeable_change(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.electronic_gradeable_change() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
        #variable_conflict use_variable
        DECLARE
            g_id varchar ;
            due_date timestamp;
        BEGIN
            -- Check for any important changes
            IF TG_OP = 'UPDATE'
            AND NEW.eg_submission_due_date = OLD.eg_submission_due_date
            AND NEW.eg_has_due_date = OLD.eg_has_due_date
            AND NEW.eg_allow_late_submission = OLD.eg_allow_late_submission
            AND NEW.eg_late_days = OLD.eg_late_days THEN
                RETURN NEW;
            END IF;
            
            -- Grab submission due date
            due_date = 
            CASE
                -- INSERT
                WHEN TG_OP = 'INSERT' THEN NEW.eg_submission_due_date
                -- DELETE
                WHEN TG_OP = 'DELETE' THEN OLD.eg_submission_due_date
                -- UPDATE
                ELSE LEAST(NEW.eg_submission_due_date, OLD.eg_submission_due_date)
            END;
            
            DELETE FROM late_day_cache WHERE late_day_date >= due_date;
            RETURN NEW;
        END;
    $$;
