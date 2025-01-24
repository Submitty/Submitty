--
-- Name: add_course_user(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.add_course_user() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
        DECLARE
            temp_row RECORD;
            random_str TEXT;
            num_rows INT;
        BEGIN
            FOR temp_row IN SELECT g_id FROM gradeable LOOP
                LOOP
                    random_str = random_string(15);
                    PERFORM 1 FROM gradeable_anon
                    WHERE g_id=temp_row.g_id AND anon_id=random_str;
                    GET DIAGNOSTICS num_rows = ROW_COUNT;
                    IF num_rows = 0 THEN
                        EXIT;
                    END IF;
                END LOOP;
                INSERT INTO gradeable_anon (
                    SELECT NEW.user_id, temp_row.g_id, random_str
                    WHERE NOT EXISTS (
                        SELECT 1
                        FROM gradeable_anon
                        WHERE user_id=NEW.user_id AND g_id=temp_row.g_id
                    )
                );
            END LOOP;
            RETURN NULL;
        END;
    $$;
