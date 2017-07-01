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
-- NEW Code by pbailie, June 30 2017
--

CREATE EXTENSION IF NOT EXISTS postgres_fdw;

CREATE OR REPLACE FUNCTION new_fdw(varchar) RETURNS void AS
-- IN: expected to be NEW.user_id from trigger function.
-- PURPOSE: Setup foreign data wrapper to sync data to users table in course DB.
$$
DECLARE
	sync_user_id ALIAS FOR $1; -- Expected to be NEW.user_id from trigger
	sync_semester varchar;
	sync_course varchar;
	sync_db_conn varchar;
BEGIN

	-- There is likely an fdw server from the last row sync'd.  If so, DROP.
	-- NOTE: ALTER SERVER (as opposed to DROP/CREATE) did not produce expected
	--       results in local testing.
 	DROP SERVER IF EXISTS data_sync CASCADE;

	-- Determine which course DB the user data needs to be synced with.
	-- Because DB can change user to user, wrapper connection needs to be
	-- dynamically built for every row that is sync'd.
	SELECT
		semester,
		course
	INTO
		sync_semester,
		sync_course
	FROM courses_users
	WHERE courses_users.user_id = sync_user_id;

	sync_db_conn := format(
		'CREATE SERVER data_sync FOREIGN DATA WRAPPER postgres_fdw OPTIONS (dbname ''submitty_%s_%s'')',
		lower(sync_semester),
		lower(sync_course)
	);

	-- Create foreign data wrapper to access a course DB.
	-- TO DO: hsdbu_password needs to be altered by setup scripts OR perhaps
	--        implement a different authentication method for hsdbu.
	EXECUTE sync_db_conn;
	CREATE USER MAPPING FOR CURRENT_USER SERVER data_sync; --OPTIONS (user 'hsdbu', password 'hsdbu_password');
	CREATE FOREIGN TABLE IF NOT EXISTS table_sync (
		user_id character varying NOT NULL,
		user_firstname character varying NOT NULL,
		user_preferred_firstname character varying,
		user_lastname character varying NOT NULL,
		user_email character varying NOT NULL,
		user_group integer NOT NULL,
		registration_section integer,
		rotating_section integer
	) SERVER data_sync OPTIONS (table_name 'users');

END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION sync_courses_user() RETURNS TRIGGER AS
-- TRIGGER function to sync users data on INSERT or UPDATE of user_record in
-- table courses_user.
$$
DECLARE
	check_null integer; -- ON UPDATE, is column registration_section NULL?
BEGIN

	PERFORM new_fdw(NEW.user_id);

	IF (TG_OP = 'INSERT') THEN
		-- FULL data sync on INSERT of a new user record.
		INSERT INTO table_sync (
			user_id,
			user_firstname,
			user_preferred_firstname,
			user_lastname,
			user_email,
			user_group,
			registration_section
		) SELECT
			users.user_id,
			users.user_firstname,
			users.user_preferred_firstname,
			users.user_lastname,
			users.user_email,
			courses_users.user_group,
			courses_users.registration_section
		FROM users
		INNER JOIN courses_users ON courses_users.user_id = users.user_id
		WHERE users.user_id = NEW.user_id;
	ELSE
		-- User update on registration_section
		-- CASE clause ensures user's rotating section is set NULL when
		-- registration is updated to NULL.  (e.g. student has dropped)
		UPDATE table_sync
		SET registration_section = courses_users.registration_section,
		    rotating_section = CASE WHEN courses_users.registration_section IS NULL
			THEN NULL
			ELSE rotating_section
			END
		FROM courses_users
		WHERE table_sync.user_id = courses_users.user_id
		AND table_sync.user_id = OLD.user_id;
	END IF;

	-- All done.
	RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION sync_user() RETURNS trigger AS
-- TRIGGER function to sync users data on INSERT or UPDATE of user_record in
-- table users.  NOTE: INSERT should not trigger this function as function
-- sync_courses_users will also sync users -- but only on INSERT.
$$
BEGIN
	PERFORM new_fdw(NEW.user_id);

	-- Data sync on UPDATE for users table
	UPDATE table_sync
	SET user_firstname = users.user_firstname,
        user_preferred_firstname = users.user_preferred_firstname,
        user_lastname = users.user_lastname,
        user_email = users.user_email
    FROM users
    WHERE table_sync.user_id = users.user_id
    AND table_sync.user_id = OLD.user_id;

	-- All done.
	RETURN NULL;
END;
$$ LANGUAGE plpgsql;

-- Foreign Key Constraint *REQUIRES* insert trigger to be assigned to course_users.
-- Updates can happen in either users and/or courses_users.
CREATE TRIGGER user_sync_courses_users AFTER INSERT OR UPDATE ON courses_users FOR EACH ROW EXECUTE PROCEDURE sync_courses_user();
CREATE TRIGGER user_sync_users AFTER UPDATE ON users FOR EACH ROW EXECUTE PROCEDURE sync_user();
