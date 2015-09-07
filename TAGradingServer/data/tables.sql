--
-- PostgreSQL database dump
--

-- Dumped from database version 9.3.5
-- Dumped by pg_dump version 9.4.0
-- Started on 2015-09-04 23:46:50 EDT

SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;

--
-- TOC entry 207 (class 3079 OID 12018)
-- Name: plpgsql; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;


--
-- TOC entry 2421 (class 0 OID 0)
-- Dependencies: 207
-- Name: EXTENSION plpgsql; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';


SET search_path = public, pg_catalog;

SET default_with_oids = false;

--
-- TOC entry 170 (class 1259 OID 53113)
-- Name: config; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE config (
    config_name character varying(255) NOT NULL,
    config_type integer NOT NULL,
    config_value character varying(255) NOT NULL
);


--
-- TOC entry 171 (class 1259 OID 53119)
-- Name: grade_lab_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE grade_lab_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 172 (class 1259 OID 53121)
-- Name: grade_question_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE grade_question_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 173 (class 1259 OID 53123)
-- Name: grade_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE grade_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 174 (class 1259 OID 53125)
-- Name: grade_test_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE grade_test_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 175 (class 1259 OID 53127)
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
    grade_email_timestamp timestamp(6) without time zone,
    student_rcs character varying,
    submitted integer DEFAULT 0 NOT NULL,
    status integer DEFAULT 0 NOT NULL
);


--
-- TOC entry 176 (class 1259 OID 53136)
-- Name: grades_academic_integrity_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE grades_academic_integrity_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 177 (class 1259 OID 53138)
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
-- TOC entry 178 (class 1259 OID 53146)
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
-- TOC entry 179 (class 1259 OID 53153)
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
-- TOC entry 180 (class 1259 OID 53160)
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
-- TOC entry 181 (class 1259 OID 53168)
-- Name: hw_grading_sec_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE hw_grading_sec_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 182 (class 1259 OID 53170)
-- Name: homework_grading_sections; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE homework_grading_sections (
    hgs_id integer DEFAULT nextval('hw_grading_sec_seq'::regclass) NOT NULL,
    user_id integer,
    rubric_id integer,
    grading_section_id integer
);


--
-- TOC entry 183 (class 1259 OID 53174)
-- Name: lab_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE lab_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 184 (class 1259 OID 53176)
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
-- TOC entry 185 (class 1259 OID 53183)
-- Name: late_day_exceptions_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE late_day_exceptions_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 186 (class 1259 OID 53185)
-- Name: late_day_exceptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE late_day_exceptions (
    ex_id integer DEFAULT nextval('late_day_exceptions_seq'::regclass) NOT NULL,
    ex_student_rcs character varying NOT NULL,
    ex_rubric_id integer NOT NULL,
    ex_late_days integer DEFAULT 0 NOT NULL
);


--
-- TOC entry 187 (class 1259 OID 53193)
-- Name: late_days; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE late_days (
    student_rcs character varying NOT NULL,
    allowed_lates integer DEFAULT 0 NOT NULL,
    since_timestamp timestamp without time zone NOT NULL
);


--
-- TOC entry 188 (class 1259 OID 53200)
-- Name: question_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE question_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 189 (class 1259 OID 53202)
-- Name: questions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE questions (
    question_id integer DEFAULT nextval('question_sequence'::regclass) NOT NULL,
    rubric_id integer,
    question_part_number integer,
    question_number integer,
    question_message character varying(255),
    question_grading_note character varying(255),
    question_total real,
    question_extra_credit integer DEFAULT 0,
    question_default character varying
);


--
-- TOC entry 190 (class 1259 OID 53210)
-- Name: relationship_student_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE relationship_student_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 191 (class 1259 OID 53212)
-- Name: relationship_user_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE relationship_user_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 192 (class 1259 OID 53214)
-- Name: relationships_students; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE relationships_students (
    relationship_student_id integer DEFAULT nextval('relationship_student_sequence'::regclass) NOT NULL,
    student_id integer,
    section_id integer,
    student_rcs character varying
);


--
-- TOC entry 193 (class 1259 OID 53221)
-- Name: relationships_users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE relationships_users (
    relationship_user_id integer DEFAULT nextval('relationship_user_sequence'::regclass) NOT NULL,
    user_id integer,
    section_id integer
);


--
-- TOC entry 194 (class 1259 OID 53225)
-- Name: reset_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE reset_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 195 (class 1259 OID 53227)
-- Name: rubric_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE rubric_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 196 (class 1259 OID 53229)
-- Name: rubrics; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE rubrics (
    rubric_id integer DEFAULT nextval('rubric_sequence'::regclass) NOT NULL,
    rubric_number integer,
    rubric_due_date timestamp(6) without time zone,
    rubric_code character varying(8),
    rubric_parts_sep boolean DEFAULT false NOT NULL,
    rubric_late_days integer DEFAULT (-1) NOT NULL,
    rubric_name character varying(20)
);


--
-- TOC entry 197 (class 1259 OID 53235)
-- Name: section_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE section_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 198 (class 1259 OID 53237)
-- Name: sections; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE sections (
    section_id integer DEFAULT nextval('section_sequence'::regclass) NOT NULL,
    section_title character varying(255),
    section_is_enabled integer
);


--
-- TOC entry 199 (class 1259 OID 53241)
-- Name: session_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE session_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 200 (class 1259 OID 53243)
-- Name: student_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE student_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 201 (class 1259 OID 53245)
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
-- TOC entry 202 (class 1259 OID 53249)
-- Name: test_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE test_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 203 (class 1259 OID 53251)
-- Name: tests; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE tests (
    test_id integer DEFAULT nextval('test_sequence'::regclass) NOT NULL,
    test_number integer,
    test_code character varying(8),
    test_max_grade numeric DEFAULT 100 NOT NULL,
    test_curve numeric DEFAULT 0 NOT NULL,
    test_questions integer DEFAULT 0 NOT NULL,
    test_locked boolean DEFAULT false NOT NULL,
    test_text_fields integer DEFAULT 0 NOT NULL
);


--
-- TOC entry 204 (class 1259 OID 53263)
-- Name: user_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE user_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 205 (class 1259 OID 53265)
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
-- TOC entry 206 (class 1259 OID 53274)
-- Name: verify_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE verify_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 2248 (class 2606 OID 53277)
-- Name: grades_academic_integrity_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_academic_integrity
    ADD CONSTRAINT grades_academic_integrity_pkey PRIMARY KEY (gai_id);


--
-- TOC entry 2258 (class 2606 OID 53279)
-- Name: grades_labs_copy_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_tests
    ADD CONSTRAINT grades_labs_copy_pkey PRIMARY KEY (grade_test_id);


--
-- TOC entry 2251 (class 2606 OID 53281)
-- Name: grades_labs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_labs
    ADD CONSTRAINT grades_labs_pkey PRIMARY KEY (grade_lab_id);


--
-- TOC entry 2246 (class 2606 OID 53283)
-- Name: grades_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades
    ADD CONSTRAINT grades_pkey PRIMARY KEY (grade_id);


--
-- TOC entry 2254 (class 2606 OID 53285)
-- Name: grades_questions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_questions
    ADD CONSTRAINT grades_questions_pkey PRIMARY KEY (grade_question_id);


--
-- TOC entry 2260 (class 2606 OID 53287)
-- Name: homework_grading_sections_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY homework_grading_sections
    ADD CONSTRAINT homework_grading_sections_pkey PRIMARY KEY (hgs_id);


--
-- TOC entry 2262 (class 2606 OID 53289)
-- Name: labs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY labs
    ADD CONSTRAINT labs_pkey PRIMARY KEY (lab_id);


--
-- TOC entry 2266 (class 2606 OID 53433)
-- Name: late_days_pk; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY late_days
    ADD CONSTRAINT late_days_pk PRIMARY KEY (student_rcs, since_timestamp);


--
-- TOC entry 2241 (class 2606 OID 53293)
-- Name: pk_config; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY config
    ADD CONSTRAINT pk_config PRIMARY KEY (config_name);


--
-- TOC entry 2264 (class 2606 OID 53295)
-- Name: pkey_ex_id; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY late_day_exceptions
    ADD CONSTRAINT pkey_ex_id PRIMARY KEY (ex_id);


--
-- TOC entry 2269 (class 2606 OID 53297)
-- Name: questions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY questions
    ADD CONSTRAINT questions_pkey PRIMARY KEY (question_id);


--
-- TOC entry 2271 (class 2606 OID 53299)
-- Name: relationships_students_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY relationships_students
    ADD CONSTRAINT relationships_students_pkey PRIMARY KEY (relationship_student_id);


--
-- TOC entry 2275 (class 2606 OID 53301)
-- Name: relationships_users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY relationships_users
    ADD CONSTRAINT relationships_users_pkey PRIMARY KEY (relationship_user_id);


--
-- TOC entry 2277 (class 2606 OID 53303)
-- Name: rubrics_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY rubrics
    ADD CONSTRAINT rubrics_pkey PRIMARY KEY (rubric_id);


--
-- TOC entry 2279 (class 2606 OID 53305)
-- Name: sections_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY sections
    ADD CONSTRAINT sections_pkey PRIMARY KEY (section_id);


--
-- TOC entry 2281 (class 2606 OID 53307)
-- Name: students_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY students
    ADD CONSTRAINT students_pkey PRIMARY KEY (student_rcs);


--
-- TOC entry 2283 (class 2606 OID 53431)
-- Name: students_rcs_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY students
    ADD CONSTRAINT students_rcs_unique UNIQUE (student_rcs);


--
-- TOC entry 2285 (class 2606 OID 53309)
-- Name: tests_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY tests
    ADD CONSTRAINT tests_pkey PRIMARY KEY (test_id);


--
-- TOC entry 2287 (class 2606 OID 53311)
-- Name: users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_pkey PRIMARY KEY (user_id);


--
-- TOC entry 2249 (class 1259 OID 53312)
-- Name: fki_grades_labs_fkey; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fki_grades_labs_fkey ON grades_labs USING btree (lab_id);


--
-- TOC entry 2252 (class 1259 OID 53313)
-- Name: fki_grades_questions_fkey; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fki_grades_questions_fkey ON grades_questions USING btree (grade_id);


--
-- TOC entry 2242 (class 1259 OID 53314)
-- Name: fki_grades_rubric_fkey; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fki_grades_rubric_fkey ON grades USING btree (rubric_id);


--
-- TOC entry 2243 (class 1259 OID 53315)
-- Name: fki_grades_student_fkey; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fki_grades_student_fkey ON grades USING btree (student_rcs);


--
-- TOC entry 2255 (class 1259 OID 53316)
-- Name: fki_grades_tests_fkey; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fki_grades_tests_fkey ON grades_tests USING btree (test_id);


--
-- TOC entry 2256 (class 1259 OID 53317)
-- Name: fki_grades_tests_student_fkey; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fki_grades_tests_student_fkey ON grades_tests USING btree (student_rcs);


--
-- TOC entry 2244 (class 1259 OID 53318)
-- Name: fki_grades_user_fkey; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fki_grades_user_fkey ON grades USING btree (grade_user_id);


--
-- TOC entry 2267 (class 1259 OID 53319)
-- Name: fki_questions_fkey; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fki_questions_fkey ON questions USING btree (rubric_id);


--
-- TOC entry 2272 (class 1259 OID 53320)
-- Name: fki_relationships_users_section_fkey; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fki_relationships_users_section_fkey ON relationships_users USING btree (section_id);


--
-- TOC entry 2273 (class 1259 OID 53321)
-- Name: fki_relationships_users_user_fkey; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fki_relationships_users_user_fkey ON relationships_users USING btree (user_id);


--
-- TOC entry 2301 (class 2606 OID 53322)
-- Name: fkey_rubric_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY late_day_exceptions
    ADD CONSTRAINT fkey_rubric_id FOREIGN KEY (ex_rubric_id) REFERENCES rubrics(rubric_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2302 (class 2606 OID 53327)
-- Name: fkey_student_rcs; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY late_day_exceptions
    ADD CONSTRAINT fkey_student_rcs FOREIGN KEY (ex_student_rcs) REFERENCES students(student_rcs) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2291 (class 2606 OID 53332)
-- Name: grades_academic_integrity_rubric_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_academic_integrity
    ADD CONSTRAINT grades_academic_integrity_rubric_fkey FOREIGN KEY (rubric_id) REFERENCES rubrics(rubric_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2292 (class 2606 OID 53337)
-- Name: grades_academic_integrity_student; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_academic_integrity
    ADD CONSTRAINT grades_academic_integrity_student FOREIGN KEY (student_rcs) REFERENCES students(student_rcs) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2293 (class 2606 OID 53342)
-- Name: grades_labs_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_labs
    ADD CONSTRAINT grades_labs_fkey FOREIGN KEY (lab_id) REFERENCES labs(lab_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2294 (class 2606 OID 53347)
-- Name: grades_labs_student_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_labs
    ADD CONSTRAINT grades_labs_student_fkey FOREIGN KEY (student_rcs) REFERENCES students(student_rcs) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2295 (class 2606 OID 53352)
-- Name: grades_questions_grade_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_questions
    ADD CONSTRAINT grades_questions_grade_fkey FOREIGN KEY (grade_id) REFERENCES grades(grade_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2296 (class 2606 OID 53357)
-- Name: grades_questions_question_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_questions
    ADD CONSTRAINT grades_questions_question_fkey FOREIGN KEY (question_id) REFERENCES questions(question_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2288 (class 2606 OID 53362)
-- Name: grades_rubric_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades
    ADD CONSTRAINT grades_rubric_fkey FOREIGN KEY (rubric_id) REFERENCES rubrics(rubric_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2289 (class 2606 OID 53367)
-- Name: grades_student_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades
    ADD CONSTRAINT grades_student_fkey FOREIGN KEY (student_rcs) REFERENCES students(student_rcs) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2297 (class 2606 OID 53372)
-- Name: grades_tests_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_tests
    ADD CONSTRAINT grades_tests_fkey FOREIGN KEY (test_id) REFERENCES tests(test_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2298 (class 2606 OID 53377)
-- Name: grades_tests_students_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_tests
    ADD CONSTRAINT grades_tests_students_fkey FOREIGN KEY (student_rcs) REFERENCES students(student_rcs) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2290 (class 2606 OID 53382)
-- Name: grades_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades
    ADD CONSTRAINT grades_user_fkey FOREIGN KEY (grade_user_id) REFERENCES users(user_id) ON UPDATE SET NULL ON DELETE SET NULL;


--
-- TOC entry 2299 (class 2606 OID 53387)
-- Name: homework_grading_sections_rubric_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY homework_grading_sections
    ADD CONSTRAINT homework_grading_sections_rubric_id_fkey FOREIGN KEY (rubric_id) REFERENCES rubrics(rubric_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2300 (class 2606 OID 53392)
-- Name: homework_grading_sections_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY homework_grading_sections
    ADD CONSTRAINT homework_grading_sections_user_id_fkey FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2303 (class 2606 OID 53425)
-- Name: late_days_student_rcs_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY late_days
    ADD CONSTRAINT late_days_student_rcs_fkey FOREIGN KEY (student_rcs) REFERENCES students(student_rcs) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2304 (class 2606 OID 53397)
-- Name: questions_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY questions
    ADD CONSTRAINT questions_fkey FOREIGN KEY (rubric_id) REFERENCES rubrics(rubric_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2305 (class 2606 OID 53402)
-- Name: relationships_users_section_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY relationships_users
    ADD CONSTRAINT relationships_users_section_fkey FOREIGN KEY (section_id) REFERENCES sections(section_id);


--
-- TOC entry 2306 (class 2606 OID 53407)
-- Name: relationships_users_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY relationships_users
    ADD CONSTRAINT relationships_users_user_fkey FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2307 (class 2606 OID 53412)
-- Name: student_section_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY students
    ADD CONSTRAINT student_section_fkey FOREIGN KEY (student_section_id) REFERENCES sections(section_id);


-- Completed on 2015-09-04 23:46:50 EDT

--
-- PostgreSQL database dump complete
--

