--
-- Name: sync_user(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.sync_user() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
        DECLARE
            course_row RECORD;
            db_conn VARCHAR;
            query_string TEXT;
            preferred_name_change_details TEXT;
        BEGIN
            -- Check for changes in users.user_preferred_givenname and users.user_preferred_familyname.
            IF coalesce(OLD.user_preferred_givenname, '') <> coalesce(NEW.user_preferred_givenname, '') THEN
                preferred_name_change_details := format('PREFERRED_GIVENNAME OLD: "%s" NEW: "%s" ', OLD.user_preferred_givenname, NEW.user_preferred_givenname);
            END IF;
            IF coalesce(OLD.user_preferred_familyname, '') <> coalesce(NEW.user_preferred_familyname, '') THEN
                preferred_name_change_details := format('%sPREFERRED_FAMILYNAME OLD: "%s" NEW: "%s"', preferred_name_change_details, OLD.user_preferred_familyname, NEW.user_preferred_familyname);
            END IF;
            -- If any preferred_name data has changed, preferred_name_change_details will not be NULL.
            IF preferred_name_change_details IS NOT NULL THEN
                preferred_name_change_details := format('USER_ID: "%s" %s', NEW.user_id, preferred_name_change_details);
                RAISE LOG USING MESSAGE = 'PREFERRED_NAME DATA UPDATE', DETAIL = preferred_name_change_details;
            END IF;
            -- Propagate UPDATE to course DBs
            FOR course_row IN SELECT term, course FROM courses_users WHERE user_id=NEW.user_id LOOP
                RAISE NOTICE 'Term: %, Course: %', course_row.term, course_row.course;
                db_conn := format('dbname=submitty_%s_%s', course_row.term, course_row.course);
                query_string := 'UPDATE users SET '
                    || 'user_numeric_id=' || quote_nullable(NEW.user_numeric_id) || ', '
                    || 'user_pronouns=' || quote_literal(NEW.user_pronouns) || ', '
                    || 'display_pronouns=' || quote_literal(NEW.display_pronouns) || ', '
                    || 'user_givenname=' || quote_literal(NEW.user_givenname) || ', '
                    || 'user_preferred_givenname=' || quote_nullable(NEW.user_preferred_givenname) || ', '
                    || 'user_familyname=' || quote_literal(NEW.user_familyname) || ', '
                    || 'user_preferred_familyname=' || quote_nullable(NEW.user_preferred_familyname) || ', '
                    || 'user_last_initial_format=' || quote_literal(NEW.user_last_initial_format) || ', '
                    || 'user_email=' || quote_literal(NEW.user_email) || ', '
                    || 'user_email_secondary=' || quote_literal(NEW.user_email_secondary) || ', '
                    || 'user_email_secondary_notify=' || quote_literal(NEW.user_email_secondary_notify) || ', '
                    || 'time_zone=' || quote_literal(NEW.time_zone) || ', '
                    || 'user_preferred_locale=' || quote_nullable(NEW.user_preferred_locale) || ', '
                    || 'display_image_state=' || quote_literal(NEW.display_image_state) || ', '
                    || 'display_name_order=' || quote_literal(NEW.display_name_order)  || ', '
                    || 'user_updated=' || quote_literal(NEW.user_updated) || ', '
                    || 'instructor_updated=' || quote_literal(NEW.instructor_updated)
                || ' WHERE user_id=' || quote_literal(NEW.user_id);
                -- Need to make sure that query_string was set properly as dblink_exec will happily take a null and then do nothing
                IF query_string IS NULL THEN
                    RAISE EXCEPTION 'query_string error in trigger function sync_user()';
                END IF;
                PERFORM dblink_exec(db_conn, query_string);
            END LOOP;

            -- All done.
            RETURN NULL;
        END;
        $$;
