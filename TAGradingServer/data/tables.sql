--
-- PostgreSQL database dump
--

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
   SELECT $1<=gc_max_value INTO valid_score FROM gradeable_component AS gc WHERE gc.gc_id=$2;
   RETURN valid_score;
END;
$_$;


SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: config; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE config (
    config_name character varying(255) NOT NULL,
    config_type integer NOT NULL,
    config_value character varying(255) NOT NULL
);


--
-- Name: electronic_gradeable; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE electronic_gradeable (
    g_id character varying(255) NOT NULL,
    eg_instructions_url character varying(255) NOT NULL,
    eg_submission_open_date timestamp(6) without time zone NOT NULL,
    eg_submission_due_date timestamp(6) without time zone NOT NULL,
    eg_is_repository boolean NOT NULL,
    eg_subdirectory character varying(1024) NOT NULL,
    eg_use_ta_grading boolean NOT NULL,
    eg_config_path character varying(1024) NOT NULL,
    eg_late_days integer DEFAULT (-1) NOT NULL,
    eg_precision numeric NOT NULL
);


--
-- Name: gradeable; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE gradeable (
    g_id character varying(255) NOT NULL,
    g_title character varying(255) NOT NULL,
    g_overall_ta_instructions character varying NOT NULL,
    g_team_assignment boolean NOT NULL,
    g_gradeable_type integer NOT NULL,
    g_grade_by_registration boolean NOT NULL,
    g_grade_start_date timestamp(6) without time zone NOT NULL,
    g_grade_released_date timestamp(6) without time zone NOT NULL,
    g_syllabus_bucket character varying(255) NOT NULL,
    g_min_grading_group integer NOT NULL
);


--
-- Name: gradeable_component; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE gradeable_component (
    gc_id integer NOT NULL,
    g_id character varying(255) NOT NULL,
    gc_title character varying(255) NOT NULL,
    gc_ta_comment character varying NOT NULL,
    gc_student_comment character varying NOT NULL,
    gc_max_value numeric NOT NULL,
    gc_is_text boolean NOT NULL,
    gc_is_extra_credit boolean NOT NULL,
    gc_order integer NOT NULL
);


--
-- Name: gradeable_component_data; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE gradeable_component_data (
    gc_id integer NOT NULL,
    gd_id integer NOT NULL,
    gcd_score numeric NOT NULL,
    gcd_component_comment character varying NOT NULL,
    CONSTRAINT gradeable_component_data_check CHECK (check_valid_score(gcd_score, gc_id))
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
-- Name: gradeable_data; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE gradeable_data (
    gd_id integer NOT NULL,
    g_id character varying(255) NOT NULL,
    gd_user_id character varying(255) NOT NULL,
    gd_grader_id character varying(255) NOT NULL,
    gd_overall_comment character varying NOT NULL,
    gd_status integer NOT NULL,
    gd_late_days_used integer NOT NULL,
    gd_active_version integer NOT NULL
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
-- Name: gradeable_data_gd_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE gradeable_data_gd_id_seq OWNED BY gradeable_data.gd_id;


--
-- Name: grading_registration; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE grading_registration (
    sections_registration_id integer NOT NULL,
    user_id character varying NOT NULL
);


--
-- Name: grading_rotating; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE grading_rotating (
    g_id character varying NOT NULL,
    user_id character varying NOT NULL,
    sections_rotating integer NOT NULL
);


--
-- Name: late_day_exceptions; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE late_day_exceptions (
    g_id character varying(255) NOT NULL,
    user_id character varying(255) NOT NULL,
    late_day_exceptions integer NOT NULL
);


--
-- Name: late_days; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE late_days (
    user_id character varying(255) NOT NULL,
    allowed_late_days integer NOT NULL,
    since_timestamp timestamp without time zone NOT NULL
);


--
-- Name: sections_registration; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE sections_registration (
    sections_registration_id integer NOT NULL
);


--
-- Name: sections_rotating; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE sections_rotating (
    sections_rotating_id integer NOT NULL
);


--
-- Name: users; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE users (
    user_id character varying NOT NULL,
    user_firstname character varying NOT NULL,
    user_lastname character varying NOT NULL,
    user_email character varying NOT NULL,
    user_group integer NOT NULL,
    registration_section integer,
    rotating_section integer,
    manual_registration boolean DEFAULT false,
    CONSTRAINT users_user_group_check CHECK (((user_group >= 0) AND (user_group <= 4)))
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
-- Name: gradeable_component_data_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY gradeable_component_data
    ADD CONSTRAINT gradeable_component_data_pkey PRIMARY KEY (gc_id, gd_id);


--
-- Name: gradeable_component_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY gradeable_component
    ADD CONSTRAINT gradeable_component_pkey PRIMARY KEY (gc_id);


--
-- Name: gradeable_data_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY gradeable_data
    ADD CONSTRAINT gradeable_data_pkey PRIMARY KEY (gd_id);


--
-- Name: gradeable_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY gradeable
    ADD CONSTRAINT gradeable_pkey PRIMARY KEY (g_id);


--
-- Name: grading_registration_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY grading_registration
    ADD CONSTRAINT grading_registration_pkey PRIMARY KEY (sections_registration_id, user_id);


--
-- Name: late_day_exceptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY late_day_exceptions
    ADD CONSTRAINT late_day_exceptions_pkey PRIMARY KEY (g_id, user_id);


--
-- Name: late_days_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY late_days
    ADD CONSTRAINT late_days_pkey PRIMARY KEY (user_id);


--
-- Name: sections_registration_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY sections_registration
    ADD CONSTRAINT sections_registration_pkey PRIMARY KEY (sections_registration_id);


--
-- Name: sections_rotating_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY sections_rotating
    ADD CONSTRAINT sections_rotating_pkey PRIMARY KEY (sections_rotating_id);


--
-- Name: users_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_pkey PRIMARY KEY (user_id);


--
-- Name: electronic_gradeable_g_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY electronic_gradeable
    ADD CONSTRAINT electronic_gradeable_g_id_fkey FOREIGN KEY (g_id) REFERENCES gradeable(g_id) ON DELETE CASCADE;


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
-- Name: gradeable_component_g_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY gradeable_component
    ADD CONSTRAINT gradeable_component_g_id_fkey FOREIGN KEY (g_id) REFERENCES gradeable(g_id) ON DELETE CASCADE;


--
-- Name: gradeable_data_g_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY gradeable_data
    ADD CONSTRAINT gradeable_data_g_id_fkey FOREIGN KEY (g_id) REFERENCES gradeable(g_id) ON DELETE CASCADE;


--
-- Name: gradeable_data_gd_grader_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY gradeable_data
    ADD CONSTRAINT gradeable_data_gd_grader_id_fkey FOREIGN KEY (gd_grader_id) REFERENCES users(user_id);


--
-- Name: gradeable_data_gd_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY gradeable_data
    ADD CONSTRAINT gradeable_data_gd_user_id_fkey FOREIGN KEY (gd_user_id) REFERENCES users(user_id);


--
-- Name: grading_registration_sections_registration_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grading_registration
    ADD CONSTRAINT grading_registration_sections_registration_id_fkey FOREIGN KEY (sections_registration_id) REFERENCES sections_registration(sections_registration_id);


--
-- Name: grading_registration_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grading_registration
    ADD CONSTRAINT grading_registration_user_id_fkey FOREIGN KEY (user_id) REFERENCES users(user_id);


--
-- Name: grading_rotating_g_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grading_rotating
    ADD CONSTRAINT grading_rotating_g_id_fkey FOREIGN KEY (g_id) REFERENCES gradeable(g_id) ON DELETE CASCADE;


--
-- Name: grading_rotating_sections_rotating_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grading_rotating
    ADD CONSTRAINT grading_rotating_sections_rotating_fkey FOREIGN KEY (sections_rotating) REFERENCES sections_rotating(sections_rotating_id) ON DELETE CASCADE;


--
-- Name: grading_rotating_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grading_rotating
    ADD CONSTRAINT grading_rotating_user_id_fkey FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE;


--
-- Name: late_day_exceptions_g_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY late_day_exceptions
    ADD CONSTRAINT late_day_exceptions_g_id_fkey FOREIGN KEY (g_id) REFERENCES gradeable(g_id);


--
-- Name: late_day_exceptions_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY late_day_exceptions
    ADD CONSTRAINT late_day_exceptions_user_id_fkey FOREIGN KEY (user_id) REFERENCES users(user_id);


--
-- Name: late_days_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY late_days
    ADD CONSTRAINT late_days_user_id_fkey FOREIGN KEY (user_id) REFERENCES users(user_id);


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
-- Name: public; Type: ACL; Schema: -; Owner: -
--

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM postgres;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO PUBLIC;


--
-- PostgreSQL database dump complete
--

