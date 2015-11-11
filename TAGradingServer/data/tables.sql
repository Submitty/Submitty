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

SET default_with_oids = false;

--
-- Name: config; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE config (
    config_name character varying(255) NOT NULL,
    config_type integer NOT NULL,
    config_value character varying(255) NOT NULL
);


--
-- Name: grade_lab_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE grade_lab_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: grade_question_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE grade_question_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: grade_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE grade_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: grade_test_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE grade_test_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: grades; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE grades (
    grade_id integer DEFAULT nextval('grade_sequence'::regclass) NOT NULL,
    rubric_id integer,
    student_id integer,
    grade_user_id integer,
    grade_finish_timestamp timestamp(6) without time zone,
    grade_comment character varying(10240),
    grade_days_late integer,
    grade_is_regraded integer,
    student_rcs character varying,
    grade_submitted integer DEFAULT 0 NOT NULL,
    grade_status integer DEFAULT 0 NOT NULL,
    grade_parts_days_late character varying DEFAULT '0'::character varying NOT NULL,
    grade_parts_submitted character varying DEFAULT '0'::character varying NOT NULL,
    grade_parts_status character varying DEFAULT '0'::character varying NOT NULL,
    grade_active_assignment character varying DEFAULT '1'::character varying NOT NULL
);


--
-- Name: grades_academic_integrity_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE grades_academic_integrity_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: grades_academic_integrity; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE grades_academic_integrity (
    gai_id integer DEFAULT nextval('grades_academic_integrity_seq'::regclass) NOT NULL,
    student_id integer,
    student_rcs character varying,
    rubric_id integer,
    penalty numeric(3,3) DEFAULT NULL::numeric
);


--
-- Name: grades_labs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE grades_labs (
    grade_lab_id integer DEFAULT nextval('grade_lab_sequence'::regclass) NOT NULL,
    lab_id integer,
    student_id integer,
    grade_lab_user_id integer,
    grade_finish_timestamp timestamp without time zone,
    grade_lab_value integer,
    grade_lab_checkpoint integer,
    student_rcs character varying
);


--
-- Name: grades_questions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE grades_questions (
    grade_question_id integer DEFAULT nextval('grade_question_sequence'::regclass) NOT NULL,
    grade_id integer,
    question_id integer,
    grade_question_score real,
    grade_question_comment character varying(10240)
);


--
-- Name: grades_tests; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE grades_tests (
    grade_test_id integer DEFAULT nextval('grade_test_sequence'::regclass) NOT NULL,
    test_id integer,
    student_id integer,
    grade_test_user_id integer,
    student_rcs character varying,
    grade_test_questions numeric[],
    grade_test_value numeric DEFAULT 0 NOT NULL,
    grade_test_text character varying[]
);


--
-- Name: hw_grading_sec_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE hw_grading_sec_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: homework_grading_sections; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE homework_grading_sections (
    hgs_id integer DEFAULT nextval('hw_grading_sec_seq'::regclass) NOT NULL,
    user_id integer,
    rubric_id integer,
    grading_section_id integer
);


--
-- Name: lab_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE lab_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: labs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE labs (
    lab_id integer DEFAULT nextval('lab_sequence'::regclass) NOT NULL,
    lab_number integer,
    lab_title character varying,
    lab_checkpoints character varying,
    lab_code character varying(8)
);


--
-- Name: late_day_exceptions_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE late_day_exceptions_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: late_day_exceptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE late_day_exceptions (
    ex_id integer DEFAULT nextval('late_day_exceptions_seq'::regclass) NOT NULL,
    ex_student_rcs character varying NOT NULL,
    ex_rubric_id integer NOT NULL,
    ex_late_days integer DEFAULT 0 NOT NULL
);


--
-- Name: late_days; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE late_days (
    student_rcs character varying NOT NULL,
    allowed_lates integer DEFAULT 0 NOT NULL,
    since_timestamp timestamp without time zone NOT NULL
);


--
-- Name: question_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE question_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: questions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE questions (
    question_id integer DEFAULT nextval('question_sequence'::regclass) NOT NULL,
    rubric_id integer NOT NULL,
    question_part_number integer NOT NULL,
    question_number integer NOT NULL,
    question_message character varying,
    question_grading_note character varying,
    question_total real NOT NULL,
    question_extra_credit integer DEFAULT 0,
    question_default character varying
);


--
-- Name: relationship_user_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE relationship_user_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: relationships_users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE relationships_users (
    relationship_user_id integer DEFAULT nextval('relationship_user_sequence'::regclass) NOT NULL,
    user_id integer,
    section_id integer
);


--
-- Name: reset_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE reset_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: rubric_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE rubric_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: rubrics; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE rubrics (
    rubric_id integer DEFAULT nextval('rubric_sequence'::regclass) NOT NULL,
    rubric_due_date timestamp(6) without time zone,
    rubric_parts_sep integer DEFAULT 0 NOT NULL,
    rubric_late_days integer DEFAULT (-1) NOT NULL,
    rubric_name character varying,
    rubric_submission_id character varying NOT NULL,
    rubric_parts_submission_id character varying DEFAULT ''::character varying NOT NULL
);


--
-- Name: section_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE section_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sections; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE sections (
    section_id integer DEFAULT nextval('section_sequence'::regclass) NOT NULL,
    section_title character varying(255),
    section_is_enabled integer
);


--
-- Name: session_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE session_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: student_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE student_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: students; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE students (
    student_id integer DEFAULT nextval('student_sequence'::regclass) NOT NULL,
    student_rcs character varying(255) NOT NULL,
    student_last_name character varying(64),
    student_first_name character varying(64),
    student_section_id integer NOT NULL,
    student_grading_id integer DEFAULT 1 NOT NULL,
    student_manual integer DEFAULT 0 NOT NULL
);


--
-- Name: test_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE test_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tests; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE tests (
    test_id integer DEFAULT nextval('test_sequence'::regclass) NOT NULL,
    test_number integer,
    test_type character varying NOT NULL,
    test_code character varying(8),
    test_max_grade numeric DEFAULT 100 NOT NULL,
    test_curve numeric DEFAULT 0 NOT NULL,
    test_questions integer DEFAULT 0 NOT NULL,
    test_locked boolean DEFAULT false NOT NULL,
    test_text_fields integer DEFAULT 0 NOT NULL
);


--
-- Name: user_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE user_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE users (
    user_id integer DEFAULT nextval('user_sequence'::regclass) NOT NULL,
    user_firstname character varying(255),
    user_lastname character varying(255),
    user_rcs character varying(255),
    user_email character varying(255),
    user_is_administrator integer DEFAULT 0 NOT NULL,
    user_is_developer integer DEFAULT 0 NOT NULL
);


--
-- Name: verify_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE verify_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: grades_academic_integrity_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_academic_integrity
    ADD CONSTRAINT grades_academic_integrity_pkey PRIMARY KEY (gai_id);


--
-- Name: grades_labs_copy_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_tests
    ADD CONSTRAINT grades_labs_copy_pkey PRIMARY KEY (grade_test_id);


--
-- Name: grades_labs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_labs
    ADD CONSTRAINT grades_labs_pkey PRIMARY KEY (grade_lab_id);


--
-- Name: grades_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades
    ADD CONSTRAINT grades_pkey PRIMARY KEY (grade_id);


--
-- Name: grades_questions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_questions
    ADD CONSTRAINT grades_questions_pkey PRIMARY KEY (grade_question_id);


--
-- Name: homework_grading_sections_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY homework_grading_sections
    ADD CONSTRAINT homework_grading_sections_pkey PRIMARY KEY (hgs_id);


--
-- Name: labs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY labs
    ADD CONSTRAINT labs_pkey PRIMARY KEY (lab_id);


--
-- Name: late_days_pk; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY late_days
    ADD CONSTRAINT late_days_pk PRIMARY KEY (student_rcs, since_timestamp);


--
-- Name: pk_config; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY config
    ADD CONSTRAINT pk_config PRIMARY KEY (config_name);


--
-- Name: pkey_ex_id; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY late_day_exceptions
    ADD CONSTRAINT pkey_ex_id PRIMARY KEY (ex_id);


--
-- Name: questions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY questions
    ADD CONSTRAINT questions_pkey PRIMARY KEY (question_id);


--
-- Name: relationships_users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY relationships_users
    ADD CONSTRAINT relationships_users_pkey PRIMARY KEY (relationship_user_id);


--
-- Name: rubrics_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY rubrics
    ADD CONSTRAINT rubrics_name_unique UNIQUE (rubric_name);


--
-- Name: rubrics_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY rubrics
    ADD CONSTRAINT rubrics_pkey PRIMARY KEY (rubric_id);


--
-- Name: rubrics_submission_id; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY rubrics
    ADD CONSTRAINT rubrics_submission_id UNIQUE (rubric_submission_id);


--
-- Name: sections_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY sections
    ADD CONSTRAINT sections_pkey PRIMARY KEY (section_id);


--
-- Name: students_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY students
    ADD CONSTRAINT students_pkey PRIMARY KEY (student_rcs);


--
-- Name: students_rcs_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY students
    ADD CONSTRAINT students_rcs_unique UNIQUE (student_rcs);


--
-- Name: tests_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY tests
    ADD CONSTRAINT tests_pkey PRIMARY KEY (test_id);


--
-- Name: users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_pkey PRIMARY KEY (user_id);


--
-- Name: fki_grades_labs_fkey; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fki_grades_labs_fkey ON grades_labs USING btree (lab_id);


--
-- Name: fki_grades_questions_fkey; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fki_grades_questions_fkey ON grades_questions USING btree (grade_id);


--
-- Name: fki_grades_rubric_fkey; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fki_grades_rubric_fkey ON grades USING btree (rubric_id);


--
-- Name: fki_grades_student_fkey; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fki_grades_student_fkey ON grades USING btree (student_rcs);


--
-- Name: fki_grades_tests_fkey; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fki_grades_tests_fkey ON grades_tests USING btree (test_id);


--
-- Name: fki_grades_tests_student_fkey; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fki_grades_tests_student_fkey ON grades_tests USING btree (student_rcs);


--
-- Name: fki_grades_user_fkey; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fki_grades_user_fkey ON grades USING btree (grade_user_id);


--
-- Name: fki_questions_fkey; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fki_questions_fkey ON questions USING btree (rubric_id);


--
-- Name: fki_relationships_users_section_fkey; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fki_relationships_users_section_fkey ON relationships_users USING btree (section_id);


--
-- Name: fki_relationships_users_user_fkey; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fki_relationships_users_user_fkey ON relationships_users USING btree (user_id);


--
-- Name: fkey_rubric_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY late_day_exceptions
    ADD CONSTRAINT fkey_rubric_id FOREIGN KEY (ex_rubric_id) REFERENCES rubrics(rubric_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: fkey_student_rcs; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY late_day_exceptions
    ADD CONSTRAINT fkey_student_rcs FOREIGN KEY (ex_student_rcs) REFERENCES students(student_rcs) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: grades_academic_integrity_rubric_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_academic_integrity
    ADD CONSTRAINT grades_academic_integrity_rubric_fkey FOREIGN KEY (rubric_id) REFERENCES rubrics(rubric_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: grades_academic_integrity_student; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_academic_integrity
    ADD CONSTRAINT grades_academic_integrity_student FOREIGN KEY (student_rcs) REFERENCES students(student_rcs) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: grades_labs_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_labs
    ADD CONSTRAINT grades_labs_fkey FOREIGN KEY (lab_id) REFERENCES labs(lab_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: grades_labs_student_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_labs
    ADD CONSTRAINT grades_labs_student_fkey FOREIGN KEY (student_rcs) REFERENCES students(student_rcs) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: grades_questions_grade_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_questions
    ADD CONSTRAINT grades_questions_grade_fkey FOREIGN KEY (grade_id) REFERENCES grades(grade_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: grades_questions_question_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_questions
    ADD CONSTRAINT grades_questions_question_fkey FOREIGN KEY (question_id) REFERENCES questions(question_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: grades_rubric_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades
    ADD CONSTRAINT grades_rubric_fkey FOREIGN KEY (rubric_id) REFERENCES rubrics(rubric_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: grades_student_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades
    ADD CONSTRAINT grades_student_fkey FOREIGN KEY (student_rcs) REFERENCES students(student_rcs) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: grades_tests_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_tests
    ADD CONSTRAINT grades_tests_fkey FOREIGN KEY (test_id) REFERENCES tests(test_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: grades_tests_students_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_tests
    ADD CONSTRAINT grades_tests_students_fkey FOREIGN KEY (student_rcs) REFERENCES students(student_rcs) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: grades_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades
    ADD CONSTRAINT grades_user_fkey FOREIGN KEY (grade_user_id) REFERENCES users(user_id) ON UPDATE SET NULL ON DELETE SET NULL;


--
-- Name: homework_grading_sections_rubric_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY homework_grading_sections
    ADD CONSTRAINT homework_grading_sections_rubric_id_fkey FOREIGN KEY (rubric_id) REFERENCES rubrics(rubric_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: homework_grading_sections_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY homework_grading_sections
    ADD CONSTRAINT homework_grading_sections_user_id_fkey FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: late_days_student_rcs_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY late_days
    ADD CONSTRAINT late_days_student_rcs_fkey FOREIGN KEY (student_rcs) REFERENCES students(student_rcs) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: questions_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY questions
    ADD CONSTRAINT questions_fkey FOREIGN KEY (rubric_id) REFERENCES rubrics(rubric_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: relationships_users_section_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY relationships_users
    ADD CONSTRAINT relationships_users_section_fkey FOREIGN KEY (section_id) REFERENCES sections(section_id);


--
-- Name: relationships_users_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY relationships_users
    ADD CONSTRAINT relationships_users_user_fkey FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: student_section_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY students
    ADD CONSTRAINT student_section_fkey FOREIGN KEY (student_section_id) REFERENCES sections(section_id);


--
-- PostgreSQL database dump complete
--

