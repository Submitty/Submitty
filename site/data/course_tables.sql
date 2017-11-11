--
-- PostgreSQL database dump
--

-- Dumped from database version 9.3.15
-- Dumped by pg_dump version 9.5.1

SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;

--
-- Name: plpgsql; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;


--
-- Name: EXTENSION plpgsql; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';


SET search_path = public, pg_catalog;

--
-- Name: check_valid_score(numeric, integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION check_valid_score(numeric, integer) RETURNS boolean
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

CREATE FUNCTION csv_to_numeric_gradeable(vcode text[], gradeable_id text, grader_id text) RETURNS boolean
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

        INSERT INTO gradeable_data(g_id, gd_user_id, gd_overall_comment) VALUES (gradeable_id, line[1], '', 1);

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


SET default_with_oids = false;

--
-- Name: electronic_gradeable; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE electronic_gradeable (
    g_id character varying(255) NOT NULL,
    eg_config_path character varying(1024) NOT NULL,
    eg_is_repository boolean NOT NULL,
    eg_subdirectory character varying(1024) NOT NULL,
    eg_team_assignment boolean NOT NULL,
    eg_max_team_size integer NOT NULL,
    eg_team_lock_date timestamp(6) with time zone NOT NULL,
    eg_use_ta_grading boolean NOT NULL,
    eg_student_view boolean NOT NULL,
    eg_student_submit boolean NOT NULL,
    eg_student_download boolean NOT NULL,
    eg_student_any_version boolean NOT NULL,
    eg_peer_grading boolean NOT NULL,
    eg_submission_open_date timestamp(6) with time zone NOT NULL,
    eg_submission_due_date timestamp(6) with time zone NOT NULL,
    eg_late_days integer DEFAULT (-1) NOT NULL,
    eg_allow_late_submission boolean DEFAULT true NOT NULL,
    eg_peer_grade_set integer DEFAULT (0) NOT NULL,
    eg_precision numeric NOT NULL,
    CONSTRAINT eg_submission_date CHECK ((eg_submission_open_date <= eg_submission_due_date))
);


--
-- Name: electronic_gradeable_data; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE electronic_gradeable_data (
    g_id character varying(255) NOT NULL,
    user_id character varying(255),
    team_id character varying(255),
    g_version integer NOT NULL,
    autograding_non_hidden_non_extra_credit numeric DEFAULT 0 NOT NULL,
    autograding_non_hidden_extra_credit numeric DEFAULT 0 NOT NULL,
    autograding_hidden_non_extra_credit numeric DEFAULT 0 NOT NULL,
    autograding_hidden_extra_credit numeric DEFAULT 0 NOT NULL,
    submission_time timestamp(6) with time zone NOT NULL,
    autograding_complete boolean DEFAULT FALSE NOT NULL,
    CONSTRAINT egd_user_team_id_check CHECK (user_id IS NOT NULL OR team_id IS NOT NULL),
    CONSTRAINT egd_g_user_team_id_unique UNIQUE (g_id, user_id, team_id, g_version)
);


--
-- Name: electronic_gradeable_version; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE electronic_gradeable_version (
    g_id character varying(255) NOT NULL,
    user_id character varying(255),
    team_id character varying(255),
    active_version integer,
    CONSTRAINT egv_user_team_id_check CHECK (user_id IS NOT NULL OR team_id IS NOT NULL),
    CONSTRAINT egv_g_user_team_id_unique UNIQUE (g_id, user_id, team_id)
);


--
-- Name: gradeable; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE gradeable (
    g_id character varying(255) NOT NULL,
    g_title character varying(255) NOT NULL,
    g_instructions_url character varying(255) NOT NULL,
    g_overall_ta_instructions character varying NOT NULL,
    g_gradeable_type integer NOT NULL,
    g_grade_by_registration boolean NOT NULL,
    g_ta_view_start_date timestamp(6) with time zone NOT NULL,
    g_grade_start_date timestamp(6) with time zone NOT NULL,
    g_grade_released_date timestamp(6) with time zone NOT NULL,
    g_grade_locked_date timestamp(6) with time zone,
    g_min_grading_group integer NOT NULL,
    g_syllabus_bucket character varying(255) NOT NULL,
    CONSTRAINT g_ta_view_start_date CHECK ((g_ta_view_start_date <= g_grade_start_date)),
    CONSTRAINT g_grade_start_date CHECK ((g_grade_start_date <= g_grade_released_date)),
    CONSTRAINT g_grade_released_date CHECK ((g_grade_released_date <= g_grade_locked_date))
);



-- 
-- Name: gradeable_component_mark; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE gradeable_component_mark (
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

CREATE TABLE gradeable_component_mark_data (
    gc_id integer NOT NULL,
    gd_id integer NOT NULL,
    gcd_grader_id character varying(255) NOT NULL,
    gcm_id integer NOT NULL
);

--
-- Name: gradeable_component; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE gradeable_component (
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
    gc_page integer NOT NULL
);


--
-- Name: gradeable_component_data; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE gradeable_component_data (
    gc_id integer NOT NULL,
    gd_id integer NOT NULL,
    gcd_score numeric NOT NULL,
    gcd_component_comment character varying NOT NULL,
    gcd_grader_id character varying(255) NOT NULL,
    gcd_graded_version integer,
    gcd_grade_time timestamp(6) with time zone NOT NULL
    -- CONSTRAINT gradeable_component_data_check CHECK (check_valid_score(gcd_score, gc_id)) -
);


--
-- Name: gradeable_component_gc_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE gradeable_component_gc_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: gradeable_component_gc_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE gradeable_component_gc_id_seq OWNED BY gradeable_component.gc_id;


--
-- Name: gradeable_data; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE gradeable_data (
    gd_id integer NOT NULL,
    g_id character varying(255) NOT NULL,
    gd_user_id character varying(255),
    gd_team_id character varying(255),
    gd_overall_comment character varying NOT NULL,
    gd_user_viewed_date timestamp(6) with time zone DEFAULT NULL
);


--
-- Name: gradeable_data_gd_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE gradeable_data_gd_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

--
-- Name: gradeable_component_mark_gcm_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE gradeable_component_mark_gcm_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

--
-- Name: gradeable_component_mark_gcm_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE gradeable_component_mark_gcm_id_seq OWNED BY gradeable_component_mark.gcm_id;

--
-- Name: gradeable_data_gd_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE gradeable_data_gd_id_seq OWNED BY gradeable_data.gd_id;


--
-- Name: grading_registration; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE grading_registration (
    sections_registration_id integer NOT NULL,
    user_id character varying NOT NULL
);


--
-- Name: grading_rotating; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE grading_rotating (
    sections_rotating_id integer NOT NULL,
    user_id character varying NOT NULL,
    g_id character varying NOT NULL
);

--
-- Name: peer_assign; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE peer_assign (
    g_id character varying NOT NULL,
    grader_id character varying NOT NULL,
    user_id character varying NOT NULL
);


--
-- Name: late_day_exceptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE late_day_exceptions (
    user_id character varying(255) NOT NULL,
    g_id character varying(255) NOT NULL,
    late_day_exceptions integer NOT NULL
);


--
-- Name: late_days; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE late_days (
    user_id character varying(255) NOT NULL,
    allowed_late_days integer NOT NULL,
    since_timestamp timestamp with time zone NOT NULL
);


--
-- Name: sections_registration; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE sections_registration (
    sections_registration_id integer NOT NULL
);


--
-- Name: sections_rotating; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE sections_rotating (
    sections_rotating_id integer NOT NULL
);


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE sessions (
    session_id character varying(255) NOT NULL,
    user_id character varying(255) NOT NULL,
    csrf_token character varying(255) NOT NULL,
    session_expires timestamp with time zone NOT NULL
);


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE users (
    user_id character varying NOT NULL,
    anon_id character varying,
    user_firstname character varying NOT NULL,
    user_preferred_firstname character varying,
    user_lastname character varying NOT NULL,
    user_email character varying NOT NULL,
    user_group integer NOT NULL,
    registration_section integer,
    rotating_section integer,
    manual_registration boolean DEFAULT false,
    last_updated TIMESTAMP WITH time zone,
    CONSTRAINT users_user_group_check CHECK (((user_group >= 0) AND (user_group <= 4)))
);


--
-- Name: gradeable_teams; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE gradeable_teams (
    team_id character varying(255) NOT NULL,
    g_id character varying(255) NOT NULL,
    registration_section integer,
    rotating_section integer
);


--
-- Name: teams; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE teams (
    team_id character varying(255) NOT NULL,
    user_id character varying(255) NOT NULL,
    state integer NOT NULL
);


--
-- Name: gc_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY gradeable_component ALTER COLUMN gc_id SET DEFAULT nextval('gradeable_component_gc_id_seq'::regclass);


--
-- Name: gd_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY gradeable_data ALTER COLUMN gd_id SET DEFAULT nextval('gradeable_data_gd_id_seq'::regclass);

--
-- Name: gcm_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY gradeable_component_mark ALTER COLUMN gcm_id SET DEFAULT nextval('gradeable_component_mark_gcm_id_seq'::regclass);

--
-- Name: electronic_gradeable_g_id_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY electronic_gradeable
    ADD CONSTRAINT electronic_gradeable_g_id_pkey PRIMARY KEY (g_id);


--
-- Name: gradeable_component_data_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY gradeable_component_data
    ADD CONSTRAINT gradeable_component_data_pkey PRIMARY KEY (gc_id, gd_id, gcd_grader_id);
    

--
-- Name: gradeable_component_data_normal_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX gradeable_component_data_no_grader_index
ON gradeable_component_data (gc_id, gd_id);


--
-- Name: gradeable_component_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY gradeable_component
    ADD CONSTRAINT gradeable_component_pkey PRIMARY KEY (gc_id);

--
-- Name: gradeable_component_mark_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY gradeable_component_mark
    ADD CONSTRAINT gradeable_component_mark_pkey PRIMARY KEY (gcm_id);

--
-- Name: gradeable_component_mark_data_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY gradeable_component_mark_data
    ADD CONSTRAINT gradeable_component_mark_data_pkey PRIMARY KEY (gcm_id, gc_id, gd_id, gcd_grader_id);

--
-- Name: gradeable_data_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY gradeable_data
    ADD CONSTRAINT gradeable_data_pkey PRIMARY KEY (gd_id);


--
-- Name: gradeable_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY gradeable
    ADD CONSTRAINT gradeable_pkey PRIMARY KEY (g_id);


--
-- Name: gradeable_unqiue; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY gradeable_data
    ADD CONSTRAINT gradeable_unqiue UNIQUE (g_id, gd_user_id);


--
-- Name: grading_registration_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grading_registration
    ADD CONSTRAINT grading_registration_pkey PRIMARY KEY (sections_registration_id, user_id);


--
-- Name: grading_rotating_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grading_rotating
    ADD CONSTRAINT grading_rotating_pkey PRIMARY KEY (sections_rotating_id, user_id, g_id);
    
    
--
-- Name: peer_assign_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--
    
ALTER TABLE ONLY peer_assign
    ADD CONSTRAINT peer_assign_pkey PRIMARY KEY (g_id, grader_id, user_id);


--
-- Name: late_day_exceptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY late_day_exceptions
    ADD CONSTRAINT late_day_exceptions_pkey PRIMARY KEY (g_id, user_id);


--
-- Name: late_days_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY late_days
    ADD CONSTRAINT late_days_pkey PRIMARY KEY (user_id, since_timestamp);


--
-- Name: sections_registration_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY sections_registration
    ADD CONSTRAINT sections_registration_pkey PRIMARY KEY (sections_registration_id);


--
-- Name: sections_rotating_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY sections_rotating
    ADD CONSTRAINT sections_rotating_pkey PRIMARY KEY (sections_rotating_id);


--
-- Name: sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (session_id);


--
-- Name: users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_pkey PRIMARY KEY (user_id);


--
-- Name: gradeable_teams_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY gradeable_teams
    ADD CONSTRAINT gradeable_teams_pkey PRIMARY KEY (team_id);


--
-- Name: teams_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY teams
    ADD CONSTRAINT teams_pkey PRIMARY KEY (team_id, user_id);


--
-- Name: electronic_gradeable_data_gid; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY electronic_gradeable_data
    ADD CONSTRAINT electronic_gradeable_data_gid FOREIGN KEY (g_id) REFERENCES gradeable(g_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: electronic_gradeable_data_user; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY electronic_gradeable_data
    ADD CONSTRAINT electronic_gradeable_data_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE;


--
-- Name: electronic_gradeable_data_team; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY electronic_gradeable_data
    ADD CONSTRAINT electronic_gradeable_data_team FOREIGN KEY (team_id) REFERENCES gradeable_teams(team_id) ON UPDATE CASCADE;


--
-- Name: electronic_gradeable_g_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY electronic_gradeable
    ADD CONSTRAINT electronic_gradeable_g_id_fkey FOREIGN KEY (g_id) REFERENCES gradeable(g_id) ON DELETE CASCADE;


--
-- Name: electronic_gradeable_gid; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY electronic_gradeable_version
    ADD CONSTRAINT electronic_gradeable_version_g_id FOREIGN KEY (g_id) REFERENCES gradeable(g_id) ON DELETE CASCADE;


--
-- Name: electronic_gradeable_user; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY electronic_gradeable_version
    ADD CONSTRAINT electronic_gradeable_version_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE;


--
-- Name: electronic_gradeable_team_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY electronic_gradeable_version
    ADD CONSTRAINT electronic_gradeable_version_team FOREIGN KEY (team_id) REFERENCES gradeable_teams(team_id) ON UPDATE CASCADE;


--
-- Name: electronic_gradeable_version; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY electronic_gradeable_version
    ADD CONSTRAINT electronic_gradeable_version FOREIGN KEY (g_id, user_id, team_id, active_version) REFERENCES electronic_gradeable_data(g_id, user_id, team_id, g_version) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: gradeable_component_data_gc_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY gradeable_component_data
    ADD CONSTRAINT gradeable_component_data_gc_id_fkey FOREIGN KEY (gc_id) REFERENCES gradeable_component(gc_id) ON DELETE CASCADE;


--
-- Name: gradeable_component_data_gd_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY gradeable_component_data
    ADD CONSTRAINT gradeable_component_data_gd_id_fkey FOREIGN KEY (gd_id) REFERENCES gradeable_data(gd_id) ON DELETE CASCADE;

--
-- Name: gradeable_component_data_gcd_grader_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY gradeable_component_data
  ADD CONSTRAINT gradeable_component_data_gcd_grader_id_fkey FOREIGN KEY (gcd_grader_id) REFERENCES users(user_id) ON UPDATE CASCADE;

--
-- Name: gradeable_component_g_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY gradeable_component
    ADD CONSTRAINT gradeable_component_g_id_fkey FOREIGN KEY (g_id) REFERENCES gradeable(g_id) ON DELETE CASCADE;

--
-- Name: gradeable_component_mark_gc_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY gradeable_component_mark
    ADD CONSTRAINT gradeable_component_mark_gc_id_fkey FOREIGN KEY (gc_id) REFERENCES gradeable_component(gc_id) ON DELETE CASCADE;

--
-- Name: gradeable_component_mark_data_gcm_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY gradeable_component_mark_data
    ADD CONSTRAINT gradeable_component_mark_data_gcm_id_fkey FOREIGN KEY (gcm_id) REFERENCES gradeable_component_mark(gcm_id) ON DELETE CASCADE;

--
-- Name: gradeable_component_mark_data_gd_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY gradeable_component_mark_data
    ADD CONSTRAINT gradeable_component_mark_data_gd_id_and_gc_id_fkey FOREIGN KEY (gd_id, gc_id, gcd_grader_id) REFERENCES gradeable_component_data(gd_id, gc_id, gcd_grader_id) ON UPDATE CASCADE ON DELETE CASCADE;

--
-- Name: gradeable_data_g_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY gradeable_data
    ADD CONSTRAINT gradeable_data_g_id_fkey FOREIGN KEY (g_id) REFERENCES gradeable(g_id) ON DELETE CASCADE;


--
-- Name: gradeable_data_gd_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY gradeable_data
    ADD CONSTRAINT gradeable_data_gd_user_id_fkey FOREIGN KEY (gd_user_id) REFERENCES users(user_id) ON UPDATE CASCADE;


--
-- Name: gradeable_data_team_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY gradeable_data
    ADD CONSTRAINT gradeable_data_gd_team_id_fkey FOREIGN KEY (gd_team_id) REFERENCES gradeable_teams(team_id) ON UPDATE CASCADE;


--
-- Name: grading_registration_sections_registration_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grading_registration
    ADD CONSTRAINT grading_registration_sections_registration_id_fkey FOREIGN KEY (sections_registration_id) REFERENCES sections_registration(sections_registration_id);


--
-- Name: grading_registration_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grading_registration
    ADD CONSTRAINT grading_registration_user_id_fkey FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE;


--
-- Name: grading_rotating_g_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grading_rotating
    ADD CONSTRAINT grading_rotating_g_id_fkey FOREIGN KEY (g_id) REFERENCES gradeable(g_id) ON DELETE CASCADE;


--
-- Name: grading_rotating_sections_rotating_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grading_rotating
    ADD CONSTRAINT grading_rotating_sections_rotating_fkey FOREIGN KEY (sections_rotating_id) REFERENCES sections_rotating(sections_rotating_id) ON DELETE CASCADE;


--
-- Name: grading_rotating_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grading_rotating
    ADD CONSTRAINT grading_rotating_user_id_fkey FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE;


--
-- Name: late_day_exceptions_g_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY late_day_exceptions
    ADD CONSTRAINT late_day_exceptions_g_id_fkey FOREIGN KEY (g_id) REFERENCES gradeable(g_id) ON DELETE CASCADE;


--
-- Name: late_day_exceptions_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY late_day_exceptions
    ADD CONSTRAINT late_day_exceptions_user_id_fkey FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE;


--
-- Name: late_days_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY late_days
    ADD CONSTRAINT late_days_user_id_fkey FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE;


--
-- Name: peer_assign_g_id_fkey; Type: FK CONSTRAINT; Schma: public; Owner: -
--

ALTER TABLE ONLY peer_assign 
    ADD CONSTRAINT peer_assign_g_id_fkey FOREIGN KEY (g_id) REFERENCES gradeable(g_id) ON UPDATE CASCADE;


--
-- Name: peer_assign_grader_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY peer_assign
    ADD CONSTRAINT peer_assign_grader_id_fkey FOREIGN KEY (grader_id) REFERENCES users(user_id) ON UPDATE CASCADE;
    

--
-- Name: peer_assign_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY peer_assign
    ADD CONSTRAINT peer_assign_user_id_fkey FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE;


--
-- Name: sessions_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY sessions
    ADD CONSTRAINT sessions_fkey FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE;


--
-- Name: users_registration_section_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_registration_section_fkey FOREIGN KEY (registration_section) REFERENCES sections_registration(sections_registration_id);


--
-- Name: users_rotating_section_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_rotating_section_fkey FOREIGN KEY (rotating_section) REFERENCES sections_rotating(sections_rotating_id);

--
-- Name: gradeable_teams_g_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY gradeable_teams
    ADD CONSTRAINT gradeable_teams_g_id_fkey FOREIGN KEY (g_id) REFERENCES gradeable(g_id) ON DELETE CASCADE;


--
-- Name: teams_team_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY teams
    ADD CONSTRAINT teams_team_id_fkey FOREIGN KEY (team_id) REFERENCES gradeable_teams(team_id) ON DELETE CASCADE;


--
-- Name: teams_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY teams
    ADD CONSTRAINT teams_user_id_fkey FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE;


--
-- PostgreSQL database dump complete
--

