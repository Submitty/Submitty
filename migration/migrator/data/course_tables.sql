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

CREATE FUNCTION get_allowed_late_days(character varying, timestamp with time zone) RETURNS integer AS $$
SELECT allowed_late_days FROM late_days WHERE user_id = $1 AND since_timestamp <= $2 ORDER BY since_timestamp DESC LIMIT 1;
$$ LANGUAGE SQL;

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
    eg_vcs_host_type integer DEFAULT(0) NOT NULL,
    eg_team_assignment boolean NOT NULL,
    --eg_inherit_teams_from character varying(255) NOT NULL,
    eg_max_team_size integer NOT NULL,
    eg_team_lock_date timestamp(6) with time zone NOT NULL,
    eg_use_ta_grading boolean NOT NULL,
    eg_scanned_exam boolean DEFAULT (FALSE) NOT NULL,
    eg_student_view boolean NOT NULL,
    eg_student_view_after_grades boolean DEFAULT (FALSE) NOT NULL,
    eg_student_submit boolean NOT NULL,
    eg_peer_grading boolean NOT NULL,
    eg_submission_open_date timestamp(6) with time zone NOT NULL,
    eg_submission_due_date timestamp(6) with time zone NOT NULL,
    eg_has_due_date boolean DEFAULT TRUE NOT NULL,
    eg_late_days integer DEFAULT (-1) NOT NULL,
    eg_allow_late_submission boolean DEFAULT true NOT NULL,
    eg_peer_grade_set integer DEFAULT (0) NOT NULL,
    eg_precision numeric NOT NULL,
    eg_regrade_allowed boolean DEFAULT true NOT NULL,
    eg_grade_inquiry_per_component_allowed boolean DEFAULT false NOT NULL,
    eg_regrade_request_date timestamp(6) with time zone NOT NULL,
    eg_thread_ids json DEFAULT '{}' NOT NULL,
    eg_has_discussion boolean DEFAULT FALSE NOT NULL,
    CONSTRAINT eg_submission_date CHECK ((eg_submission_open_date <= eg_submission_due_date)),
    CONSTRAINT eg_team_lock_date_max CHECK ((eg_team_lock_date <= '9999-03-01 00:00:00.000000')),
    CONSTRAINT eg_submission_due_date_max CHECK ((eg_submission_due_date <= '9999-03-01 00:00:00.000000')),
    CONSTRAINT eg_regrade_request_date_max CHECK ((eg_regrade_request_date <= '9999-03-01 00:00:00.000000')),
    CONSTRAINT eg_regrade_allowed_true CHECK (eg_regrade_allowed is true or eg_grade_inquiry_per_component_allowed is false)
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
    g_instructions_url character varying NOT NULL,
    g_overall_ta_instructions character varying NOT NULL,
    g_gradeable_type integer NOT NULL,
    g_grader_assignment_method integer NOT NULL,
    g_ta_view_start_date timestamp(6) with time zone NOT NULL,
    g_grade_start_date timestamp(6) with time zone NOT NULL,
    g_grade_due_date timestamp(6) with time zone NOT NULL,
    g_grade_released_date timestamp(6) with time zone NOT NULL,
    g_grade_locked_date timestamp(6) with time zone,
    g_min_grading_group integer NOT NULL,
    g_syllabus_bucket character varying(255) NOT NULL,
    CONSTRAINT g_ta_view_start_date CHECK ((g_ta_view_start_date <= g_grade_start_date)),
    CONSTRAINT g_grade_start_date CHECK ((g_grade_start_date <= g_grade_due_date)),
    CONSTRAINT g_grade_due_date CHECK ((g_grade_due_date <= g_grade_released_date)),
    CONSTRAINT g_grade_released_date CHECK ((g_grade_released_date <= g_grade_locked_date)),
    CONSTRAINT g_grade_locked_date_max CHECK ((g_grade_locked_date <= '9999-03-01 00:00:00.000000'))
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
    gcd_grade_time timestamp(6) with time zone NOT NULL,
    gcd_verifier_id character varying(255),
    gcd_verify_time TIMESTAMP
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
-- Name: gradeable_comments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE gradeable_data_overall_comment (
    goc_id integer NOT NULL,
    g_id character varying(255) NOT NULL,
    goc_user_id character varying(255),
    goc_team_id character varying(255),
    goc_grader_id character varying(255) NOT NULL,
    goc_overall_comment character varying NOT NULL,
    CONSTRAINT goc_user_team_id_check CHECK (goc_user_id IS NOT NULL OR goc_team_id IS NOT NULL)
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
-- Name: gradeable_data_overall_comment_goc_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE gradeable_data_overall_comment_goc_id_seq
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
-- Name: gradeable_data_overall_comment_goc_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE gradeable_data_overall_comment_goc_id_seq OWNED BY gradeable_data_overall_comment.goc_id;

--
-- Name: grading_registration; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE grading_registration (
    sections_registration_id character varying(255) NOT NULL,
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
-- Name: seeking_team; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE seeking_team (
    g_id character varying(255) NOT NULL,
    user_id character varying NOT NULL
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
    since_timestamp timestamp(6) with time zone NOT NULL
);


CREATE TABLE migrations_course (
  id VARCHAR(100) PRIMARY KEY NOT NULL,
  commit_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status NUMERIC(1) DEFAULT 0 NOT NULL
);


--
-- Name: sections_registration; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE sections_registration (
    sections_registration_id character varying(255) NOT NULL
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
    session_expires timestamp(6) with time zone NOT NULL
);


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE users (
    user_id character varying NOT NULL,
    anon_id character varying,
    user_numeric_id character varying,
    user_firstname character varying NOT NULL,
    user_preferred_firstname character varying,
    user_lastname character varying NOT NULL,
    user_preferred_lastname character varying,
    user_email character varying NOT NULL,
    user_group integer NOT NULL,
    registration_section character varying(255),
    rotating_section integer,
    user_updated boolean NOT NULL DEFAULT false,
    instructor_updated boolean NOT NULL DEFAULT false,
    manual_registration boolean DEFAULT false,
    last_updated timestamp(6) with time zone,
    time_zone VARCHAR NOT NULL DEFAULT 'NOT_SET/NOT_SET',
    display_image_state VARCHAR NOT NULL DEFAULT 'system',
    CONSTRAINT users_user_group_check CHECK ((user_group >= 1) AND (user_group <= 4))
);

CREATE INDEX users_user_numeric_id_idx ON users using btree (
    user_numeric_id ASC NULLS LAST
);


--
-- Name: gradeable_teams; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE gradeable_teams (
    team_id character varying(255) NOT NULL,
    g_id character varying(255) NOT NULL,
    registration_section character varying(255),
    rotating_section integer
);


--
-- Name: teams; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE teams (
    team_id character varying(255) NOT NULL,
    user_id character varying(255) NOT NULL,
    state integer NOT NULL,
	last_viewed_time timestamp(6) with time zone DEFAULT NULL
);


--
-- Name: regrade_requests; Type: TABLE; Schema: public; Owner: -
--
CREATE TABLE regrade_requests (
    id serial NOT NULL PRIMARY KEY,
    g_id VARCHAR(255) NOT NULL,
    timestamp TIMESTAMP WITH TIME ZONE NOT NULL,
    user_id VARCHAR(255),
    team_id VARCHAR(255),
    status INTEGER DEFAULT 0 NOT NULL,
    gc_id INTEGER
);


CREATE TABLE notification_settings (
	user_id character varying NOT NULL,
	merge_threads BOOLEAN DEFAULT FALSE NOT NULL,
	all_new_threads BOOLEAN DEFAULT FALSE NOT NULL,
	all_new_posts BOOLEAN DEFAULT FALSE NOT NULL,
	all_modifications_forum BOOLEAN DEFAULT FALSE NOT NULL,
	reply_in_post_thread BOOLEAN DEFAULT FALSE NOT NULL,
	team_invite BOOLEAN DEFAULT TRUE NOT NULL,
	team_joined BOOLEAN DEFAULT TRUE NOT NULL,
	team_member_submission BOOLEAN DEFAULT TRUE NOT NULL,
    self_notification BOOLEAN DEFAULT FALSE NOT NULL,
	merge_threads_email BOOLEAN DEFAULT FALSE NOT NULL,
	all_new_threads_email BOOLEAN DEFAULT FALSE NOT NULL,
	all_new_posts_email BOOLEAN DEFAULT FALSE NOT NULL,
	all_modifications_forum_email BOOLEAN DEFAULT FALSE NOT NULL,
	reply_in_post_thread_email BOOLEAN DEFAULT FALSE NOT NULL,
	team_invite_email BOOLEAN DEFAULT TRUE NOT NULL,
	team_joined_email BOOLEAN DEFAULT TRUE NOT NULL,
	team_member_submission_email BOOLEAN DEFAULT TRUE NOT NULL,
	self_notification_email BOOLEAN DEFAULT FALSE NOT NULL

);

--
-- Name: regrade_discussion; Type: TABLE; Schema: public; Owner: -
--
CREATE TABLE regrade_discussion (
    id serial NOT NULL PRIMARY KEY,
    regrade_id INTEGER NOT NULL,
    timestamp TIMESTAMP WITH TIME ZONE NOT NULL,
    user_id VARCHAR(255) NOT NULL,
    content TEXT,
    deleted BOOLEAN DEFAULT FALSE NOT NULL,
    gc_id integer
);

--
-- Name: grade_override; Type: TABLE; Schema:
--
CREATE TABLE grade_override (
    user_id character varying(255) NOT NULL,
    g_id character varying(255) NOT NULL,
    marks float NOT NULL,
    comment character varying
);

--
-- Name: notifications_component_enum; Type: ENUM; Schema: public; Owner: -
--
CREATE TYPE notifications_component AS ENUM ('forum', 'student', 'grading', 'team');

--
-- Name: notifications; Type: TABLE; Schema: public; Owner: -
--
CREATE TABLE notifications (
    id serial NOT NULL PRIMARY KEY,
    component notifications_component NOT NULL,
    metadata TEXT NOT NULL,
    content TEXT NOT NULL,
    from_user_id VARCHAR(255),
    to_user_id VARCHAR(255) NOT NULL,
    created_at timestamp with time zone NOT NULL,
    seen_at timestamp with time zone
);


-- Begins Forum

--
-- Name: posts; Type: Table; Schema: public; Owner: -
--
CREATE TABLE "posts" (
        "id" serial NOT NULL,
        "thread_id" int NOT NULL,
        "parent_id" int DEFAULT '-1',
        "author_user_id" character varying NOT NULL,
        "content" TEXT NOT NULL,
        "timestamp" timestamp with time zone NOT NULL,
        "anonymous" BOOLEAN NOT NULL,
        "deleted" BOOLEAN NOT NULL DEFAULT 'false',
        "endorsed_by" varchar,
        "type" int NOT NULL,
        "has_attachment" BOOLEAN NOT NULL,
        "render_markdown" BOOLEAN NOT NULL DEFAULT 'false',
        CONSTRAINT posts_pk PRIMARY KEY ("id")
);

CREATE TABLE "threads" (
	"id" serial NOT NULL,
	"title" varchar NOT NULL,
	"created_by" varchar NOT NULL,
	"pinned" BOOLEAN NOT NULL DEFAULT 'false',
	"deleted" BOOLEAN NOT NULL DEFAULT 'false',
	"merged_thread_id" int DEFAULT '-1',
	"merged_post_id" int DEFAULT '-1',
	"is_visible" BOOLEAN NOT NULL,
	"status" int DEFAULT 0 NOT NULL,
	"lock_thread_date" timestamp with time zone,
	CONSTRAINT threads_pk PRIMARY KEY ("id")
);

CREATE TABLE forum_posts_history (
    "post_id" int NOT NULL,
    "edit_author" character varying NOT NULL,
    "content" text NOT NULL,
    "edit_timestamp" timestamp with time zone NOT NULL
);

CREATE TABLE "thread_categories" (
	"thread_id" int NOT NULL,
	"category_id" int NOT NULL
);

CREATE TABLE "categories_list" (
	"category_id" serial NOT NULL,
	"category_desc" varchar NOT NULL,
	"rank" int,
	"color" varchar DEFAULT '#000080' NOT NULL,
	CONSTRAINT categories_list_pk PRIMARY KEY ("category_id")
);

CREATE TABLE "student_favorites" (
	"id" serial NOT NULL,
	"user_id" character varying NOT NULL,
	"thread_id" int,
	CONSTRAINT student_favorites_pk PRIMARY KEY ("id")
);

CREATE TABLE "viewed_responses" (
	"thread_id" int NOT NULL,
	"user_id" character varying NOT NULL,
	"timestamp" timestamp with time zone NOT NULL,
    CONSTRAINT viewed_responses_pkey PRIMARY KEY ("thread_id", "user_id")
);


-- Ends Forum

--
-- Name: gc_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY gradeable_component ALTER COLUMN gc_id SET DEFAULT nextval('gradeable_component_gc_id_seq'::regclass);


--
-- Name: gd_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY gradeable_data ALTER COLUMN gd_id SET DEFAULT nextval('gradeable_data_gd_id_seq'::regclass);

--
-- Name: goc_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY gradeable_data_overall_comment ALTER COLUMN goc_id SET DEFAULT nextval('gradeable_data_overall_comment_goc_id_seq'::regclass);

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
-- Name: gradeable_data_overall_comment_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY gradeable_data_overall_comment
    ADD CONSTRAINT gradeable_data_overall_comment_pkey PRIMARY KEY (goc_id);


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
-- Name: gradeable_data_overall_comment_user_unique; Type: CONSTRAINT; Schema: public; Owner: -
--
ALTER TABLE ONLY gradeable_data_overall_comment ADD CONSTRAINT gradeable_data_overall_comment_user_unique UNIQUE (g_id, goc_user_id, goc_grader_id);

--
-- Name: gradeable_data_overall_comment_team_unique; Type: CONSTRAINT; Schema: public; Owner: -
--
ALTER TABLE ONLY gradeable_data_overall_comment ADD CONSTRAINT gradeable_data_overall_comment_team_unique UNIQUE (g_id, goc_team_id, goc_grader_id);

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
-- Name: seeking_team; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE seeking_team
    ADD CONSTRAINT seeking_team_pkey PRIMARY KEY (g_id, user_id);


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

ALTER TABLE ONLY notification_settings
    ADD CONSTRAINT notification_settings_pkey PRIMARY KEY (user_id);

ALTER TABLE ONLY notification_settings
    ADD CONSTRAINT notification_settings_fkey FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE;

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
-- Name: grade_override_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grade_override
    ADD CONSTRAINT grade_override_pkey PRIMARY KEY (user_id, g_id);

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
-- Name: gradeable_component_data_verifier_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY gradeable_component_data
  ADD CONSTRAINT gradeable_component_data_verifier_id_fkey FOREIGN KEY (gcd_verifier_id) REFERENCES users(user_id);
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
-- Name: gradeable_data_overall_comment_g_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY gradeable_data_overall_comment
    ADD CONSTRAINT gradeable_data_overall_comment_g_id_fkey FOREIGN KEY (g_id) REFERENCES gradeable(g_id) ON DELETE CASCADE;

--
-- Name: gradeable_data_overall_comment_goc_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY gradeable_data_overall_comment
    ADD CONSTRAINT gradeable_data_overall_comment_goc_user_id_fkey FOREIGN KEY (goc_user_id) REFERENCES users(user_id) ON DELETE CASCADE;

--
-- Name: gradeable_data_overall_comment_goc_team_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY gradeable_data_overall_comment
    ADD CONSTRAINT gradeable_data_overall_comment_goc_team_id_fkey FOREIGN KEY (goc_team_id) REFERENCES gradeable_teams(team_id) ON DELETE CASCADE;


--
-- Name: gradeable_data_overall_comment_goc_grader_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY gradeable_data_overall_comment
    ADD CONSTRAINT gradeable_data_overall_comment_goc_grader_id FOREIGN KEY (goc_grader_id) REFERENCES users(user_id) ON DELETE CASCADE;

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
-- Name: seeking_team; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY seeking_team
    ADD CONSTRAINT seeking_team_g_id_fkey FOREIGN KEY (g_id) REFERENCES gradeable(g_id) ON UPDATE CASCADE ON DELETE CASCADE;


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
    ADD CONSTRAINT peer_assign_g_id_fkey FOREIGN KEY (g_id) REFERENCES gradeable(g_id) ON UPDATE CASCADE ON DELETE CASCADE;


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


ALTER TABLE ONLY gradeable_teams
    ADD CONSTRAINT gradeable_teams_registration_section_fkey FOREIGN KEY (registration_section) REFERENCES sections_registration(sections_registration_id);



--
-- Name: users_rotating_section_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_rotating_section_fkey FOREIGN KEY (rotating_section) REFERENCES sections_rotating(sections_rotating_id);

ALTER TABLE ONLY gradeable_teams
    ADD CONSTRAINT gradeable_teams_rotating_section_fkey FOREIGN KEY (rotating_section) REFERENCES sections_rotating(sections_rotating_id);

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
-- Name: grade_override_g_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grade_override
    ADD CONSTRAINT grade_override_g_id_fkey FOREIGN KEY (g_id) REFERENCES gradeable(g_id) ON DELETE CASCADE;


--
-- Name: grade_override_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grade_override
    ADD CONSTRAINT grade_override_user_id_fkey FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE;


--
-- Name: regrade_discussion; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY regrade_discussion
    ADD CONSTRAINT regrade_discussion_regrade_requests_id_fk FOREIGN KEY (regrade_id) REFERENCES regrade_requests(id) ON UPDATE CASCADE;

--
-- Name: notifications_to_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY notifications
    ADD CONSTRAINT notifications_to_user_id_fkey FOREIGN KEY (to_user_id) REFERENCES users(user_id) ON UPDATE CASCADE;

--
-- Name: notifications_from_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY notifications
    ADD CONSTRAINT notifications_from_user_id_fkey FOREIGN KEY (from_user_id) REFERENCES users(user_id) ON UPDATE CASCADE;

-- Forum Key relationships

ALTER TABLE "posts" ADD CONSTRAINT "posts_fk0" FOREIGN KEY ("thread_id") REFERENCES "threads"("id");
ALTER TABLE "posts" ADD CONSTRAINT "posts_fk1" FOREIGN KEY ("author_user_id") REFERENCES "users"("user_id");

ALTER TABLE "threads" ADD CONSTRAINT "threads_status_check" CHECK ("status" IN (-1,0,1));

ALTER TABLE "forum_posts_history" ADD CONSTRAINT "forum_posts_history_post_id_fk" FOREIGN KEY ("post_id") REFERENCES "posts"("id");
ALTER TABLE "forum_posts_history" ADD CONSTRAINT "forum_posts_history_edit_author_fk" FOREIGN KEY ("edit_author") REFERENCES "users"("user_id");
CREATE INDEX "forum_posts_history_post_id_index" ON "forum_posts_history" ("post_id");
CREATE INDEX "forum_posts_history_edit_timestamp_index" ON "forum_posts_history" ("edit_timestamp" DESC);

ALTER TABLE "thread_categories" ADD CONSTRAINT "thread_categories_fk0" FOREIGN KEY ("thread_id") REFERENCES "threads"("id");
ALTER TABLE "thread_categories" ADD CONSTRAINT "thread_categories_fk1" FOREIGN KEY ("category_id") REFERENCES "categories_list"("category_id");


ALTER TABLE "student_favorites" ADD CONSTRAINT "student_favorites_fk0" FOREIGN KEY ("user_id") REFERENCES "users"("user_id");
ALTER TABLE "student_favorites" ADD CONSTRAINT "student_favorites_fk1" FOREIGN KEY ("thread_id") REFERENCES "threads"("id");

ALTER TABLE "viewed_responses" ADD CONSTRAINT "viewed_responses_fk0" FOREIGN KEY ("thread_id") REFERENCES "threads"("id");
ALTER TABLE "viewed_responses" ADD CONSTRAINT "viewed_responses_fk1" FOREIGN KEY ("user_id") REFERENCES "users"("user_id");

ALTER TABLE "regrade_requests" ADD CONSTRAINT "regrade_requests_fk0" FOREIGN KEY ("g_id") REFERENCES "gradeable"("g_id");
ALTER TABLE "regrade_requests" ADD CONSTRAINT "regrade_requests_fk1" FOREIGN KEY ("user_id") REFERENCES "users"("user_id");
ALTER TABLE "regrade_requests" ADD CONSTRAINT "regrade_requests_fk2" FOREIGN KEY ("team_id") REFERENCES "gradeable_teams"("team_id");
ALTER TABLE "regrade_requests" ADD CONSTRAINT "regrade_requests_fk3" FOREIGN KEY ("gc_id") REFERENCES "gradeable_component"("gc_id");

ALTER TABLE "regrade_discussion" ADD CONSTRAINT "regrade_discussion_fk0" FOREIGN KEY ("regrade_id") REFERENCES "regrade_requests"("id");
ALTER TABLE "regrade_discussion" ADD CONSTRAINT "regrade_discussion_fk1" FOREIGN KEY ("user_id") REFERENCES "users"("user_id");

ALTER TABLE ONLY categories_list
    ADD CONSTRAINT category_unique UNIQUE (category_desc);

ALTER TABLE ONLY thread_categories
    ADD CONSTRAINT thread_and_category_unique UNIQUE (thread_id, category_id);

ALTER TABLE ONLY student_favorites
    ADD CONSTRAINT user_and_thread_unique UNIQUE (user_id, thread_id);

CREATE UNIQUE INDEX gradeable_user_unique ON regrade_requests(user_id, g_id) WHERE gc_id IS NULL;
CREATE UNIQUE INDEX gradeable_team_unique ON regrade_requests(team_id, g_id) WHERE gc_id IS NULL;

ALTER TABLE ONLY regrade_requests ADD CONSTRAINT gradeable_user_gc_id UNIQUE (user_id, g_id, gc_id);
ALTER TABLE ONLY regrade_requests ADD CONSTRAINT gradeable_team_gc_id UNIQUE (team_id, g_id, gc_id);

-- End Forum Key relationships

-- office hours queue

CREATE TABLE IF NOT EXISTS queue(
  entry_id SERIAL PRIMARY KEY,
  current_state TEXT NOT NULL,
  removal_type TEXT,
  queue_code TEXT NOT NULL,
  user_id TEXT NOT NULL REFERENCES users(user_id),
  name TEXT NOT NULL,
  time_in TIMESTAMP NOT NULL,
  time_help_start TIMESTAMP,
  time_out TIMESTAMP,
  added_by TEXT NOT NULL REFERENCES users(user_id),
  help_started_by TEXT REFERENCES users(user_id),
  removed_by TEXT REFERENCES users(user_id),
  contact_info TEXT,
  last_time_in_queue TIMESTAMP WITH TIME ZONE
);
CREATE TABLE IF NOT EXISTS queue_settings(
  id serial PRIMARY KEY,
  open boolean NOT NULL,
  code text NOT NULL,
  token text NOT NULL
);

-- end office hours queue

-- begin online polling
CREATE TABLE IF NOT EXISTS polls(
    poll_id SERIAL PRIMARY KEY,
    name TEXT NOT NULL,
    question TEXT NOT NULL,
    status TEXT NOT NULL,
    release_date DATE NOT NULL
);
CREATE TABLE IF NOT EXISTS poll_options(
    option_id integer NOT NULL,
    order_id integer NOT NULL,
    poll_id integer REFERENCES polls(poll_id),
    response TEXT NOT NULL,
    correct bool NOT NULL
);
CREATE TABLE IF NOT EXISTS poll_responses(
    poll_id integer NOT NULL REFERENCES polls(poll_id),
    student_id TEXT NOT NULL REFERENCES users(user_id),
    option_id integer NOT NULL
);

-- end online polling


--
-- PostgreSQL database dump complete
--
