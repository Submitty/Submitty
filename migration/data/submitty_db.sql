--
-- PostgreSQL database dump
--

-- Dumped from database version 9.5.13
-- Dumped by pg_dump version 9.5.13

SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: plpgsql; Type: EXTENSION; Schema: -; Owner: 
--

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;


--
-- Name: EXTENSION plpgsql; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';


--
-- Name: dblink; Type: EXTENSION; Schema: -; Owner: 
--

CREATE EXTENSION IF NOT EXISTS dblink WITH SCHEMA public;


--
-- Name: EXTENSION dblink; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION dblink IS 'connect to other PostgreSQL databases from within a database';


--
-- Name: sync_courses_user(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.sync_courses_user() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
  user_row record;
  db_conn varchar;
  query_string text;
BEGIN
  db_conn := format('dbname=submitty_%s_%s', NEW.semester, NEW.course);

  IF (TG_OP = 'INSERT') THEN
    -- FULL data sync on INSERT of a new user record.
    SELECT * INTO user_row FROM users WHERE user_id=NEW.user_id;
    query_string := 'INSERT INTO users (user_id, user_firstname, user_preferred_firstname, user_lastname, user_email, user_group, registration_section, manual_registration) ' ||
                    'VALUES (' || quote_literal(user_row.user_id) || ', ' || quote_literal(user_row.user_firstname) || ', ' || quote_nullable(user_row.user_preferred_firstname) || ', ' ||
                    '' || quote_literal(user_row.user_lastname) || ', ' || quote_literal(user_row.user_email) || ', ' || NEW.user_group || ', ' || quote_nullable(NEW.registration_section) || ', ' || NEW.manual_registration || ')';
    IF query_string IS NULL THEN
      RAISE EXCEPTION 'dblink_query set as NULL';
    END IF;
    PERFORM dblink_exec(db_conn, query_string);

  ELSE
    -- User update on registration_section
    -- CASE clause ensures user's rotating section is set NULL when
    -- registration is updated to NULL.  (e.g. student has dropped)
    query_string = 'UPDATE users SET user_group=' || NEW.user_group || ', registration_section=' || quote_nullable(NEW.registration_section) || ', rotating_section=' || CASE WHEN NEW.registration_section IS NULL THEN 'null' ELSE 'rotating_section' END || ', manual_registration=' || NEW.manual_registration || ' WHERE user_id=' || QUOTE_LITERAL(NEW.user_id);
    IF query_string IS NULL THEN
      RAISE EXCEPTION 'dblink_query set as NULL';
    END IF;
    PERFORM dblink_exec(db_conn, query_string);
  END IF;

  -- All done.
  RETURN NULL;
END;
$$;


ALTER FUNCTION public.sync_courses_user() OWNER TO postgres;

--
-- Name: sync_registration_section(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.sync_registration_section() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
  registration_row RECORD;
  db_conn VARCHAR;
  query_string TEXT;
BEGIN
  FOR registration_row IN SELECT semester, course FROM courses_registration_sections WHERE registration_section_id=NEW.registration_section_id LOOP
    RAISE NOTICE 'Semester: %, Course: %, Registration Section: %', registration_row.semester, registration_row.course, NEW.registration_section_id;
    db_conn := format('dbname=submitty_%s_%s', registration_row.semester, registration_row.course);
    query_string := 'INSERT INTO sections_registration VALUES(' || quote_literal(NEW.registration_section_id) || ') ON CONFLICT DO NOTHING';
    -- Need to make sure that query_string was set properly as dblink_exec will happily take a null and then do nothing
    IF query_string IS NULL THEN
      RAISE EXCEPTION 'dblink_query set as NULL';
    END IF;
    PERFORM dblink_exec(db_conn, query_string);
  END LOOP;

  -- All done.
  RETURN NULL;
END;
$$;


ALTER FUNCTION public.sync_registration_section() OWNER TO postgres;

--
-- Name: sync_user(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.sync_user() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
  course_row RECORD;
  db_conn VARCHAR;
  query_string TEXT;
BEGIN
  FOR course_row IN SELECT semester, course FROM courses_users WHERE user_id=NEW.user_id LOOP
    RAISE NOTICE 'Semester: %, Course: %', course_row.semester, course_row.course;
    db_conn := format('dbname=submitty_%s_%s', course_row.semester, course_row.course);
    query_string := 'UPDATE users SET user_firstname=' || quote_literal(NEW.user_firstname) || ', user_preferred_firstname=' || quote_nullable(NEW.user_preferred_firstname) || ', user_lastname=' || quote_literal(NEW.user_lastname) || ', user_email=' || quote_literal(NEW.user_email) || ' WHERE user_id=' || quote_literal(NEW.user_id);
    -- Need to make sure that query_string was set properly as dblink_exec will happily take a null and then do nothing
    IF query_string IS NULL THEN
      RAISE EXCEPTION 'dblink_query set as NULL';
    END IF;
    PERFORM dblink_exec(db_conn, query_string);
  END LOOP;

  -- All done.
  RETURN NULL;
END;
$$;


ALTER FUNCTION public.sync_user() OWNER TO postgres;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: courses; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.courses (
    semester character varying(255) NOT NULL,
    course character varying(255) NOT NULL,
    status smallint DEFAULT 1 NOT NULL
);


ALTER TABLE public.courses OWNER TO postgres;

--
-- Name: courses_registration_sections; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.courses_registration_sections (
    semester character varying(255) NOT NULL,
    course character varying(255) NOT NULL,
    registration_section_id character varying(255) NOT NULL
);


ALTER TABLE public.courses_registration_sections OWNER TO postgres;

--
-- Name: courses_users; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.courses_users (
    semester character varying(255) NOT NULL,
    course character varying(255) NOT NULL,
    user_id character varying NOT NULL,
    user_group integer NOT NULL,
    registration_section character varying(255),
    manual_registration boolean DEFAULT false,
    CONSTRAINT users_user_group_check CHECK (((user_group >= 1) AND (user_group <= 4)))
);


ALTER TABLE public.courses_users OWNER TO postgres;

--
-- Name: mapped_courses; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.mapped_courses (
    semester character varying(255) NOT NULL,
    course character varying(255) NOT NULL,
    registration_section character varying(255) NOT NULL,
    mapped_course character varying(255) NOT NULL,
    mapped_section character varying(255) NOT NULL
);


ALTER TABLE public.mapped_courses OWNER TO postgres;

--
-- Name: migrations_master; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.migrations_master (
    id character varying(100) NOT NULL,
    commit_time timestamp without time zone DEFAULT now() NOT NULL,
    status numeric(1,0) DEFAULT 0 NOT NULL
);


ALTER TABLE public.migrations_master OWNER TO postgres;

--
-- Name: migrations_system; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.migrations_system (
    id character varying(100) NOT NULL,
    commit_time timestamp without time zone DEFAULT now() NOT NULL,
    status numeric(1,0) DEFAULT 0 NOT NULL
);


ALTER TABLE public.migrations_system OWNER TO postgres;

--
-- Name: sessions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.sessions (
    session_id character varying(255) NOT NULL,
    user_id character varying(255) NOT NULL,
    csrf_token character varying(255) NOT NULL,
    session_expires timestamp(6) with time zone NOT NULL
);


ALTER TABLE public.sessions OWNER TO postgres;

--
-- Name: users; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.users (
    user_id character varying NOT NULL,
    user_password character varying,
    user_firstname character varying NOT NULL,
    user_preferred_firstname character varying,
    user_lastname character varying NOT NULL,
    user_email character varying NOT NULL,
    user_updated boolean DEFAULT false NOT NULL,
    instructor_updated boolean DEFAULT false NOT NULL,
    last_updated timestamp(6) with time zone
);


ALTER TABLE public.users OWNER TO postgres;

--
-- Name: courses_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.courses
    ADD CONSTRAINT courses_pkey PRIMARY KEY (semester, course);


--
-- Name: courses_registration_sections_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.courses_registration_sections
    ADD CONSTRAINT courses_registration_sections_pkey PRIMARY KEY (semester, course, registration_section_id);


--
-- Name: courses_users_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.courses_users
    ADD CONSTRAINT courses_users_pkey PRIMARY KEY (semester, course, user_id);


--
-- Name: mapped_courses_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.mapped_courses
    ADD CONSTRAINT mapped_courses_pkey PRIMARY KEY (semester, course, registration_section);


--
-- Name: migrations_master_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.migrations_master
    ADD CONSTRAINT migrations_master_pkey PRIMARY KEY (id);


--
-- Name: migrations_system_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.migrations_system
    ADD CONSTRAINT migrations_system_pkey PRIMARY KEY (id);


--
-- Name: sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (session_id);


--
-- Name: users_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (user_id);


--
-- Name: registration_sync_registration_id; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER registration_sync_registration_id AFTER INSERT ON public.courses_registration_sections FOR EACH ROW EXECUTE PROCEDURE public.sync_registration_section();


--
-- Name: user_sync_courses_users; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER user_sync_courses_users AFTER INSERT OR UPDATE ON public.courses_users FOR EACH ROW EXECUTE PROCEDURE public.sync_courses_user();


--
-- Name: user_sync_users; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER user_sync_users AFTER UPDATE ON public.users FOR EACH ROW EXECUTE PROCEDURE public.sync_user();


--
-- Name: courses_registration_sections_semester_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.courses_registration_sections
    ADD CONSTRAINT courses_registration_sections_semester_fkey FOREIGN KEY (semester, course) REFERENCES public.courses(semester, course) ON UPDATE CASCADE;


--
-- Name: courses_users_course_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.courses_users
    ADD CONSTRAINT courses_users_course_fkey FOREIGN KEY (semester, course) REFERENCES public.courses(semester, course) ON UPDATE CASCADE;


--
-- Name: courses_users_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.courses_users
    ADD CONSTRAINT courses_users_user_fkey FOREIGN KEY (user_id) REFERENCES public.users(user_id) ON UPDATE CASCADE;


--
-- Name: mapped_courses_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.mapped_courses
    ADD CONSTRAINT mapped_courses_fkey FOREIGN KEY (semester, mapped_course) REFERENCES public.courses(semester, course) ON UPDATE CASCADE;


--
-- Name: sessions_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_fkey FOREIGN KEY (user_id) REFERENCES public.users(user_id) ON UPDATE CASCADE;


--
-- Name: SCHEMA public; Type: ACL; Schema: -; Owner: hsdbu
--

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM hsdbu;
GRANT ALL ON SCHEMA public TO hsdbu;
GRANT ALL ON SCHEMA public TO PUBLIC;


--
-- PostgreSQL database dump complete
--

