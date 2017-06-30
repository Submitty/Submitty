--
-- PostgreSQL database dump
--

-- Dumped from database version 9.5.7
-- Dumped by pg_dump version 9.5.1

-- Started on 2017-06-12 14:35:03 EDT

SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET row_security = off;

--
-- TOC entry 1 (class 3079 OID 12393)
-- Name: plpgsql; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;


--
-- TOC entry 2161 (class 0 OID 0)
-- Dependencies: 1
-- Name: EXTENSION plpgsql; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';


SET search_path = public, pg_catalog;

SET default_with_oids = false;

--
-- TOC entry 183 (class 1259 OID 19646)
-- Name: courses; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE courses (
    semester character varying(5) NOT NULL,
    course character varying(10) NOT NULL
);


--
-- TOC entry 184 (class 1259 OID 19651)
-- Name: courses_users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE courses_users (
    semester character varying(5) NOT NULL,
    course character varying(10) NOT NULL,
    user_id character varying NOT NULL,
    user_group integer NOT NULL,
    registration_section integer,
    manual_registration boolean DEFAULT false,
    CONSTRAINT users_user_group_check CHECK ((user_group >= 0) AND (user_group <= 4))
);


--
-- TOC entry 182 (class 1259 OID 19631)
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE sessions (
    session_id character varying(255) NOT NULL,
    user_id character varying(255) NOT NULL,
    csrf_token character varying(255) NOT NULL,
    session_expires timestamp without time zone NOT NULL
);


--
-- TOC entry 181 (class 1259 OID 19623)
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE users (
    user_id character varying NOT NULL,
    user_password character varying,
    user_firstname character varying NOT NULL,
    user_preferred_firstname character varying,
    user_lastname character varying NOT NULL,
    user_email character varying NOT NULL,
    user_updated BOOLEAN NOT NULL DEFAULT FALSE,
    instructor_updated BOOLEAN NOT NULL DEFAULT FALSE,
    last_updated timestamp WITHOUT time zone
);


--
-- TOC entry 2035 (class 2606 OID 19650)
-- Name: courses_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY courses
    ADD CONSTRAINT courses_pkey PRIMARY KEY (semester, course);


--
-- TOC entry 2037 (class 2606 OID 19658)
-- Name: courses_users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY courses_users
    ADD CONSTRAINT courses_users_pkey PRIMARY KEY (semester, course, user_id);


--
-- TOC entry 2033 (class 2606 OID 19638)
-- Name: sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (session_id);


--
-- TOC entry 2031 (class 2606 OID 19640)
-- Name: users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_pkey PRIMARY KEY (user_id);


--
-- TOC entry 2039 (class 2606 OID 19659)
-- Name: courses_users_course_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY courses_users
    ADD CONSTRAINT courses_users_course_fkey FOREIGN KEY (semester, course) REFERENCES courses(semester, course) ON UPDATE CASCADE;


--
-- TOC entry 2040 (class 2606 OID 19664)
-- Name: courses_users_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY courses_users
    ADD CONSTRAINT courses_users_user_fkey FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE;


--
-- TOC entry 2038 (class 2606 OID 19641)
-- Name: sessions_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY sessions
    ADD CONSTRAINT sessions_fkey FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE;


-- Completed on 2017-06-12 14:35:07 EDT

--
-- PostgreSQL database dump complete
--

--
-- NEW Code by PDB
--

CREATE EXTENSION IF NOT EXISTS postgres_fdw;

CREATE OR REPLACE FUNCTION sync_user()
	RETURNS trigger AS
$func$
DECLARE
	sync_semester text;
	sync_course text;
	sync_db_conn text;
BEGIN
	-- First, determine which course DB the student data needs to be synced with.
	-- Because DB can change student to student, wrapper connection needs to be dynamically built.
	SELECT
		courses_users.semester,
		courses_users.course
	INTO
		sync_semester,
		sync_course
	FROM courses_users
	WHERE courses_users.user_id = NEW.user_id;

	sync_db_conn := format(
		'CREATE SERVER data_sync FOREIGN DATA WRAPPER postgres_fdw OPTIONS (dbname ''submitty_%s_%s'')',
		lower(sync_semester),
		lower(sync_course)
	);

	--Create foreign data wrapper.
	EXECUTE sync_db_conn;
	CREATE USER MAPPING FOR CURRENT_USER SERVER data_sync OPTIONS (user 'hsdbu');
	CREATE FOREIGN TABLE IF NOT EXISTS table_sync (
		user_id character varying NOT NULL,
		user_firstname character varying NOT NULL,
		user_preferred_firstname character varying,
		user_lastname character varying NOT NULL,
		user_email character varying NOT NULL,
		user_group integer NOT NULL
	) SERVER data_sync OPTIONS (table_name 'users');

	IF (TG_OP = 'INSERT') THEN
		-- FULL data sync on INSERT of a new record.
		INSERT INTO table_sync (
			user_id,
			user_firstname,
			user_preferred_firstname,
			user_lastname,
			user_email,
			user_group
		) SELECT
			users.user_id,
			users.user_firstname,
			users.user_preferred_firstname,
			users.user_lastname,
			users.user_email,
			courses_users.user_group
		FROM users
		INNER JOIN courses_users ON courses_users.user_id = users.user_id
		WHERE users.user_id = NEW.user_id;
	ELSE
		-- TO DO: Write Update SQL

	END IF;

	-- We're done, drop server to avoid conflicts.
 	DROP SERVER data_sync CASCADE;

	-- All done.
	RETURN NULL;
END;
$func$ LANGUAGE plpgsql;

-- Foreign Key Constraint *REQUIRES* insert trigger to be assigned to course_users.
-- Otherwise, we can't determine what course DB to sync with.
CREATE TRIGGER user_sync_insert AFTER INSERT ON courses_users FOR EACH ROW EXECUTE PROCEDURE sync_user();

CREATE TRIGGER user_sync_update AFTER UPDATE ON users FOR EACH ROW EXECUTE PROCEDURE sync_user();
