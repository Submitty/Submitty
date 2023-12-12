--
-- Name: sync_courses_user(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.sync_courses_user() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
            DECLARE
                user_row record;
                db_conn varchar;
                query_string text;
            BEGIN
                db_conn := format('dbname=submitty_%s_%s', NEW.term, NEW.course);

                IF (TG_OP = 'INSERT') THEN
                    -- FULL data sync on INSERT of a new user record.
                    SELECT * INTO user_row FROM users WHERE user_id=NEW.user_id;
                    query_string := 'INSERT INTO users (
                        user_id,
                        user_numeric_id,
                        user_pronouns,
                        display_pronouns,
                        user_givenname,
                        user_preferred_givenname,
                        user_familyname,
                        user_preferred_familyname,
                        user_last_initial_format,
                        user_email,
                        user_email_secondary,
                        user_email_secondary_notify,
                        time_zone,
                        user_preferred_locale,
                        display_image_state,
                        user_updated,
                        instructor_updated,
                        user_group,
                        registration_section,
                        registration_type,
                        manual_registration,
                        display_name_order
                    ) VALUES ('
                        || quote_literal(user_row.user_id) || ', '
                        || quote_nullable(user_row.user_numeric_id) || ', ' 
                        || quote_literal(user_row.user_pronouns) || ', ' 
                        || quote_literal(user_row.display_pronouns) || ', '
                        || quote_literal(user_row.user_givenname) || ', ' 
                        || quote_nullable(user_row.user_preferred_givenname) || ', ' 
                        || quote_literal(user_row.user_familyname) || ', '
                        || quote_nullable(user_row.user_preferred_familyname) || ', ' 
                        || quote_literal(user_row.user_last_initial_format) || ', ' 
                        || quote_literal(user_row.user_email) || ', ' 
                        || quote_literal(user_row.user_email_secondary) || ', ' 
                        || quote_literal(user_row.user_email_secondary_notify) || ', ' 
                        || quote_literal(user_row.time_zone) || ', '
                        || quote_nullable(user_row.user_preferred_locale) || ', '
                        || quote_literal(user_row.display_image_state) || ', '
                        || quote_literal(user_row.user_updated) || ', '
                        || quote_literal(user_row.instructor_updated) || ', '
                        || NEW.user_group || ', ' 
                        || quote_nullable(NEW.registration_section) || ', ' 
                        || quote_literal(NEW.registration_type) || ', '
                        || NEW.manual_registration || ', '
                        || quote_literal(user_row.display_name_order)
                    || ')';
                    IF query_string IS NULL THEN
                        RAISE EXCEPTION 'query_string error in trigger function sync_courses_user() when doing INSERT';
                    END IF;
                    PERFORM dblink_exec(db_conn, query_string);
                ELSIF (TG_OP = 'UPDATE') THEN
                    -- User update on registration_section
                    -- CASE clause ensures user's rotating section is set NULL when
                    -- registration is updated to NULL.  (e.g. student has dropped)
                    query_string = 'UPDATE users SET '
                        || 'user_group=' || NEW.user_group || ', '
                        || 'registration_section=' || quote_nullable(NEW.registration_section) || ', '
                        || 'rotating_section=' || CASE WHEN NEW.registration_section IS NULL THEN 'null' ELSE 'rotating_section' END || ', '
                        || 'registration_type=' || quote_literal(NEW.registration_type) || ', '
                        || 'manual_registration=' || NEW.manual_registration
                    || ' WHERE user_id=' || quote_literal(NEW.user_id);
                    IF query_string IS NULL THEN
                        RAISE EXCEPTION 'query_string error in trigger function sync_courses_user() when doing UPDATE';
                    END IF;
                    PERFORM dblink_exec(db_conn, query_string);
                END IF;

                -- All done.
                RETURN NULL;
            END;
            $$;