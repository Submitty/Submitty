--
-- PostgreSQL database dump
--


SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: notifications_component; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.notifications_component AS ENUM (
    'forum',
    'student',
    'grading',
    'team'
);


--
-- Name: add_course_user(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.add_course_user() RETURNS trigger
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


SET default_tablespace = '';


--
-- Name: late_day_cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.late_day_cache (
    g_id character varying(255),
    user_id character varying(255) NOT NULL,
    team_id character varying(255),
    late_day_date timestamp with time zone NOT NULL,
    late_days_remaining integer NOT NULL,
    late_days_allowed integer,
    submission_days_late integer,
    late_day_exceptions integer,
    late_day_status integer,
    late_days_change integer NOT NULL,
    reason_for_exception character varying(255),
    CONSTRAINT ldc_gradeable_info CHECK (((g_id IS NULL) OR ((submission_days_late IS NOT NULL) AND (late_day_exceptions IS NOT NULL))))
);


--
-- Name: calculate_remaining_cache_for_user(text, integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.calculate_remaining_cache_for_user(user_id text, default_late_days integer) RETURNS SETOF public.late_day_cache
    LANGUAGE plpgsql
    AS $$
    #variable_conflict use_variable
    DECLARE
        var_row RECORD;
        return_cache late_day_cache%rowtype;
        latestDate timestamp with time zone;
        late_days_remaining integer;
        late_days_change integer;
        late_days_used integer;
        returnedrow late_day_cache%rowtype;
    BEGIN
        -- Grab latest row of data available
        FOR var_row IN (
            SELECT * 
            FROM late_day_cache ldc 
            WHERE ldc.user_id = user_id
            ORDER BY ldc.late_day_date DESC, ldc.g_id DESC NULLS LAST
            LIMIT 1
        ) LOOP
            late_days_remaining = var_row.late_days_remaining;
            latestDate = var_row.late_day_date;
        END LOOP;
        
        -- Get the number of late days charged up to this point
        late_days_used = (SELECT COALESCE(SUM(-ldc.late_days_change), 0)
            FROM late_day_cache ldc
            WHERE (latestDate is NULL OR ldc.late_day_date <= latestDate)
                AND ldc.user_id = user_id AND ldc.g_id IS NOT NULL
        );
        
        -- if there is no cache in the table, the starting point
        -- should be the course default late days
        IF late_days_remaining IS NULL THEN
            late_days_remaining = default_late_days;
            late_days_used = 0;
        END IF;
        
        -- For every event after the cache's latest entry, calculate the 
        -- late days remaining and the late day change (increase or decrease)
        FOR var_row IN (
            SELECT * FROM (
                SELECT * FROM grab_late_day_gradeables_for_user (user_id := user_id)
                UNION
                SELECT * FROM grab_late_day_updates_for_user (user_id := user_id)
            ) as combined
            WHERE latestDate is NULL OR late_day_date > latestDate
            ORDER BY late_day_date NULLS LAST, g_id NULLS FIRST
        ) LOOP
            --is late day update
            IF var_row.g_id IS NULL THEN
                late_days_change = var_row.late_days_allowed - (late_days_remaining + late_days_used);
                late_days_remaining = GREATEST(0, late_days_remaining + late_days_change);
                return_cache = var_row;
                return_cache.late_days_change = late_days_change;
                return_cache.late_days_remaining = late_days_remaining;
            --is gradeable event
            ELSE
                returnedrow = get_late_day_info_from_previous(var_row.submission_days_late, var_row.late_days_allowed, var_row.late_day_exceptions, late_days_remaining);
                late_days_used = late_days_used - returnedrow.late_days_change;
				late_days_remaining = late_days_remaining + returnedrow.late_days_change;
                return_cache = var_row;
                return_cache.late_days_change = returnedrow.late_days_change;
                return_cache.late_days_remaining = returnedrow.late_days_remaining;
            END IF;
            RETURN NEXT return_cache;
        END LOOP;
        RETURN;
    END;
    $$;


--
-- Name: calculate_submission_days_late(timestamp with time zone, timestamp with time zone); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.calculate_submission_days_late(submission_time timestamp with time zone, submission_due_date timestamp with time zone) RETURNS integer
    LANGUAGE plpgsql
    AS $$
    #variable_conflict use_variable
    DECLARE
        return_row late_day_cache%rowtype;
        late_days_change integer;
        assignment_budget integer;
    BEGIN
        RETURN 
        CASE
            WHEN submission_time IS NULL THEN 0
            WHEN DATE_PART('day', submission_time - submission_due_date) < 0 THEN 0
            WHEN DATE_PART('hour', submission_time - submission_due_date) > 0
                OR DATE_PART('minute', submission_time - submission_due_date) > 0
                OR DATE_PART('second', submission_time - submission_due_date) > 0
                THEN DATE_PART('day', submission_time - submission_due_date) + 1
            ELSE DATE_PART('day', submission_time - submission_due_date)
        END;
    END;
    $$;


--
-- Name: check_valid_score(numeric, integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.check_valid_score(numeric, integer) RETURNS boolean
    LANGUAGE plpgsql
    AS $_$
declare
valid_score BOOLEAN;
BEGIN
   SELECT
   CASE WHEN gc_max_value >=0 THEN $1<=gc_max_value AND $1>=0
        ELSE $1>=gc_max_value AND $1<=0
   END INTO valid_score FROM gradeable_component AS gc WHERE gc.gc_id=$2;
   RETURN valid_score;
END;
$_$;


--
-- Name: csv_to_numeric_gradeable(text[], text, text); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.csv_to_numeric_gradeable(vcode text[], gradeable_id text, grader_id text) RETURNS boolean
    LANGUAGE plpgsql
    AS $$
    DECLARE
        -- Size of first array after splitting
        size INTEGER;
        -- Array of individual line after splitting
        line TEXT[];
        -- Variable to store each line in the array
        i TEXT;
        -- Array of gc_ids for this gradeable
        gcids INTEGER[];
        -- gradeable_data id for this gradeable for this student
        gdid INTEGER;
        -- Array counter
        j INTEGER;
        -- Is this gradeable component text?
        istext BOOLEAN[];
        --Score to be inserted
        score NUMERIC;
    BEGIN
        gcids := ARRAY(SELECT gc_id FROM gradeable_component WHERE g_id = gradeable_id);
        istext := ARRAY(SELECT gc_is_text FROM gradeable_component WHERE g_id = gradeable_id);
        -- Get the number of gradeable components for this gradeable. Will be used to test
        -- for uniform sized arrays
        size := array_length(gcids, 1);
        FOREACH i IN ARRAY vcode
        LOOP
            -- Split the current line
            line := string_to_array(i, ',');
            -- Check for uniform size
            IF array_length(line, 1) <> size + 1 THEN
            RAISE EXCEPTION 'INVALID SIZE: Arrays are jagged.';
            END IF;

            -- Remove any existing record for this student for this gradeable
            DELETE FROM gradeable_data WHERE gd_user_id = line[1] AND g_id = gradeable_id;

            INSERT INTO gradeable_data(g_id, gd_user_id) VALUES (gradeable_id, line[1]);

            SELECT gd_id INTO gdid FROM gradeable_data WHERE g_id = gradeable_id AND gd_user_id = line[1];

            FOR j IN 1..size
            LOOP
            IF istext[j] THEN
            --COME BACK AND FIX: need to put in gcd_grade_time...double check to see that CSV upload still works for numeric/text
                INSERT INTO gradeable_component_data(gc_id, gd_id, gcd_component_comment, gcd_grader_id, gcd_graded_version, gcd_grade_time) VALUES (gcids[j], gdid, line[j+1], grader_id, NULL);
            ELSE
                score := CAST(line[j+1] AS NUMERIC);
                INSERT INTO gradeable_component_data(gc_id, gd_id, gcd_score, gcd_grader_id, gcd_graded_version, gcd_grade_time) VALUES (gcids[j], gdid, score, grader_id, NULL);
            END IF;
            END LOOP;

        END LOOP;
        RETURN TRUE ;
    END;
    $$;


--
-- Name: electronic_gradeable_change(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.electronic_gradeable_change() RETURNS trigger
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


--
-- Name: get_allowed_late_days(character varying, timestamp with time zone); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.get_allowed_late_days(character varying, timestamp with time zone) RETURNS integer
    LANGUAGE sql
    AS $_$
SELECT allowed_late_days FROM late_days WHERE user_id = $1 AND since_timestamp <= $2 ORDER BY since_timestamp DESC LIMIT 1;
$_$;


--
-- Name: get_late_day_info_from_previous(integer, integer, integer, integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.get_late_day_info_from_previous(submission_days_late integer, late_days_allowed integer, late_day_exceptions integer, late_days_remaining integer) RETURNS SETOF public.late_day_cache
    LANGUAGE plpgsql
    AS $$
    #variable_conflict use_variable
    DECLARE
        return_row late_day_cache%rowtype;
        late_days_change integer;
        assignment_budget integer;
    BEGIN
        late_days_change = 0;
        assignment_budget = LEAST(late_days_allowed, late_days_remaining) + late_day_exceptions;
        IF submission_days_late <= assignment_budget THEN
            -- clamp the days charged to be the days late minus exceptions above zero.
            late_days_change = -GREATEST(0, LEAST(submission_days_late, assignment_budget) - late_day_exceptions);
        END IF;

        return_row.late_day_status = 
        CASE
            -- BAD STATUS
            WHEN (submission_days_late > late_day_exceptions AND late_days_change = 0) THEN 3
            -- LATE STATUS
            WHEN submission_days_late > late_day_exceptions THEN 2
            -- GOOD STATUS
            ELSE 1
        END;

        return_row.late_days_change = late_days_change;
        return_row.late_days_remaining = late_days_remaining + late_days_change;
        RETURN NEXT return_row;
        RETURN;
    END;
    $$;


--
-- Name: grab_late_day_gradeables_for_user(text); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.grab_late_day_gradeables_for_user(user_id text) RETURNS SETOF public.late_day_cache
    LANGUAGE plpgsql
    AS $$
    #variable_conflict use_variable
    DECLARE
    latestDate timestamp with time zone ;
    var_row RECORD;
    returnrow late_day_cache%rowtype;
    BEGIN
        FOR var_row in (
            WITH valid_gradeables AS (
				SELECT g.g_id, g.g_title, eg.eg_submission_due_date, eg.eg_late_days
				FROM gradeable g
				JOIN electronic_gradeable eg
					ON eg.g_id=g.g_id
				WHERE 
					eg.eg_submission_due_date IS NOT NULL
					and eg.eg_has_due_date = TRUE
					and eg.eg_student_submit = TRUE
					and eg.eg_student_view = TRUE
					and g.g_gradeable_type = 0
					and eg.eg_allow_late_submission = TRUE
					and eg.eg_submission_open_date <= NOW()
			),
			submitted_gradeables AS (
				SELECT egd.g_id, u.user_id, t.team_id, egd.submission_time
				FROM electronic_gradeable_version egv
				JOIN electronic_gradeable_data egd
					ON egv.g_id=egd.g_id 
					AND egv.active_version=egd.g_version
					AND (
						CASE
							when egd.team_id IS NOT NULL THEN egv.team_id=egd.team_id
							else egv.user_id=egd.user_id
						END
					)
				LEFT JOIN teams t
					ON t.team_id=egd.team_id
				LEFT JOIN users u
					ON u.user_id=t.user_id
					OR u.user_id=egd.user_id
				WHERE u.user_id=user_id
			)
			SELECT
				vg.g_id,
				vg.g_title,
				COALESCE(sg.user_id, user_id) as user_id,
				sg.team_id,
				vg.eg_submission_due_date AS late_day_date,
				vg.eg_late_days AS late_days_allowed,
				calculate_submission_days_late(sg.submission_time, vg.eg_submission_due_date) AS submission_days_late,
				CASE
					WHEN lde.late_day_exceptions IS NULL THEN 0
					ELSE lde.late_day_exceptions
				END AS late_day_exceptions,
				lde.reason_for_exception
			FROM valid_gradeables vg
			LEFT JOIN submitted_gradeables sg
				ON vg.g_id=sg.g_id
			LEFT JOIN late_day_exceptions lde
				ON lde.user_id=user_id
				AND vg.g_id=lde.g_id
		ORDER BY late_day_date, g_id
	) LOOP
		returnrow.g_id = var_row.g_id;
		returnrow.team_id = var_row.team_id;
		returnrow.user_id = var_row.user_id;
		returnrow.late_days_allowed = var_row.late_days_allowed;
		returnrow.late_day_date = var_row.late_day_date;
		returnrow.submission_days_late = var_row.submission_days_late;
		returnrow.late_day_exceptions = var_row.late_day_exceptions;
		returnrow.reason_for_exception = var_row.reason_for_exception;
		RETURN NEXT returnrow;
        END LOOP;
        RETURN;	
    END;
    $$;


--
-- Name: grab_late_day_updates_for_user(text); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.grab_late_day_updates_for_user(user_id text) RETURNS SETOF public.late_day_cache
    LANGUAGE plpgsql
    AS $$
    #variable_conflict use_variable
    DECLARE
    latestDate timestamp with time zone ;
    var_row RECORD;
    returnrow late_day_cache%rowtype;
    BEGIN
        FOR var_row in (
            SELECT
                ld.user_id,
                ld.since_timestamp AS late_day_date,
                ld.allowed_late_days AS late_days_allowed
            FROM late_days ld
            WHERE 
                ld.user_id = user_id
            ORDER BY late_day_date
        ) LOOP
            returnrow.user_id = var_row.user_id;
            returnrow.late_day_date = var_row.late_day_date;
            returnrow.late_days_allowed = var_row.late_days_allowed;
            RETURN NEXT returnrow;
        END LOOP;
        RETURN;	
    END;
    $$;


--
-- Name: gradeable_delete(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.gradeable_delete() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
        BEGIN
            DELETE FROM late_day_cache WHERE late_day_date >= (SELECT eg_submission_due_date 
                                                                FROM electronic_gradeable 
                                                                WHERE g_id = OLD.g_id);
            RETURN OLD;
        END;
    $$;


--
-- Name: gradeable_version_change(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.gradeable_version_change() RETURNS trigger
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


--
-- Name: late_day_extension_change(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.late_day_extension_change() RETURNS trigger
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


--
-- Name: late_days_allowed_change(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.late_days_allowed_change() RETURNS trigger
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


--
-- Name: random_string(integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.random_string(length integer) RETURNS text
    LANGUAGE plpgsql
    AS $$
        DECLARE
            chars text[] := '{0,1,2,3,4,5,6,7,8,9,A,B,C,D,E,F,G,H,I,J,K,L,M,N,O,P,Q,R,S,T,U,V,W,X,Y,Z,a,b,c,d,e,f,g,h,i,j,k,l,m,n,o,p,q,r,s,t,u,v,w,x,y,z}';
            result text := '';
            i integer := 0;
        BEGIN
            IF length < 0 THEN
                raise exception 'Given length cannot be less than 0';
            END IF;
            FOR i IN 1..length LOOP
                result := result || chars[1+random()*(array_length(chars, 1)-1)];
            END LOOP;
            RETURN result;
        END;
    $$;


--
-- Name: update_previous_rotating_section(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.update_previous_rotating_section() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
	IF (
		(NEW.rotating_section IS NULL AND OLD.rotating_section IS NOT NULL)
		OR NEW.rotating_section != OLD.rotating_section
	) THEN
		NEW.previous_rotating_section := OLD.rotating_section;
	END IF;
	RETURN NEW;
END;
$$;


--
-- Name: active_graders; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.active_graders (
    id integer NOT NULL,
    grader_id character varying(255) NOT NULL,
    gc_id integer NOT NULL,
    ag_user_id character varying(255),
    ag_team_id character varying(255),
    "timestamp" timestamp with time zone NOT NULL,
    CONSTRAINT ag_user_team_id_check CHECK (((ag_user_id IS NOT NULL) OR (ag_team_id IS NOT NULL)))
);


--
-- Name: active_graders_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.active_graders_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: active_graders_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.active_graders_id_seq OWNED BY public.active_graders.id;


--
-- Name: autograding_metrics; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.autograding_metrics (
    user_id text NOT NULL,
    team_id text NOT NULL,
    g_id text NOT NULL,
    g_version integer NOT NULL,
    testcase_id text NOT NULL,
    elapsed_time real,
    max_rss_size integer,
    points integer NOT NULL,
    passed boolean NOT NULL,
    hidden boolean NOT NULL,
    source_lines_of_code integer,
    CONSTRAINT elapsed_time_nonnegative CHECK ((elapsed_time >= (0)::double precision)),
    CONSTRAINT max_rss_size_nonnegative CHECK ((max_rss_size >= 0)),
    CONSTRAINT metrics_user_team_id_check CHECK (((user_id IS NOT NULL) OR (team_id IS NOT NULL))),
    CONSTRAINT sloc_non_negative CHECK ((source_lines_of_code >= 0))
);


--
-- Name: calendar_messages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.calendar_messages (
    id integer NOT NULL,
    type integer NOT NULL,
    text character varying(255) NOT NULL,
    date date NOT NULL
);


--
-- Name: calendar_messages_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.calendar_messages_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: calendar_messages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.calendar_messages_id_seq OWNED BY public.calendar_messages.id;


--
-- Name: categories_list; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.categories_list (
    category_id integer NOT NULL,
    category_desc character varying NOT NULL,
    rank integer,
    color character varying DEFAULT '#000080'::character varying NOT NULL,
    visible_date date
);


--
-- Name: categories_list_category_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.categories_list_category_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: categories_list_category_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.categories_list_category_id_seq OWNED BY public.categories_list.category_id;


--
-- Name: course_materials; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.course_materials (
    id integer NOT NULL,
    path character varying(255),
    type smallint NOT NULL,
    release_date timestamp with time zone,
    hidden_from_students boolean,
    priority double precision NOT NULL,
    url text,
    title character varying(255),
    uploaded_by character varying(255) DEFAULT NULL::character varying,
    uploaded_date timestamp with time zone,
    last_edit_by character varying(255) DEFAULT NULL::character varying,
    last_edit_date timestamp with time zone,
    CONSTRAINT check_dates CHECK (((uploaded_date IS NULL) OR (last_edit_date IS NULL) OR (uploaded_date <= last_edit_date)))
);


--
-- Name: course_materials_access; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.course_materials_access (
    id integer NOT NULL,
    course_material_id integer NOT NULL,
    user_id character varying(255) NOT NULL,
    "timestamp" timestamp with time zone NOT NULL
);


--
-- Name: course_materials_access_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.course_materials_access_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: course_materials_access_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.course_materials_access_id_seq OWNED BY public.course_materials_access.id;


--
-- Name: course_materials_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.course_materials_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: course_materials_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.course_materials_id_seq OWNED BY public.course_materials.id;


--
-- Name: course_materials_sections; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.course_materials_sections (
    course_material_id integer NOT NULL,
    section_id character varying(255) NOT NULL,
    id integer NOT NULL
);


--
-- Name: course_materials_sections_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.course_materials_sections_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: course_materials_sections_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.course_materials_sections_id_seq OWNED BY public.course_materials_sections.id;


--
-- Name: electronic_gradeable; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.electronic_gradeable (
    g_id character varying(255) NOT NULL,
    eg_config_path character varying(1024) NOT NULL,
    eg_is_repository boolean NOT NULL,
    eg_vcs_partial_path character varying(1024) NOT NULL,
    eg_vcs_host_type integer DEFAULT 0 NOT NULL,
    eg_team_assignment boolean NOT NULL,
    eg_max_team_size integer NOT NULL,
    eg_team_lock_date timestamp(6) with time zone NOT NULL,
    eg_use_ta_grading boolean NOT NULL,
    eg_student_download boolean DEFAULT false NOT NULL,
    eg_student_view boolean NOT NULL,
    eg_student_view_after_grades boolean DEFAULT false NOT NULL,
    eg_student_submit boolean NOT NULL,
    eg_submission_open_date timestamp(6) with time zone NOT NULL,
    eg_submission_due_date timestamp(6) with time zone NOT NULL,
    eg_has_due_date boolean DEFAULT true NOT NULL,
    eg_late_days integer DEFAULT '-1'::integer NOT NULL,
    eg_allow_late_submission boolean DEFAULT true NOT NULL,
    eg_precision numeric NOT NULL,
    eg_grade_inquiry_allowed boolean DEFAULT true NOT NULL,
    eg_grade_inquiry_per_component_allowed boolean DEFAULT false NOT NULL,
    eg_grade_inquiry_due_date timestamp(6) with time zone NOT NULL,
    eg_thread_ids json DEFAULT '{}'::json NOT NULL,
    eg_has_discussion boolean DEFAULT false NOT NULL,
    eg_limited_access_blind integer DEFAULT 1,
    eg_peer_blind integer DEFAULT 3,
    eg_grade_inquiry_start_date timestamp(6) with time zone NOT NULL,
    eg_hidden_files character varying(1024),
    eg_depends_on character varying(255) DEFAULT NULL::character varying,
    eg_depends_on_points integer,
    eg_has_release_date boolean DEFAULT true NOT NULL,
    eg_vcs_subdirectory character varying(1024) DEFAULT ''::character varying NOT NULL,
    eg_using_subdirectory boolean DEFAULT false NOT NULL,
    eg_instructor_blind integer DEFAULT 1,
    CONSTRAINT eg_grade_inquiry_allowed_true CHECK (((eg_grade_inquiry_allowed IS TRUE) OR (eg_grade_inquiry_per_component_allowed IS FALSE))),
    CONSTRAINT eg_grade_inquiry_due_date_max CHECK ((eg_grade_inquiry_due_date <= '9999-03-01 00:00:00-05'::timestamp with time zone)),
    CONSTRAINT eg_grade_inquiry_start_date_max CHECK ((eg_grade_inquiry_start_date <= '9999-03-01 00:00:00-05'::timestamp with time zone)),
    CONSTRAINT eg_submission_date CHECK ((eg_submission_open_date <= eg_submission_due_date)),
    CONSTRAINT eg_submission_due_date_max CHECK ((eg_submission_due_date <= '9999-03-01 00:00:00-05'::timestamp with time zone)),
    CONSTRAINT eg_team_lock_date_max CHECK ((eg_team_lock_date <= '9999-03-01 00:00:00-05'::timestamp with time zone)),
    CONSTRAINT late_days_positive CHECK ((eg_late_days >= 0))
);


--
-- Name: electronic_gradeable_data; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.electronic_gradeable_data (
    g_id character varying(255) NOT NULL,
    user_id character varying(255),
    team_id character varying(255),
    g_version integer NOT NULL,
    autograding_non_hidden_non_extra_credit numeric DEFAULT 0 NOT NULL,
    autograding_non_hidden_extra_credit numeric DEFAULT 0 NOT NULL,
    autograding_hidden_non_extra_credit numeric DEFAULT 0 NOT NULL,
    autograding_hidden_extra_credit numeric DEFAULT 0 NOT NULL,
    submission_time timestamp(6) with time zone NOT NULL,
    autograding_complete boolean DEFAULT false NOT NULL,
    CONSTRAINT egd_user_team_id_check CHECK (((user_id IS NOT NULL) OR (team_id IS NOT NULL)))
);


--
-- Name: electronic_gradeable_version; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.electronic_gradeable_version (
    g_id character varying(255) NOT NULL,
    user_id character varying(255),
    team_id character varying(255),
    active_version integer,
    anonymous_leaderboard boolean DEFAULT true NOT NULL,
    CONSTRAINT egv_user_team_id_check CHECK (((user_id IS NOT NULL) OR (team_id IS NOT NULL)))
);


--
-- Name: forum_attachments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.forum_attachments (
    post_id integer NOT NULL,
    file_name character varying NOT NULL,
    version_added integer DEFAULT 1 NOT NULL,
    version_deleted integer DEFAULT 0 NOT NULL,
    id integer NOT NULL
);


--
-- Name: forum_attachments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.forum_attachments_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: forum_attachments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.forum_attachments_id_seq OWNED BY public.forum_attachments.id;


--
-- Name: forum_posts_history; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.forum_posts_history (
    post_id integer NOT NULL,
    edit_author character varying NOT NULL,
    content text NOT NULL,
    edit_timestamp timestamp(0) with time zone NOT NULL,
    has_attachment boolean DEFAULT false,
    version_id integer
);


--
-- Name: forum_upducks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.forum_upducks (
    post_id integer NOT NULL,
    user_id character varying(255) NOT NULL
);


--
-- Name: grade_inquiries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.grade_inquiries (
    id integer NOT NULL,
    g_id character varying(255) NOT NULL,
    "timestamp" timestamp(0) with time zone NOT NULL,
    user_id character varying(255),
    team_id character varying(255),
    status integer DEFAULT 0 NOT NULL,
    gc_id integer
);


--
-- Name: grade_inquiries_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.grade_inquiries_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: grade_inquiries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.grade_inquiries_id_seq OWNED BY public.grade_inquiries.id;


--
-- Name: grade_inquiry_discussion; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.grade_inquiry_discussion (
    id integer NOT NULL,
    grade_inquiry_id integer NOT NULL,
    "timestamp" timestamp(0) with time zone NOT NULL,
    user_id character varying(255) NOT NULL,
    content text,
    deleted boolean DEFAULT false NOT NULL,
    gc_id integer
);


--
-- Name: grade_inquiry_discussion_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.grade_inquiry_discussion_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: grade_inquiry_discussion_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.grade_inquiry_discussion_id_seq OWNED BY public.grade_inquiry_discussion.id;


--
-- Name: grade_override; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.grade_override (
    user_id character varying(255) NOT NULL,
    g_id character varying(255) NOT NULL,
    marks double precision NOT NULL,
    comment character varying
);


--
-- Name: gradeable; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.gradeable (
    g_id character varying(255) NOT NULL,
    g_title character varying(255) NOT NULL,
    g_instructions_url character varying NOT NULL,
    g_overall_ta_instructions character varying NOT NULL,
    g_gradeable_type integer NOT NULL,
    g_grader_assignment_method integer NOT NULL,
    g_ta_view_start_date timestamp(6) with time zone NOT NULL,
    g_grade_start_date timestamp(6) with time zone NOT NULL,
    g_grade_due_date timestamp(6) with time zone NOT NULL,
    g_grade_released_date timestamp(6) with time zone NOT NULL,
    g_min_grading_group integer NOT NULL,
    g_syllabus_bucket character varying(255) NOT NULL,
    g_allowed_minutes integer,
    g_allow_custom_marks boolean DEFAULT true NOT NULL,
    CONSTRAINT g_grade_due_date CHECK ((g_grade_due_date <= g_grade_released_date)),
    CONSTRAINT g_grade_start_date CHECK ((g_grade_start_date <= g_grade_due_date)),
    CONSTRAINT g_ta_view_start_date CHECK ((g_ta_view_start_date <= g_grade_start_date))
);


--
-- Name: gradeable_access; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.gradeable_access (
    id integer NOT NULL,
    g_id character varying(255) NOT NULL,
    user_id character varying(255),
    team_id character varying(255),
    accessor_id character varying(255),
    "timestamp" timestamp with time zone NOT NULL,
    CONSTRAINT access_team_id_check CHECK (((user_id IS NOT NULL) OR (team_id IS NOT NULL)))
);


--
-- Name: gradeable_access_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.gradeable_access_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: gradeable_access_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.gradeable_access_id_seq OWNED BY public.gradeable_access.id;


--
-- Name: gradeable_allowed_minutes_override; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.gradeable_allowed_minutes_override (
    g_id character varying(255) NOT NULL,
    user_id character varying(255) NOT NULL,
    allowed_minutes integer NOT NULL
);


--
-- Name: gradeable_anon; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.gradeable_anon (
    user_id character varying NOT NULL,
    g_id character varying(255) NOT NULL,
    anon_id character varying(255) NOT NULL
);


--
-- Name: gradeable_component; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.gradeable_component (
    gc_id integer NOT NULL,
    g_id character varying(255) NOT NULL,
    gc_title character varying(255) NOT NULL,
    gc_ta_comment character varying NOT NULL,
    gc_student_comment character varying NOT NULL,
    gc_lower_clamp numeric NOT NULL,
    gc_default numeric NOT NULL,
    gc_max_value numeric NOT NULL,
    gc_upper_clamp numeric NOT NULL,
    gc_is_text boolean NOT NULL,
    gc_is_peer boolean NOT NULL,
    gc_order integer NOT NULL,
    gc_page integer NOT NULL,
    gc_is_itempool_linked boolean DEFAULT false NOT NULL,
    gc_itempool character varying(100) DEFAULT ''::character varying NOT NULL
);


--
-- Name: gradeable_component_data; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.gradeable_component_data (
    gc_id integer NOT NULL,
    gd_id integer NOT NULL,
    gcd_score numeric NOT NULL,
    gcd_component_comment character varying NOT NULL,
    gcd_grader_id character varying(255) NOT NULL,
    gcd_graded_version integer,
    gcd_grade_time timestamp(6) with time zone NOT NULL,
    gcd_verifier_id character varying(255),
    gcd_verify_time timestamp without time zone
);


--
-- Name: gradeable_component_gc_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.gradeable_component_gc_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: gradeable_component_gc_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.gradeable_component_gc_id_seq OWNED BY public.gradeable_component.gc_id;


--
-- Name: gradeable_component_mark; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.gradeable_component_mark (
    gcm_id integer NOT NULL,
    gc_id integer NOT NULL,
    gcm_points numeric NOT NULL,
    gcm_note character varying NOT NULL,
    gcm_publish boolean DEFAULT false,
    gcm_order integer NOT NULL
);


--
-- Name: gradeable_component_mark_data; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.gradeable_component_mark_data (
    gc_id integer NOT NULL,
    gd_id integer NOT NULL,
    gcd_grader_id character varying(255) NOT NULL,
    gcm_id integer NOT NULL
);


--
-- Name: gradeable_component_mark_gcm_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.gradeable_component_mark_gcm_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: gradeable_component_mark_gcm_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.gradeable_component_mark_gcm_id_seq OWNED BY public.gradeable_component_mark.gcm_id;


--
-- Name: gradeable_data; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.gradeable_data (
    gd_id integer NOT NULL,
    g_id character varying(255) NOT NULL,
    gd_user_id character varying(255),
    gd_team_id character varying(255),
    gd_user_viewed_date timestamp(6) with time zone DEFAULT NULL::timestamp with time zone
);


--
-- Name: gradeable_data_gd_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.gradeable_data_gd_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: gradeable_data_gd_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.gradeable_data_gd_id_seq OWNED BY public.gradeable_data.gd_id;


--
-- Name: gradeable_data_overall_comment; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.gradeable_data_overall_comment (
    goc_id integer NOT NULL,
    g_id character varying(255) NOT NULL,
    goc_user_id character varying(255),
    goc_team_id character varying(255),
    goc_grader_id character varying(255) NOT NULL,
    goc_overall_comment character varying NOT NULL,
    CONSTRAINT goc_user_team_id_check CHECK (((goc_user_id IS NOT NULL) OR (goc_team_id IS NOT NULL)))
);


--
-- Name: gradeable_data_overall_comment_goc_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.gradeable_data_overall_comment_goc_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: gradeable_data_overall_comment_goc_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.gradeable_data_overall_comment_goc_id_seq OWNED BY public.gradeable_data_overall_comment.goc_id;


--
-- Name: gradeable_teams; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.gradeable_teams (
    team_id character varying(255) NOT NULL,
    g_id character varying(255) NOT NULL,
    anon_id character varying(255),
    registration_section character varying(255),
    rotating_section integer,
    team_name character varying(255) DEFAULT NULL::character varying
);


--
-- Name: grading_registration; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.grading_registration (
    sections_registration_id character varying(255) NOT NULL,
    user_id character varying NOT NULL
);


--
-- Name: grading_rotating; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.grading_rotating (
    sections_rotating_id integer NOT NULL,
    user_id character varying NOT NULL,
    g_id character varying NOT NULL
);


--
-- Name: late_day_exceptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.late_day_exceptions (
    user_id character varying(255) NOT NULL,
    g_id character varying(255) NOT NULL,
    late_day_exceptions integer NOT NULL,
    reason_for_exception character varying(255) DEFAULT ''::character varying
);


--
-- Name: late_days; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.late_days (
    user_id character varying(255) NOT NULL,
    allowed_late_days integer NOT NULL,
    since_timestamp date NOT NULL
);


--
-- Name: lichen; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.lichen (
    id integer NOT NULL,
    gradeable_id character varying(255) NOT NULL,
    config_id smallint NOT NULL,
    version character varying(255) NOT NULL,
    regex text,
    regex_dir_submissions boolean NOT NULL,
    regex_dir_results boolean NOT NULL,
    regex_dir_checkout boolean NOT NULL,
    language character varying(255) NOT NULL,
    threshold smallint NOT NULL,
    hash_size smallint NOT NULL,
    other_gradeables text,
    ignore_submissions text,
    last_run_timestamp timestamp with time zone DEFAULT now(),
    has_provided_code boolean DEFAULT false NOT NULL,
    other_gradeable_paths text,
    CONSTRAINT lichen_config_id_check CHECK ((config_id > 0)),
    CONSTRAINT lichen_hash_size_check CHECK ((hash_size > 1)),
    CONSTRAINT lichen_threshold_check CHECK ((threshold > 1))
);


--
-- Name: lichen_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.lichen_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: lichen_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.lichen_id_seq OWNED BY public.lichen.id;


--
-- Name: lichen_run_access; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.lichen_run_access (
    id integer NOT NULL,
    lichen_run_id integer NOT NULL,
    user_id character varying(255) NOT NULL,
    "timestamp" timestamp with time zone NOT NULL
);


--
-- Name: lichen_run_access_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.lichen_run_access_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: lichen_run_access_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.lichen_run_access_id_seq OWNED BY public.lichen_run_access.id;


--
-- Name: migrations_course; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations_course (
    id character varying(100) NOT NULL,
    commit_time timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    status numeric(1,0) DEFAULT 0 NOT NULL
);


--
-- Name: notification_settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notification_settings (
    user_id character varying NOT NULL,
    merge_threads boolean DEFAULT false NOT NULL,
    all_new_threads boolean DEFAULT false NOT NULL,
    all_new_posts boolean DEFAULT false NOT NULL,
    all_modifications_forum boolean DEFAULT false NOT NULL,
    reply_in_post_thread boolean DEFAULT false NOT NULL,
    team_invite boolean DEFAULT true NOT NULL,
    team_joined boolean DEFAULT true NOT NULL,
    team_member_submission boolean DEFAULT true NOT NULL,
    self_notification boolean DEFAULT false NOT NULL,
    merge_threads_email boolean DEFAULT false NOT NULL,
    all_new_threads_email boolean DEFAULT false NOT NULL,
    all_new_posts_email boolean DEFAULT false NOT NULL,
    all_modifications_forum_email boolean DEFAULT false NOT NULL,
    reply_in_post_thread_email boolean DEFAULT false NOT NULL,
    team_invite_email boolean DEFAULT true NOT NULL,
    team_joined_email boolean DEFAULT true NOT NULL,
    team_member_submission_email boolean DEFAULT true NOT NULL,
    self_notification_email boolean DEFAULT false NOT NULL
);


--
-- Name: notifications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notifications (
    id integer NOT NULL,
    component public.notifications_component NOT NULL,
    metadata text NOT NULL,
    content text NOT NULL,
    from_user_id character varying(255),
    to_user_id character varying(255) NOT NULL,
    created_at timestamp(0) with time zone NOT NULL,
    seen_at timestamp(0) with time zone
);


--
-- Name: notifications_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.notifications_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: notifications_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.notifications_id_seq OWNED BY public.notifications.id;


--
-- Name: peer_assign; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.peer_assign (
    g_id character varying NOT NULL,
    grader_id character varying NOT NULL,
    user_id character varying NOT NULL
);


--
-- Name: peer_feedback; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.peer_feedback (
    grader_id character varying(255) NOT NULL,
    user_id character varying(255) NOT NULL,
    g_id character varying(255) NOT NULL,
    feedback character varying(255)
);


--
-- Name: peer_grading_panel; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.peer_grading_panel (
    g_id character varying(255) NOT NULL,
    autograding boolean DEFAULT true NOT NULL,
    rubric boolean DEFAULT true NOT NULL,
    files boolean DEFAULT true NOT NULL,
    solution_notes boolean DEFAULT true NOT NULL,
    discussion boolean DEFAULT true NOT NULL
);


--
-- Name: poll_options; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.poll_options (
    order_id integer NOT NULL,
    poll_id integer,
    response text NOT NULL,
    correct boolean NOT NULL,
    option_id integer NOT NULL,
    author_id character varying(255) DEFAULT NULL::character varying
);


--
-- Name: poll_options_option_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.poll_options_option_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: poll_options_option_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.poll_options_option_id_seq OWNED BY public.poll_options.option_id;


--
-- Name: poll_responses; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.poll_responses (
    poll_id integer NOT NULL,
    student_id text NOT NULL,
    option_id integer NOT NULL,
    id integer NOT NULL
);


--
-- Name: poll_responses_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.poll_responses_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: poll_responses_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.poll_responses_id_seq OWNED BY public.poll_responses.id;


--
-- Name: polls; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.polls (
    poll_id integer NOT NULL,
    name text NOT NULL,
    question text NOT NULL,
    release_date date NOT NULL,
    image_path text,
    question_type character varying(35) DEFAULT 'single-response-multiple-correct'::character varying,
    release_histogram character varying(10) DEFAULT 'never'::character varying,
    release_answer character varying(10) DEFAULT 'never'::character varying,
    duration integer DEFAULT 0,
    end_time timestamp with time zone,
    is_visible boolean DEFAULT false NOT NULL,
    allows_custom boolean DEFAULT false NOT NULL
);


--
-- Name: polls_poll_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.polls_poll_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: polls_poll_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.polls_poll_id_seq OWNED BY public.polls.poll_id;


--
-- Name: posts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.posts (
    id integer NOT NULL,
    thread_id integer NOT NULL,
    parent_id integer DEFAULT '-1'::integer,
    author_user_id character varying NOT NULL,
    content text NOT NULL,
    "timestamp" timestamp(0) with time zone NOT NULL,
    anonymous boolean NOT NULL,
    deleted boolean DEFAULT false NOT NULL,
    endorsed_by character varying,
    type integer NOT NULL,
    has_attachment boolean NOT NULL,
    render_markdown boolean DEFAULT false NOT NULL,
    version_id integer DEFAULT 1
);


--
-- Name: posts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.posts_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: posts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.posts_id_seq OWNED BY public.posts.id;


--
-- Name: queue; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.queue (
    entry_id integer NOT NULL,
    current_state text NOT NULL,
    removal_type text,
    queue_code text NOT NULL,
    user_id text NOT NULL,
    name text NOT NULL,
    time_in timestamp with time zone NOT NULL,
    time_out timestamp with time zone,
    added_by text NOT NULL,
    help_started_by text,
    removed_by text,
    contact_info text,
    last_time_in_queue timestamp with time zone,
    time_help_start timestamp with time zone,
    paused boolean DEFAULT false NOT NULL,
    time_paused integer DEFAULT 0 NOT NULL,
    time_paused_start timestamp with time zone,
    star_type character varying(16) DEFAULT 'none'::character varying
);


--
-- Name: queue_entry_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.queue_entry_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: queue_entry_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.queue_entry_id_seq OWNED BY public.queue.entry_id;


--
-- Name: queue_settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.queue_settings (
    id integer NOT NULL,
    open boolean NOT NULL,
    code text NOT NULL,
    token text,
    regex_pattern character varying,
    contact_information boolean DEFAULT true NOT NULL,
    message character varying(400) DEFAULT NULL::character varying,
    message_sent_time timestamp with time zone
);


--
-- Name: queue_settings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.queue_settings_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: queue_settings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.queue_settings_id_seq OWNED BY public.queue_settings.id;


--
-- Name: sections_registration; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sections_registration (
    sections_registration_id character varying(255) NOT NULL,
    course_section_id character varying(255) DEFAULT ''::character varying
);


--
-- Name: sections_rotating; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sections_rotating (
    sections_rotating_id integer NOT NULL
);


--
-- Name: seeking_team; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.seeking_team (
    g_id character varying(255) NOT NULL,
    user_id character varying NOT NULL,
    message character varying
);


--
-- Name: solution_ta_notes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.solution_ta_notes (
    g_id character varying(255) NOT NULL,
    component_id integer NOT NULL,
    solution_notes text NOT NULL,
    author character varying NOT NULL,
    edited_at timestamp(0) with time zone NOT NULL,
    itempool_item character varying(100) DEFAULT ''::character varying NOT NULL
);


--
-- Name: student_favorites; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.student_favorites (
    id integer NOT NULL,
    user_id character varying NOT NULL,
    thread_id integer
);


--
-- Name: student_favorites_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.student_favorites_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: student_favorites_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.student_favorites_id_seq OWNED BY public.student_favorites.id;


--
-- Name: teams; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.teams (
    team_id character varying(255) NOT NULL,
    user_id character varying(255) NOT NULL,
    state integer NOT NULL,
    last_viewed_time timestamp(6) with time zone DEFAULT NULL::timestamp with time zone
);


--
-- Name: thread_categories; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.thread_categories (
    thread_id integer NOT NULL,
    category_id integer NOT NULL
);


--
-- Name: threads; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.threads (
    id integer NOT NULL,
    title character varying NOT NULL,
    created_by character varying NOT NULL,
    pinned boolean DEFAULT false NOT NULL,
    deleted boolean DEFAULT false NOT NULL,
    merged_thread_id integer DEFAULT '-1'::integer,
    merged_post_id integer DEFAULT '-1'::integer,
    is_visible boolean NOT NULL,
    status integer DEFAULT 0 NOT NULL,
    lock_thread_date timestamp with time zone,
    pinned_expiration timestamp with time zone DEFAULT '1900-01-01 00:00:00-05'::timestamp with time zone NOT NULL,
    announced timestamp(6) with time zone DEFAULT NULL::timestamp with time zone,
    CONSTRAINT threads_status_check CHECK ((status = ANY (ARRAY['-1'::integer, 0, 1])))
);


--
-- Name: threads_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.threads_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: threads_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.threads_id_seq OWNED BY public.threads.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    user_id character varying NOT NULL,
    user_numeric_id character varying,
    user_givenname character varying NOT NULL,
    user_preferred_givenname character varying,
    user_familyname character varying NOT NULL,
    user_preferred_familyname character varying,
    user_email character varying NOT NULL,
    user_group integer NOT NULL,
    registration_section character varying(255),
    rotating_section integer,
    user_updated boolean DEFAULT false NOT NULL,
    instructor_updated boolean DEFAULT false NOT NULL,
    manual_registration boolean DEFAULT false,
    last_updated timestamp(6) with time zone,
    time_zone character varying DEFAULT 'NOT_SET/NOT_SET'::character varying NOT NULL,
    display_image_state character varying DEFAULT 'system'::character varying NOT NULL,
    registration_subsection character varying(255) DEFAULT ''::character varying NOT NULL,
    user_email_secondary character varying(255) DEFAULT ''::character varying NOT NULL,
    user_email_secondary_notify boolean DEFAULT false,
    registration_type character varying(255) DEFAULT 'graded'::character varying,
    user_pronouns character varying(255) DEFAULT ''::character varying,
    user_last_initial_format integer DEFAULT 0 NOT NULL,
    display_name_order character varying(255) DEFAULT 'GIVEN_F'::character varying NOT NULL,
    display_pronouns boolean DEFAULT false,
    user_preferred_locale character varying,
    previous_rotating_section integer,
    CONSTRAINT check_registration_type CHECK (((registration_type)::text = ANY (ARRAY[('graded'::character varying)::text, ('audit'::character varying)::text, ('withdrawn'::character varying)::text, ('staff'::character varying)::text]))),
    CONSTRAINT users_user_group_check CHECK (((user_group >= 1) AND (user_group <= 4))),
    CONSTRAINT users_user_last_initial_format_check CHECK (((user_last_initial_format >= 0) AND (user_last_initial_format <= 3)))
);


--
-- Name: viewed_responses; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.viewed_responses (
    thread_id integer NOT NULL,
    user_id character varying NOT NULL,
    "timestamp" timestamp(0) with time zone NOT NULL
);


--
-- Name: active_graders id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.active_graders ALTER COLUMN id SET DEFAULT nextval('public.active_graders_id_seq'::regclass);


--
-- Name: calendar_messages id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.calendar_messages ALTER COLUMN id SET DEFAULT nextval('public.calendar_messages_id_seq'::regclass);


--
-- Name: categories_list category_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categories_list ALTER COLUMN category_id SET DEFAULT nextval('public.categories_list_category_id_seq'::regclass);


--
-- Name: course_materials id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.course_materials ALTER COLUMN id SET DEFAULT nextval('public.course_materials_id_seq'::regclass);


--
-- Name: course_materials_access id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.course_materials_access ALTER COLUMN id SET DEFAULT nextval('public.course_materials_access_id_seq'::regclass);


--
-- Name: course_materials_sections id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.course_materials_sections ALTER COLUMN id SET DEFAULT nextval('public.course_materials_sections_id_seq'::regclass);


--
-- Name: forum_attachments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.forum_attachments ALTER COLUMN id SET DEFAULT nextval('public.forum_attachments_id_seq'::regclass);


--
-- Name: grade_inquiries id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.grade_inquiries ALTER COLUMN id SET DEFAULT nextval('public.grade_inquiries_id_seq'::regclass);


--
-- Name: grade_inquiry_discussion id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.grade_inquiry_discussion ALTER COLUMN id SET DEFAULT nextval('public.grade_inquiry_discussion_id_seq'::regclass);


--
-- Name: gradeable_access id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_access ALTER COLUMN id SET DEFAULT nextval('public.gradeable_access_id_seq'::regclass);


--
-- Name: gradeable_component gc_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_component ALTER COLUMN gc_id SET DEFAULT nextval('public.gradeable_component_gc_id_seq'::regclass);


--
-- Name: gradeable_component_mark gcm_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_component_mark ALTER COLUMN gcm_id SET DEFAULT nextval('public.gradeable_component_mark_gcm_id_seq'::regclass);


--
-- Name: gradeable_data gd_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_data ALTER COLUMN gd_id SET DEFAULT nextval('public.gradeable_data_gd_id_seq'::regclass);


--
-- Name: gradeable_data_overall_comment goc_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_data_overall_comment ALTER COLUMN goc_id SET DEFAULT nextval('public.gradeable_data_overall_comment_goc_id_seq'::regclass);


--
-- Name: lichen id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lichen ALTER COLUMN id SET DEFAULT nextval('public.lichen_id_seq'::regclass);


--
-- Name: lichen_run_access id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lichen_run_access ALTER COLUMN id SET DEFAULT nextval('public.lichen_run_access_id_seq'::regclass);


--
-- Name: notifications id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notifications ALTER COLUMN id SET DEFAULT nextval('public.notifications_id_seq'::regclass);


--
-- Name: poll_options option_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.poll_options ALTER COLUMN option_id SET DEFAULT nextval('public.poll_options_option_id_seq'::regclass);


--
-- Name: poll_responses id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.poll_responses ALTER COLUMN id SET DEFAULT nextval('public.poll_responses_id_seq'::regclass);


--
-- Name: polls poll_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.polls ALTER COLUMN poll_id SET DEFAULT nextval('public.polls_poll_id_seq'::regclass);


--
-- Name: posts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posts ALTER COLUMN id SET DEFAULT nextval('public.posts_id_seq'::regclass);


--
-- Name: queue entry_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.queue ALTER COLUMN entry_id SET DEFAULT nextval('public.queue_entry_id_seq'::regclass);


--
-- Name: queue_settings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.queue_settings ALTER COLUMN id SET DEFAULT nextval('public.queue_settings_id_seq'::regclass);


--
-- Name: student_favorites id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.student_favorites ALTER COLUMN id SET DEFAULT nextval('public.student_favorites_id_seq'::regclass);


--
-- Name: threads id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.threads ALTER COLUMN id SET DEFAULT nextval('public.threads_id_seq'::regclass);


--
-- Name: active_graders active_graders_grader_id_gc_id_ag_team_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.active_graders
    ADD CONSTRAINT active_graders_grader_id_gc_id_ag_team_id_key UNIQUE (grader_id, gc_id, ag_team_id);


--
-- Name: active_graders active_graders_grader_id_gc_id_ag_user_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.active_graders
    ADD CONSTRAINT active_graders_grader_id_gc_id_ag_user_id_key UNIQUE (grader_id, gc_id, ag_user_id);


--
-- Name: active_graders active_graders_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.active_graders
    ADD CONSTRAINT active_graders_pkey PRIMARY KEY (id);


--
-- Name: gradeable_teams anon_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_teams
    ADD CONSTRAINT anon_id_unique UNIQUE (anon_id);


--
-- Name: autograding_metrics autograding_metrics_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.autograding_metrics
    ADD CONSTRAINT autograding_metrics_pkey PRIMARY KEY (user_id, team_id, g_id, testcase_id, g_version);


--
-- Name: categories_list categories_list_pk; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categories_list
    ADD CONSTRAINT categories_list_pk PRIMARY KEY (category_id);


--
-- Name: categories_list category_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categories_list
    ADD CONSTRAINT category_unique UNIQUE (category_desc);


--
-- Name: course_materials course_materials_path_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.course_materials
    ADD CONSTRAINT course_materials_path_key UNIQUE (path);


--
-- Name: course_materials course_materials_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.course_materials
    ADD CONSTRAINT course_materials_pkey PRIMARY KEY (id);


--
-- Name: course_materials_sections course_materials_sections_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.course_materials_sections
    ADD CONSTRAINT course_materials_sections_pkey PRIMARY KEY (id);


--
-- Name: electronic_gradeable_data egd_g_user_team_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.electronic_gradeable_data
    ADD CONSTRAINT egd_g_user_team_id_unique UNIQUE (g_id, user_id, team_id, g_version);


--
-- Name: electronic_gradeable_version egv_g_user_team_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.electronic_gradeable_version
    ADD CONSTRAINT egv_g_user_team_id_unique UNIQUE (g_id, user_id, team_id);


--
-- Name: electronic_gradeable electronic_gradeable_g_id_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.electronic_gradeable
    ADD CONSTRAINT electronic_gradeable_g_id_pkey PRIMARY KEY (g_id);


--
-- Name: forum_attachments forum_attachments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.forum_attachments
    ADD CONSTRAINT forum_attachments_pkey PRIMARY KEY (id);


--
-- Name: forum_upducks forum_upducks_user_id_post_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.forum_upducks
    ADD CONSTRAINT forum_upducks_user_id_post_id_key UNIQUE (user_id, post_id);


--
-- Name: gradeable_data g_id_gd_team_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_data
    ADD CONSTRAINT g_id_gd_team_id_unique UNIQUE (g_id, gd_team_id);


--
-- Name: grade_inquiries grade_inquiries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.grade_inquiries
    ADD CONSTRAINT grade_inquiries_pkey PRIMARY KEY (id);


--
-- Name: grade_inquiry_discussion grade_inquiry_discussion_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.grade_inquiry_discussion
    ADD CONSTRAINT grade_inquiry_discussion_pkey PRIMARY KEY (id);


--
-- Name: grade_override grade_override_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.grade_override
    ADD CONSTRAINT grade_override_pkey PRIMARY KEY (user_id, g_id);


--
-- Name: gradeable_access gradeable_access_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_access
    ADD CONSTRAINT gradeable_access_pkey PRIMARY KEY (id);


--
-- Name: gradeable_anon gradeable_anon_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_anon
    ADD CONSTRAINT gradeable_anon_pkey PRIMARY KEY (g_id, anon_id);


--
-- Name: gradeable_component_data gradeable_component_data_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_component_data
    ADD CONSTRAINT gradeable_component_data_pkey PRIMARY KEY (gc_id, gd_id, gcd_grader_id);


--
-- Name: gradeable_component_mark_data gradeable_component_mark_data_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_component_mark_data
    ADD CONSTRAINT gradeable_component_mark_data_pkey PRIMARY KEY (gcm_id, gc_id, gd_id, gcd_grader_id);


--
-- Name: gradeable_component_mark gradeable_component_mark_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_component_mark
    ADD CONSTRAINT gradeable_component_mark_pkey PRIMARY KEY (gcm_id);


--
-- Name: gradeable_component gradeable_component_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_component
    ADD CONSTRAINT gradeable_component_pkey PRIMARY KEY (gc_id);


--
-- Name: gradeable_data_overall_comment gradeable_data_overall_comment_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_data_overall_comment
    ADD CONSTRAINT gradeable_data_overall_comment_pkey PRIMARY KEY (goc_id);


--
-- Name: gradeable_data_overall_comment gradeable_data_overall_comment_team_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_data_overall_comment
    ADD CONSTRAINT gradeable_data_overall_comment_team_unique UNIQUE (g_id, goc_team_id, goc_grader_id);


--
-- Name: gradeable_data_overall_comment gradeable_data_overall_comment_user_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_data_overall_comment
    ADD CONSTRAINT gradeable_data_overall_comment_user_unique UNIQUE (g_id, goc_user_id, goc_grader_id);


--
-- Name: gradeable_data gradeable_data_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_data
    ADD CONSTRAINT gradeable_data_pkey PRIMARY KEY (gd_id);


--
-- Name: gradeable gradeable_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable
    ADD CONSTRAINT gradeable_pkey PRIMARY KEY (g_id);


--
-- Name: grade_inquiries gradeable_team_gc_id; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.grade_inquiries
    ADD CONSTRAINT gradeable_team_gc_id UNIQUE (team_id, g_id, gc_id);


--
-- Name: gradeable_teams gradeable_teams_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_teams
    ADD CONSTRAINT gradeable_teams_pkey PRIMARY KEY (team_id);


--
-- Name: gradeable_data gradeable_unqiue; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_data
    ADD CONSTRAINT gradeable_unqiue UNIQUE (g_id, gd_user_id);


--
-- Name: grade_inquiries gradeable_user_gc_id; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.grade_inquiries
    ADD CONSTRAINT gradeable_user_gc_id UNIQUE (user_id, g_id, gc_id);


--
-- Name: grading_registration grading_registration_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.grading_registration
    ADD CONSTRAINT grading_registration_pkey PRIMARY KEY (sections_registration_id, user_id);


--
-- Name: grading_rotating grading_rotating_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.grading_rotating
    ADD CONSTRAINT grading_rotating_pkey PRIMARY KEY (sections_rotating_id, user_id, g_id);


--
-- Name: late_day_exceptions late_day_exceptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.late_day_exceptions
    ADD CONSTRAINT late_day_exceptions_pkey PRIMARY KEY (g_id, user_id);


--
-- Name: late_days late_days_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.late_days
    ADD CONSTRAINT late_days_pkey PRIMARY KEY (user_id, since_timestamp);


--
-- Name: late_day_cache ldc_g_team_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.late_day_cache
    ADD CONSTRAINT ldc_g_team_id_unique UNIQUE (g_id, user_id, team_id);


--
-- Name: lichen lichen_gradeable_id_config_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lichen
    ADD CONSTRAINT lichen_gradeable_id_config_id_key UNIQUE (gradeable_id, config_id);


--
-- Name: lichen lichen_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lichen
    ADD CONSTRAINT lichen_pkey PRIMARY KEY (id);


--
-- Name: lichen_run_access lichen_run_access_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lichen_run_access
    ADD CONSTRAINT lichen_run_access_pkey PRIMARY KEY (id);


--
-- Name: migrations_course migrations_course_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations_course
    ADD CONSTRAINT migrations_course_pkey PRIMARY KEY (id);


--
-- Name: notification_settings notification_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_settings
    ADD CONSTRAINT notification_settings_pkey PRIMARY KEY (user_id);


--
-- Name: notifications notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_pkey PRIMARY KEY (id);


--
-- Name: peer_assign peer_assign_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.peer_assign
    ADD CONSTRAINT peer_assign_pkey PRIMARY KEY (g_id, grader_id, user_id);


--
-- Name: peer_feedback peer_feedback_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.peer_feedback
    ADD CONSTRAINT peer_feedback_pkey PRIMARY KEY (g_id, grader_id, user_id);


--
-- Name: peer_grading_panel peer_grading_panel_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.peer_grading_panel
    ADD CONSTRAINT peer_grading_panel_pkey PRIMARY KEY (g_id);


--
-- Name: poll_options poll_options_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.poll_options
    ADD CONSTRAINT poll_options_pkey PRIMARY KEY (option_id);


--
-- Name: poll_responses poll_responses_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.poll_responses
    ADD CONSTRAINT poll_responses_pkey PRIMARY KEY (id);


--
-- Name: polls polls_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.polls
    ADD CONSTRAINT polls_pkey PRIMARY KEY (poll_id);


--
-- Name: posts posts_pk; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posts
    ADD CONSTRAINT posts_pk PRIMARY KEY (id);


--
-- Name: queue queue_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.queue
    ADD CONSTRAINT queue_pkey PRIMARY KEY (entry_id);


--
-- Name: queue_settings queue_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.queue_settings
    ADD CONSTRAINT queue_settings_pkey PRIMARY KEY (id);


--
-- Name: sections_registration sections_registration_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sections_registration
    ADD CONSTRAINT sections_registration_pkey PRIMARY KEY (sections_registration_id);


--
-- Name: sections_rotating sections_rotating_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sections_rotating
    ADD CONSTRAINT sections_rotating_pkey PRIMARY KEY (sections_rotating_id);


--
-- Name: seeking_team seeking_team_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.seeking_team
    ADD CONSTRAINT seeking_team_pkey PRIMARY KEY (g_id, user_id);


--
-- Name: student_favorites student_favorites_pk; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.student_favorites
    ADD CONSTRAINT student_favorites_pk PRIMARY KEY (id);


--
-- Name: teams teams_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.teams
    ADD CONSTRAINT teams_pkey PRIMARY KEY (team_id, user_id);


--
-- Name: thread_categories thread_and_category_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.thread_categories
    ADD CONSTRAINT thread_and_category_unique UNIQUE (thread_id, category_id);


--
-- Name: threads threads_pk; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.threads
    ADD CONSTRAINT threads_pk PRIMARY KEY (id);


--
-- Name: course_materials_sections unique_course_material_section; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.course_materials_sections
    ADD CONSTRAINT unique_course_material_section UNIQUE (course_material_id, section_id);


--
-- Name: student_favorites user_and_thread_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.student_favorites
    ADD CONSTRAINT user_and_thread_unique UNIQUE (user_id, thread_id);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (user_id);


--
-- Name: viewed_responses viewed_responses_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.viewed_responses
    ADD CONSTRAINT viewed_responses_pkey PRIMARY KEY (thread_id, user_id);


--
-- Name: forum_posts_history_edit_timestamp_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX forum_posts_history_edit_timestamp_index ON public.forum_posts_history USING btree (edit_timestamp DESC);


--
-- Name: forum_posts_history_post_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX forum_posts_history_post_id_index ON public.forum_posts_history USING btree (post_id);


--
-- Name: gradeable_allowed_minutes_override_g_id_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX gradeable_allowed_minutes_override_g_id_idx ON public.gradeable_allowed_minutes_override USING btree (g_id);


--
-- Name: gradeable_component_data_gd; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX gradeable_component_data_gd ON public.gradeable_component_data USING btree (gd_id);


--
-- Name: gradeable_component_data_no_grader_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX gradeable_component_data_no_grader_index ON public.gradeable_component_data USING btree (gc_id, gd_id);


--
-- Name: gradeable_component_mark_data_gcm_id_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX gradeable_component_mark_data_gcm_id_idx ON public.gradeable_component_mark_data USING btree (gcm_id);


--
-- Name: gradeable_component_mark_data_gd_id_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX gradeable_component_mark_data_gd_id_idx ON public.gradeable_component_mark_data USING btree (gd_id);


--
-- Name: gradeable_team_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX gradeable_team_unique ON public.grade_inquiries USING btree (team_id, g_id) WHERE (gc_id IS NULL);


--
-- Name: gradeable_user_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX gradeable_user_unique ON public.grade_inquiries USING btree (user_id, g_id) WHERE (gc_id IS NULL);


--
-- Name: grading_registration_user_id_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX grading_registration_user_id_idx ON public.grading_registration USING btree (user_id);


--
-- Name: grading_registration_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX grading_registration_user_id_index ON public.grading_registration USING btree (user_id);


--
-- Name: ldc_g_user_id_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX ldc_g_user_id_unique ON public.late_day_cache USING btree (g_id, user_id) WHERE (team_id IS NULL);


--
-- Name: notifications_to_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notifications_to_user_id_index ON public.notifications USING btree (to_user_id);


--
-- Name: users_user_numeric_id_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_user_numeric_id_idx ON public.users USING btree (user_numeric_id);


--
-- Name: users add_course_user; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER add_course_user AFTER INSERT OR UPDATE ON public.users FOR EACH ROW EXECUTE PROCEDURE public.add_course_user();


--
-- Name: users before_update_users_update_previous_rotating_section; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER before_update_users_update_previous_rotating_section BEFORE UPDATE OF rotating_section ON public.users FOR EACH ROW EXECUTE PROCEDURE public.update_previous_rotating_section();


--
-- Name: electronic_gradeable electronic_gradeable_change; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER electronic_gradeable_change AFTER INSERT OR UPDATE OF eg_submission_due_date, eg_has_due_date, eg_allow_late_submission, eg_late_days ON public.electronic_gradeable FOR EACH ROW EXECUTE PROCEDURE public.electronic_gradeable_change();


--
-- Name: gradeable gradeable_delete; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER gradeable_delete BEFORE DELETE ON public.gradeable FOR EACH ROW EXECUTE PROCEDURE public.gradeable_delete();


--
-- Name: electronic_gradeable_version gradeable_version_change; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER gradeable_version_change AFTER INSERT OR DELETE OR UPDATE ON public.electronic_gradeable_version FOR EACH ROW EXECUTE PROCEDURE public.gradeable_version_change();


--
-- Name: late_day_exceptions late_day_extension_change; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER late_day_extension_change AFTER INSERT OR DELETE OR UPDATE ON public.late_day_exceptions FOR EACH ROW EXECUTE PROCEDURE public.late_day_extension_change();


--
-- Name: late_days late_days_allowed_change; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER late_days_allowed_change AFTER INSERT OR DELETE OR UPDATE ON public.late_days FOR EACH ROW EXECUTE PROCEDURE public.late_days_allowed_change();


--
-- Name: active_graders active_graders_ag_team_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.active_graders
    ADD CONSTRAINT active_graders_ag_team_id_fkey FOREIGN KEY (ag_team_id) REFERENCES public.gradeable_teams(team_id);


--
-- Name: active_graders active_graders_ag_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.active_graders
    ADD CONSTRAINT active_graders_ag_user_id_fkey FOREIGN KEY (ag_user_id) REFERENCES public.users(user_id);


--
-- Name: active_graders active_graders_gc_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.active_graders
    ADD CONSTRAINT active_graders_gc_id_fkey FOREIGN KEY (gc_id) REFERENCES public.gradeable_component(gc_id) ON DELETE CASCADE;


--
-- Name: active_graders active_graders_grader_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.active_graders
    ADD CONSTRAINT active_graders_grader_id_fkey FOREIGN KEY (grader_id) REFERENCES public.users(user_id);


--
-- Name: course_materials course_materials_last_edit_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.course_materials
    ADD CONSTRAINT course_materials_last_edit_by_fkey FOREIGN KEY (last_edit_by) REFERENCES public.users(user_id);


--
-- Name: course_materials course_materials_uploaded_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.course_materials
    ADD CONSTRAINT course_materials_uploaded_by_fkey FOREIGN KEY (uploaded_by) REFERENCES public.users(user_id);


--
-- Name: electronic_gradeable_data electronic_gradeable_data_gid; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.electronic_gradeable_data
    ADD CONSTRAINT electronic_gradeable_data_gid FOREIGN KEY (g_id) REFERENCES public.gradeable(g_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: electronic_gradeable_data electronic_gradeable_data_team; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.electronic_gradeable_data
    ADD CONSTRAINT electronic_gradeable_data_team FOREIGN KEY (team_id) REFERENCES public.gradeable_teams(team_id) ON UPDATE CASCADE;


--
-- Name: electronic_gradeable_data electronic_gradeable_data_user; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.electronic_gradeable_data
    ADD CONSTRAINT electronic_gradeable_data_user FOREIGN KEY (user_id) REFERENCES public.users(user_id) ON UPDATE CASCADE;


--
-- Name: electronic_gradeable electronic_gradeable_g_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.electronic_gradeable
    ADD CONSTRAINT electronic_gradeable_g_id_fkey FOREIGN KEY (g_id) REFERENCES public.gradeable(g_id) ON DELETE CASCADE;


--
-- Name: electronic_gradeable_version electronic_gradeable_version; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.electronic_gradeable_version
    ADD CONSTRAINT electronic_gradeable_version FOREIGN KEY (g_id, user_id, team_id, active_version) REFERENCES public.electronic_gradeable_data(g_id, user_id, team_id, g_version) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: electronic_gradeable_version electronic_gradeable_version_g_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.electronic_gradeable_version
    ADD CONSTRAINT electronic_gradeable_version_g_id FOREIGN KEY (g_id) REFERENCES public.gradeable(g_id) ON DELETE CASCADE;


--
-- Name: electronic_gradeable_version electronic_gradeable_version_team; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.electronic_gradeable_version
    ADD CONSTRAINT electronic_gradeable_version_team FOREIGN KEY (team_id) REFERENCES public.gradeable_teams(team_id) ON UPDATE CASCADE;


--
-- Name: electronic_gradeable_version electronic_gradeable_version_user; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.electronic_gradeable_version
    ADD CONSTRAINT electronic_gradeable_version_user FOREIGN KEY (user_id) REFERENCES public.users(user_id) ON UPDATE CASCADE;


--
-- Name: course_materials_sections fk_course_material_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.course_materials_sections
    ADD CONSTRAINT fk_course_material_id FOREIGN KEY (course_material_id) REFERENCES public.course_materials(id) ON DELETE CASCADE;


--
-- Name: course_materials_access fk_course_material_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.course_materials_access
    ADD CONSTRAINT fk_course_material_id FOREIGN KEY (course_material_id) REFERENCES public.course_materials(id);


--
-- Name: electronic_gradeable fk_depends_on; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.electronic_gradeable
    ADD CONSTRAINT fk_depends_on FOREIGN KEY (eg_depends_on) REFERENCES public.electronic_gradeable(g_id);


--
-- Name: lichen fk_gradeable_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lichen
    ADD CONSTRAINT fk_gradeable_id FOREIGN KEY (gradeable_id) REFERENCES public.gradeable(g_id) ON DELETE CASCADE;


--
-- Name: lichen_run_access fk_lichen_run_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lichen_run_access
    ADD CONSTRAINT fk_lichen_run_id FOREIGN KEY (lichen_run_id) REFERENCES public.lichen(id) ON DELETE CASCADE;


--
-- Name: course_materials_sections fk_section_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.course_materials_sections
    ADD CONSTRAINT fk_section_id FOREIGN KEY (section_id) REFERENCES public.sections_registration(sections_registration_id) ON DELETE CASCADE;


--
-- Name: course_materials_access fk_user_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.course_materials_access
    ADD CONSTRAINT fk_user_id FOREIGN KEY (user_id) REFERENCES public.users(user_id);


--
-- Name: gradeable_allowed_minutes_override fk_user_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_allowed_minutes_override
    ADD CONSTRAINT fk_user_id FOREIGN KEY (user_id) REFERENCES public.users(user_id);


--
-- Name: forum_posts_history forum_posts_history_edit_author_fk; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.forum_posts_history
    ADD CONSTRAINT forum_posts_history_edit_author_fk FOREIGN KEY (edit_author) REFERENCES public.users(user_id);


--
-- Name: forum_posts_history forum_posts_history_post_id_fk; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.forum_posts_history
    ADD CONSTRAINT forum_posts_history_post_id_fk FOREIGN KEY (post_id) REFERENCES public.posts(id);


--
-- Name: forum_upducks forum_upducks_post_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.forum_upducks
    ADD CONSTRAINT forum_upducks_post_id_fkey FOREIGN KEY (post_id) REFERENCES public.posts(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: forum_upducks forum_upducks_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.forum_upducks
    ADD CONSTRAINT forum_upducks_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(user_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: grade_inquiries grade_inquiries_fk0; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.grade_inquiries
    ADD CONSTRAINT grade_inquiries_fk0 FOREIGN KEY (g_id) REFERENCES public.gradeable(g_id);


--
-- Name: grade_inquiries grade_inquiries_fk1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.grade_inquiries
    ADD CONSTRAINT grade_inquiries_fk1 FOREIGN KEY (user_id) REFERENCES public.users(user_id);


--
-- Name: grade_inquiries grade_inquiries_fk2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.grade_inquiries
    ADD CONSTRAINT grade_inquiries_fk2 FOREIGN KEY (team_id) REFERENCES public.gradeable_teams(team_id);


--
-- Name: grade_inquiries grade_inquiries_fk3; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.grade_inquiries
    ADD CONSTRAINT grade_inquiries_fk3 FOREIGN KEY (gc_id) REFERENCES public.gradeable_component(gc_id);


--
-- Name: grade_inquiry_discussion grade_inquiry_discussion_fk0; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.grade_inquiry_discussion
    ADD CONSTRAINT grade_inquiry_discussion_fk0 FOREIGN KEY (grade_inquiry_id) REFERENCES public.grade_inquiries(id);


--
-- Name: grade_inquiry_discussion grade_inquiry_discussion_fk1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.grade_inquiry_discussion
    ADD CONSTRAINT grade_inquiry_discussion_fk1 FOREIGN KEY (user_id) REFERENCES public.users(user_id);


--
-- Name: grade_inquiry_discussion grade_inquiry_discussion_grade_inquiries_id_fk; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.grade_inquiry_discussion
    ADD CONSTRAINT grade_inquiry_discussion_grade_inquiries_id_fk FOREIGN KEY (grade_inquiry_id) REFERENCES public.grade_inquiries(id) ON UPDATE CASCADE;


--
-- Name: grade_override grade_override_g_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.grade_override
    ADD CONSTRAINT grade_override_g_id_fkey FOREIGN KEY (g_id) REFERENCES public.gradeable(g_id) ON DELETE CASCADE;


--
-- Name: grade_override grade_override_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.grade_override
    ADD CONSTRAINT grade_override_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(user_id) ON UPDATE CASCADE;


--
-- Name: gradeable_access gradeable_access_fk0; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_access
    ADD CONSTRAINT gradeable_access_fk0 FOREIGN KEY (g_id) REFERENCES public.gradeable(g_id) ON DELETE CASCADE;


--
-- Name: gradeable_access gradeable_access_fk1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_access
    ADD CONSTRAINT gradeable_access_fk1 FOREIGN KEY (user_id) REFERENCES public.users(user_id) ON DELETE CASCADE;


--
-- Name: gradeable_access gradeable_access_fk2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_access
    ADD CONSTRAINT gradeable_access_fk2 FOREIGN KEY (team_id) REFERENCES public.gradeable_teams(team_id);


--
-- Name: gradeable_access gradeable_access_fk3; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_access
    ADD CONSTRAINT gradeable_access_fk3 FOREIGN KEY (accessor_id) REFERENCES public.users(user_id) ON DELETE CASCADE;


--
-- Name: gradeable_anon gradeable_anon_g_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_anon
    ADD CONSTRAINT gradeable_anon_g_id_fkey FOREIGN KEY (g_id) REFERENCES public.gradeable(g_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: gradeable_anon gradeable_anon_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_anon
    ADD CONSTRAINT gradeable_anon_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(user_id) ON UPDATE CASCADE;


--
-- Name: gradeable_component_data gradeable_component_data_gc_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_component_data
    ADD CONSTRAINT gradeable_component_data_gc_id_fkey FOREIGN KEY (gc_id) REFERENCES public.gradeable_component(gc_id) ON DELETE CASCADE;


--
-- Name: gradeable_component_data gradeable_component_data_gcd_grader_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_component_data
    ADD CONSTRAINT gradeable_component_data_gcd_grader_id_fkey FOREIGN KEY (gcd_grader_id) REFERENCES public.users(user_id) ON UPDATE CASCADE;


--
-- Name: gradeable_component_data gradeable_component_data_gd_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_component_data
    ADD CONSTRAINT gradeable_component_data_gd_id_fkey FOREIGN KEY (gd_id) REFERENCES public.gradeable_data(gd_id) ON DELETE CASCADE;


--
-- Name: gradeable_component_data gradeable_component_data_verifier_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_component_data
    ADD CONSTRAINT gradeable_component_data_verifier_id_fkey FOREIGN KEY (gcd_verifier_id) REFERENCES public.users(user_id);


--
-- Name: gradeable_component gradeable_component_g_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_component
    ADD CONSTRAINT gradeable_component_g_id_fkey FOREIGN KEY (g_id) REFERENCES public.gradeable(g_id) ON DELETE CASCADE;


--
-- Name: gradeable_component_mark_data gradeable_component_mark_data_gcm_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_component_mark_data
    ADD CONSTRAINT gradeable_component_mark_data_gcm_id_fkey FOREIGN KEY (gcm_id) REFERENCES public.gradeable_component_mark(gcm_id) ON DELETE CASCADE;


--
-- Name: gradeable_component_mark_data gradeable_component_mark_data_gd_id_and_gc_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_component_mark_data
    ADD CONSTRAINT gradeable_component_mark_data_gd_id_and_gc_id_fkey FOREIGN KEY (gd_id, gc_id, gcd_grader_id) REFERENCES public.gradeable_component_data(gd_id, gc_id, gcd_grader_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: gradeable_component_mark gradeable_component_mark_gc_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_component_mark
    ADD CONSTRAINT gradeable_component_mark_gc_id_fkey FOREIGN KEY (gc_id) REFERENCES public.gradeable_component(gc_id) ON DELETE CASCADE;


--
-- Name: gradeable_data gradeable_data_g_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_data
    ADD CONSTRAINT gradeable_data_g_id_fkey FOREIGN KEY (g_id) REFERENCES public.gradeable(g_id) ON DELETE CASCADE;


--
-- Name: gradeable_data gradeable_data_gd_team_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_data
    ADD CONSTRAINT gradeable_data_gd_team_id_fkey FOREIGN KEY (gd_team_id) REFERENCES public.gradeable_teams(team_id) ON UPDATE CASCADE;


--
-- Name: gradeable_data gradeable_data_gd_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_data
    ADD CONSTRAINT gradeable_data_gd_user_id_fkey FOREIGN KEY (gd_user_id) REFERENCES public.users(user_id) ON UPDATE CASCADE;


--
-- Name: gradeable_data_overall_comment gradeable_data_overall_comment_g_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_data_overall_comment
    ADD CONSTRAINT gradeable_data_overall_comment_g_id_fkey FOREIGN KEY (g_id) REFERENCES public.gradeable(g_id) ON DELETE CASCADE;


--
-- Name: gradeable_data_overall_comment gradeable_data_overall_comment_goc_grader_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_data_overall_comment
    ADD CONSTRAINT gradeable_data_overall_comment_goc_grader_id FOREIGN KEY (goc_grader_id) REFERENCES public.users(user_id) ON DELETE CASCADE;


--
-- Name: gradeable_data_overall_comment gradeable_data_overall_comment_goc_team_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_data_overall_comment
    ADD CONSTRAINT gradeable_data_overall_comment_goc_team_id_fkey FOREIGN KEY (goc_team_id) REFERENCES public.gradeable_teams(team_id) ON DELETE CASCADE;


--
-- Name: gradeable_data_overall_comment gradeable_data_overall_comment_goc_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_data_overall_comment
    ADD CONSTRAINT gradeable_data_overall_comment_goc_user_id_fkey FOREIGN KEY (goc_user_id) REFERENCES public.users(user_id) ON DELETE CASCADE;


--
-- Name: gradeable_teams gradeable_teams_g_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_teams
    ADD CONSTRAINT gradeable_teams_g_id_fkey FOREIGN KEY (g_id) REFERENCES public.gradeable(g_id) ON DELETE CASCADE;


--
-- Name: gradeable_teams gradeable_teams_registration_section_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_teams
    ADD CONSTRAINT gradeable_teams_registration_section_fkey FOREIGN KEY (registration_section) REFERENCES public.sections_registration(sections_registration_id);


--
-- Name: gradeable_teams gradeable_teams_rotating_section_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gradeable_teams
    ADD CONSTRAINT gradeable_teams_rotating_section_fkey FOREIGN KEY (rotating_section) REFERENCES public.sections_rotating(sections_rotating_id);


--
-- Name: grading_registration grading_registration_sections_registration_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.grading_registration
    ADD CONSTRAINT grading_registration_sections_registration_id_fkey FOREIGN KEY (sections_registration_id) REFERENCES public.sections_registration(sections_registration_id);


--
-- Name: grading_registration grading_registration_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.grading_registration
    ADD CONSTRAINT grading_registration_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(user_id) ON UPDATE CASCADE;


--
-- Name: grading_rotating grading_rotating_g_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.grading_rotating
    ADD CONSTRAINT grading_rotating_g_id_fkey FOREIGN KEY (g_id) REFERENCES public.gradeable(g_id) ON DELETE CASCADE;


--
-- Name: grading_rotating grading_rotating_sections_rotating_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.grading_rotating
    ADD CONSTRAINT grading_rotating_sections_rotating_fkey FOREIGN KEY (sections_rotating_id) REFERENCES public.sections_rotating(sections_rotating_id) ON DELETE CASCADE;


--
-- Name: grading_rotating grading_rotating_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.grading_rotating
    ADD CONSTRAINT grading_rotating_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(user_id) ON UPDATE CASCADE;


--
-- Name: late_day_cache late_day_cache_g_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.late_day_cache
    ADD CONSTRAINT late_day_cache_g_id FOREIGN KEY (g_id) REFERENCES public.gradeable(g_id) ON DELETE CASCADE;


--
-- Name: late_day_cache late_day_cache_team; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.late_day_cache
    ADD CONSTRAINT late_day_cache_team FOREIGN KEY (team_id) REFERENCES public.gradeable_teams(team_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: late_day_cache late_day_cache_user; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.late_day_cache
    ADD CONSTRAINT late_day_cache_user FOREIGN KEY (user_id) REFERENCES public.users(user_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: late_day_exceptions late_day_exceptions_g_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.late_day_exceptions
    ADD CONSTRAINT late_day_exceptions_g_id_fkey FOREIGN KEY (g_id) REFERENCES public.gradeable(g_id) ON DELETE CASCADE;


--
-- Name: late_day_exceptions late_day_exceptions_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.late_day_exceptions
    ADD CONSTRAINT late_day_exceptions_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(user_id) ON UPDATE CASCADE;


--
-- Name: late_days late_days_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.late_days
    ADD CONSTRAINT late_days_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(user_id) ON UPDATE CASCADE;


--
-- Name: notification_settings notification_settings_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_settings
    ADD CONSTRAINT notification_settings_fkey FOREIGN KEY (user_id) REFERENCES public.users(user_id) ON UPDATE CASCADE;


--
-- Name: notifications notifications_from_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_from_user_id_fkey FOREIGN KEY (from_user_id) REFERENCES public.users(user_id) ON UPDATE CASCADE;


--
-- Name: notifications notifications_to_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_to_user_id_fkey FOREIGN KEY (to_user_id) REFERENCES public.users(user_id) ON UPDATE CASCADE;


--
-- Name: peer_assign peer_assign_g_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.peer_assign
    ADD CONSTRAINT peer_assign_g_id_fkey FOREIGN KEY (g_id) REFERENCES public.gradeable(g_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: peer_assign peer_assign_grader_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.peer_assign
    ADD CONSTRAINT peer_assign_grader_id_fkey FOREIGN KEY (grader_id) REFERENCES public.users(user_id) ON UPDATE CASCADE;


--
-- Name: peer_assign peer_assign_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.peer_assign
    ADD CONSTRAINT peer_assign_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(user_id) ON UPDATE CASCADE;


--
-- Name: peer_feedback peer_feedback_g_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.peer_feedback
    ADD CONSTRAINT peer_feedback_g_id_fkey FOREIGN KEY (g_id) REFERENCES public.gradeable(g_id) ON DELETE CASCADE;


--
-- Name: peer_feedback peer_feedback_grader_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.peer_feedback
    ADD CONSTRAINT peer_feedback_grader_id_fkey FOREIGN KEY (grader_id) REFERENCES public.users(user_id) ON DELETE CASCADE;


--
-- Name: peer_feedback peer_feedback_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.peer_feedback
    ADD CONSTRAINT peer_feedback_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(user_id) ON DELETE CASCADE;


--
-- Name: peer_grading_panel peer_grading_panel_g_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.peer_grading_panel
    ADD CONSTRAINT peer_grading_panel_g_id_fkey FOREIGN KEY (g_id) REFERENCES public.electronic_gradeable(g_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: poll_options poll_options_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.poll_options
    ADD CONSTRAINT poll_options_fkey FOREIGN KEY (author_id) REFERENCES public.users(user_id) ON UPDATE CASCADE;


--
-- Name: poll_options poll_options_poll_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.poll_options
    ADD CONSTRAINT poll_options_poll_id_fkey FOREIGN KEY (poll_id) REFERENCES public.polls(poll_id);


--
-- Name: poll_responses poll_responses_poll_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.poll_responses
    ADD CONSTRAINT poll_responses_poll_id_fkey FOREIGN KEY (poll_id) REFERENCES public.polls(poll_id);


--
-- Name: poll_responses poll_responses_student_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.poll_responses
    ADD CONSTRAINT poll_responses_student_id_fkey FOREIGN KEY (student_id) REFERENCES public.users(user_id);


--
-- Name: posts posts_fk0; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posts
    ADD CONSTRAINT posts_fk0 FOREIGN KEY (thread_id) REFERENCES public.threads(id);


--
-- Name: posts posts_fk1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posts
    ADD CONSTRAINT posts_fk1 FOREIGN KEY (author_user_id) REFERENCES public.users(user_id);


--
-- Name: queue queue_added_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.queue
    ADD CONSTRAINT queue_added_by_fkey FOREIGN KEY (added_by) REFERENCES public.users(user_id);


--
-- Name: queue queue_help_started_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.queue
    ADD CONSTRAINT queue_help_started_by_fkey FOREIGN KEY (help_started_by) REFERENCES public.users(user_id);


--
-- Name: queue queue_removed_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.queue
    ADD CONSTRAINT queue_removed_by_fkey FOREIGN KEY (removed_by) REFERENCES public.users(user_id);


--
-- Name: queue queue_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.queue
    ADD CONSTRAINT queue_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(user_id);


--
-- Name: seeking_team seeking_team_g_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.seeking_team
    ADD CONSTRAINT seeking_team_g_id_fkey FOREIGN KEY (g_id) REFERENCES public.gradeable(g_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: solution_ta_notes solution_ta_notes_author_fk; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.solution_ta_notes
    ADD CONSTRAINT solution_ta_notes_author_fk FOREIGN KEY (author) REFERENCES public.users(user_id);


--
-- Name: solution_ta_notes solution_ta_notes_g_id_fk; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.solution_ta_notes
    ADD CONSTRAINT solution_ta_notes_g_id_fk FOREIGN KEY (g_id) REFERENCES public.gradeable(g_id);


--
-- Name: student_favorites student_favorites_fk0; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.student_favorites
    ADD CONSTRAINT student_favorites_fk0 FOREIGN KEY (user_id) REFERENCES public.users(user_id);


--
-- Name: student_favorites student_favorites_fk1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.student_favorites
    ADD CONSTRAINT student_favorites_fk1 FOREIGN KEY (thread_id) REFERENCES public.threads(id);


--
-- Name: teams teams_team_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.teams
    ADD CONSTRAINT teams_team_id_fkey FOREIGN KEY (team_id) REFERENCES public.gradeable_teams(team_id) ON DELETE CASCADE;


--
-- Name: teams teams_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.teams
    ADD CONSTRAINT teams_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(user_id) ON UPDATE CASCADE;


--
-- Name: thread_categories thread_categories_fk0; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.thread_categories
    ADD CONSTRAINT thread_categories_fk0 FOREIGN KEY (thread_id) REFERENCES public.threads(id);


--
-- Name: thread_categories thread_categories_fk1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.thread_categories
    ADD CONSTRAINT thread_categories_fk1 FOREIGN KEY (category_id) REFERENCES public.categories_list(category_id);


--
-- Name: users users_registration_section_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_registration_section_fkey FOREIGN KEY (registration_section) REFERENCES public.sections_registration(sections_registration_id);


--
-- Name: users users_rotating_section_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_rotating_section_fkey FOREIGN KEY (rotating_section) REFERENCES public.sections_rotating(sections_rotating_id);


--
-- Name: viewed_responses viewed_responses_fk0; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.viewed_responses
    ADD CONSTRAINT viewed_responses_fk0 FOREIGN KEY (thread_id) REFERENCES public.threads(id);


--
-- Name: viewed_responses viewed_responses_fk1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.viewed_responses
    ADD CONSTRAINT viewed_responses_fk1 FOREIGN KEY (user_id) REFERENCES public.users(user_id);


--
-- PostgreSQL database dump complete
--

