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
CREATE EXTENSION IF NOT EXISTS pgcrypto WITH SCHEMA public;

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

CREATE TABLE terms (
    term_id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    start_date date NOT NULL,
    end_date date NOT NULL,
    CONSTRAINT terms_check CHECK (end_date > start_date)
);


CREATE TABLE courses (
    semester character varying(255) NOT NULL,
    course character varying(255) NOT NULL,
    status smallint NOT NULL default 1
);


CREATE TABLE emails (
    id serial NOT NULL,
    user_id character varying NOT NULL,
    subject TEXT NOT NULL,
    body TEXT NOT NULL,
    created TIMESTAMP WITHOUT TIME zone NOT NULL,
    sent TIMESTAMP WITHOUT TIME zone,
    error character varying NOT NULL default ''
);


CREATE TABLE mapped_courses (
    semester character varying(255) NOT NULL,
    course character varying(255) NOT NULL,
    registration_section character varying(255) NOT NULL,
    mapped_course character varying(255) NOT NULL,
    mapped_section character varying(255) NOT NULL
);


CREATE TABLE migrations_master (
  id VARCHAR(100) PRIMARY KEY NOT NULL,
  commit_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status NUMERIC(1) DEFAULT 0 NOT NULL
);


CREATE TABLE migrations_system (
  id VARCHAR(100) PRIMARY KEY NOT NULL,
  commit_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status NUMERIC(1) DEFAULT 0 NOT NULL
);


--
-- TOC entry 184 (class 1259 OID 19651)
-- Name: courses_users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE courses_users (
    semester character varying(255) NOT NULL,
    course character varying(255) NOT NULL,
    user_id character varying NOT NULL,
    user_group integer NOT NULL,
    registration_section character varying(255),
    manual_registration boolean DEFAULT false,
    CONSTRAINT users_user_group_check CHECK ((user_group >= 1) AND (user_group <= 4))
);


--
-- TOC entry 182 (class 1259 OID 19631)
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE sessions (
    session_id character varying(255) NOT NULL,
    user_id character varying(255) NOT NULL,
    csrf_token character varying(255) NOT NULL,
    session_expires timestamp(6) with time zone NOT NULL
);


--
-- TOC entry 181 (class 1259 OID 19623)
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE users (
    user_id character varying NOT NULL,
    user_numeric_id character varying,
    user_password character varying,
    user_firstname character varying NOT NULL,
    user_preferred_firstname character varying,
    user_lastname character varying NOT NULL,
    user_preferred_lastname character varying,
    user_access_level INTEGER NOT NULL DEFAULT 3,
    user_email character varying NOT NULL,
    user_updated BOOLEAN NOT NULL DEFAULT FALSE,
    instructor_updated BOOLEAN NOT NULL DEFAULT FALSE,
    last_updated timestamp(6) with time zone,
    api_key character varying(255) NOT NULL UNIQUE DEFAULT encode(gen_random_bytes(16), 'hex'),
    time_zone VARCHAR NOT NULL DEFAULT 'NOT_SET/NOT_SET',
    CONSTRAINT users_user_access_level_check CHECK ((user_access_level >= 1) AND (user_access_level <= 3))
);

CREATE TABLE courses_registration_sections (
    semester character varying(255) NOT NULL,
    course character varying(255) NOT NULL,
    registration_section_id character varying(255) NOT NULL
);

--
-- TOC entry 2035 (class 2606 OID 19650)
-- Name: courses_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY terms
    ADD CONSTRAINT terms_pkey PRIMARY KEY (term_id);

ALTER TABLE ONLY courses
    ADD CONSTRAINT courses_pkey PRIMARY KEY (semester, course);

ALTER TABLE ONLY emails
    ADD CONSTRAINT emails_pkey PRIMARY KEY (id);

ALTER TABLE ONLY mapped_courses
    ADD CONSTRAINT mapped_courses_pkey PRIMARY KEY (semester, course, registration_section);

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


ALTER TABLE ONLY courses_registration_sections
    ADD CONSTRAINT courses_registration_sections_pkey PRIMARY KEY (semester, course, registration_section_id);

ALTER TABLE ONLY courses
    ADD CONSTRAINT courses_fkey FOREIGN KEY (semester) REFERENCES terms (term_id) ON UPDATE CASCADE;

ALTER TABLE ONLY mapped_courses
    ADD CONSTRAINT mapped_courses_fkey FOREIGN KEY (semester, mapped_course) REFERENCES courses(semester, course) ON UPDATE CASCADE;
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



ALTER TABLE ONLY emails
    ADD CONSTRAINT emails_user_id_fk FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE;


--
-- TOC entry 2038 (class 2606 OID 19641)
-- Name: sessions_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY sessions
    ADD CONSTRAINT sessions_fkey FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE;


ALTER TABLE ONLY courses_registration_sections
    ADD CONSTRAINT courses_registration_sections_fkey FOREIGN KEY (semester, course) REFERENCES courses(semester, course) ON UPDATE CASCADE;


-- Completed on 2017-06-12 14:35:07 EDT

--
-- PostgreSQL database dump complete
--

--
-- plpgsql functions and triggers
--

CREATE EXTENSION IF NOT EXISTS dblink;

CREATE OR REPLACE FUNCTION sync_courses_user() RETURNS TRIGGER AS
-- TRIGGER function to sync users data on INSERT or UPDATE of user_record in
-- table courses_user.
$$
DECLARE
    user_row record;
    db_conn varchar;
    query_string text;
BEGIN
    db_conn := format('dbname=submitty_%s_%s', NEW.semester, NEW.course);

    IF (TG_OP = 'INSERT') THEN
        -- FULL data sync on INSERT of a new user record.
        SELECT * INTO user_row FROM users WHERE user_id=NEW.user_id;
        query_string := 'INSERT INTO users (user_id, user_numeric_id, user_firstname, user_preferred_firstname, user_lastname, user_preferred_lastname, user_email, user_updated, instructor_updated, user_group, registration_section, manual_registration) ' ||
                        'VALUES (' || quote_literal(user_row.user_id) || ', ' || quote_nullable(user_row.user_numeric_id) || ', ' || quote_literal(user_row.user_firstname) || ', ' || quote_nullable(user_row.user_preferred_firstname) || ', ' || quote_literal(user_row.user_lastname) || ', ' ||
                        '' || quote_nullable(user_row.user_preferred_lastname) || ', ' || quote_literal(user_row.user_email) || ', ' || quote_literal(user_row.user_updated) || ', ' || quote_literal(user_row.instructor_updated) || ', ' ||
                        '' || NEW.user_group || ', ' || quote_nullable(NEW.registration_section) || ', ' || NEW.manual_registration || ')';
        IF query_string IS NULL THEN
            RAISE EXCEPTION 'query_string error in trigger function sync_courses_user() when doing INSERT';
        END IF;
        PERFORM dblink_exec(db_conn, query_string);
    ELSIF (TG_OP = 'UPDATE') THEN
        -- User update on registration_section
        -- CASE clause ensures user's rotating section is set NULL when
        -- registration is updated to NULL.  (e.g. student has dropped)
        query_string = 'UPDATE users SET user_group=' || NEW.user_group || ', registration_section=' || quote_nullable(NEW.registration_section) || ', rotating_section=' || CASE WHEN NEW.registration_section IS NULL THEN 'null' ELSE 'rotating_section' END || ', manual_registration=' || NEW.manual_registration || ' WHERE user_id=' || QUOTE_LITERAL(NEW.user_id);
        IF query_string IS NULL THEN
            RAISE EXCEPTION 'query_string error in trigger function sync_courses_user() when doing UPDATE';
        END IF;
        PERFORM dblink_exec(db_conn, query_string);
    END IF;

    -- All done.
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION sync_user() RETURNS trigger AS
-- TRIGGER function to sync users data on UPDATE of user_record in table users.
-- NOTE: INSERT should not trigger this function as function sync_courses_users
-- will also sync users -- but only on INSERT.
$$
DECLARE
    course_row RECORD;
    db_conn VARCHAR;
    query_string TEXT;
    preferred_name_change_details TEXT;
BEGIN
    -- Check for changes in users.user_preferred_firstname and users.user_preferred_lastname.
    IF coalesce(OLD.user_preferred_firstname, '') <> coalesce(NEW.user_preferred_firstname, '') THEN
        preferred_name_change_details := format('PREFERRED_FIRSTNAME OLD: "%s" NEW: "%s" ', OLD.user_preferred_firstname, NEW.user_preferred_firstname);
    END IF;
    IF coalesce(OLD.user_preferred_lastname, '') <> coalesce(NEW.user_preferred_lastname, '') THEN
        preferred_name_change_details := format('%sPREFERRED_LASTNAME OLD: "%s" NEW: "%s"', preferred_name_change_details, OLD.user_preferred_lastname, NEW.user_preferred_lastname);
    END IF;
    -- If any preferred_name data has changed, preferred_name_change_details will not be NULL.
    IF preferred_name_change_details IS NOT NULL THEN
        preferred_name_change_details := format('USER_ID: "%s" %s', NEW.user_id, preferred_name_change_details);
        RAISE LOG USING MESSAGE = 'PREFERRED_NAME DATA UPDATE', DETAIL = preferred_name_change_details;
    END IF;
    -- Propagate UPDATE to course DBs
    FOR course_row IN SELECT semester, course FROM courses_users WHERE user_id=NEW.user_id LOOP
        RAISE NOTICE 'Semester: %, Course: %', course_row.semester, course_row.course;
        db_conn := format('dbname=submitty_%s_%s', course_row.semester, course_row.course);
        query_string := 'UPDATE users SET user_numeric_id=' || quote_nullable(NEW.user_numeric_id) || ', user_firstname=' || quote_literal(NEW.user_firstname) || ', user_preferred_firstname=' || quote_nullable(NEW.user_preferred_firstname) || ', user_lastname=' || quote_literal(NEW.user_lastname) || ', user_preferred_lastname=' || quote_nullable(NEW.user_preferred_lastname) || ', user_email=' || quote_literal(NEW.user_email) || ', user_updated=' || quote_literal(NEW.user_updated) || ', instructor_updated=' || quote_literal(NEW.instructor_updated) || ' WHERE user_id=' || quote_literal(NEW.user_id);
        -- Need to make sure that query_string was set properly as dblink_exec will happily take a null and then do nothing
        IF query_string IS NULL THEN
            RAISE EXCEPTION 'query_string error in trigger function sync_user()';
        END IF;
        PERFORM dblink_exec(db_conn, query_string);
    END LOOP;

    -- All done.
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;


CREATE OR REPLACE FUNCTION sync_insert_registration_section() RETURNS trigger AS $$
-- AFTER INSERT trigger function to INSERT registration sections to course DB, as needed.
DECLARE
    registration_row RECORD;
    db_conn VARCHAR;
    query_string TEXT;
BEGIN
    db_conn := format('dbname=submitty_%s_%s', NEW.semester, NEW.course);
    query_string := 'INSERT INTO sections_registration VALUES(' || quote_literal(NEW.registration_section_id) || ') ON CONFLICT DO NOTHING';
    -- Need to make sure that query_string was set properly as dblink_exec will happily take a null and then do nothing
    IF query_string IS NULL THEN
        RAISE EXCEPTION 'query_string error in trigger function sync_insert_registration_section()';
    END IF;
    PERFORM dblink_exec(db_conn, query_string);

    -- All done.
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION sync_delete_registration_section() RETURNS TRIGGER AS $$
-- BEFORE DELETE trigger function to DELETE registration sections from course DB, as needed.
DECLARE
    registration_row RECORD;
    db_conn VARCHAR;
    query_string TEXT;
BEGIN
    db_conn := format('dbname=submitty_%s_%s', OLD.semester, OLD.course);
    query_string := 'DELETE FROM sections_registration WHERE sections_registration_id = ' || quote_literal(OLD.registration_section_id);
    -- Need to make sure that query_string was set properly as dblink_exec will happily take a null and then do nothing
    IF query_string IS NULL THEN
        RAISE EXCEPTION 'query_string error in trigger function sync_delete_registration_section()';
    END IF;
    PERFORM dblink_exec(db_conn, query_string);

    -- All done.  As this is a BEFORE DELETE trigger, RETURN OLD allows original triggering DELETE query to proceed.
    RETURN OLD;

-- Trying to delete a registration section while users are still enrolled will raise an integrity constraint violation exception.
-- We should catch this exception and stop execution with no rows processed.
-- No rows processed will indicate to the UsersController that deletion had an error and did not occur.
EXCEPTION WHEN integrity_constraint_violation THEN
    RAISE NOTICE 'Users are still enrolled in registration section ''%''', OLD.registration_section_id;
    -- Return NULL so we do not proceed with original triggering DELETE query.
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION generate_api_key() RETURNS TRIGGER AS $generate_api_key$
-- TRIGGER function to generate api_key on INSERT or UPDATE of user_password in
-- table users.
BEGIN
    NEW.api_key := encode(gen_random_bytes(16), 'hex');
    RETURN NEW;
END;
$generate_api_key$ LANGUAGE plpgsql;

-- Foreign Key Constraint *REQUIRES* insert trigger to be assigned to course_users.
-- Updates can happen in either users and/or courses_users.
CREATE TRIGGER user_sync_courses_users AFTER INSERT OR UPDATE ON courses_users FOR EACH ROW EXECUTE PROCEDURE sync_courses_user();
CREATE TRIGGER user_sync_users AFTER UPDATE ON users FOR EACH ROW EXECUTE PROCEDURE sync_user();

-- INSERT and DELETE triggers for syncing registration sections happen on different instances of TG_WHEN (after vs before).
CREATE TRIGGER insert_sync_registration_id AFTER INSERT OR UPDATE ON courses_registration_sections FOR EACH ROW EXECUTE PROCEDURE sync_insert_registration_section();
CREATE TRIGGER delete_sync_registration_id BEFORE DELETE ON courses_registration_sections FOR EACH ROW EXECUTE PROCEDURE sync_delete_registration_section();

-- Generate API key when a user is created or its password is changed.
CREATE TRIGGER generate_api_key BEFORE INSERT OR UPDATE OF user_password ON users FOR EACH ROW EXECUTE PROCEDURE generate_api_key();
