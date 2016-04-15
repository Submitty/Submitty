--
-- PostgreSQL database dump
--

-- Dumped from database version 9.3.11
-- Dumped by pg_dump version 9.4.0
-- Started on 2016-04-15 17:28:37 EDT

SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;

--
-- TOC entry 196 (class 3079 OID 11756)
-- Name: plpgsql; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;


--
-- TOC entry 2153 (class 0 OID 0)
-- Dependencies: 196
-- Name: EXTENSION plpgsql; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';


SET search_path = public, pg_catalog;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- TOC entry 189 (class 1259 OID 26559)
-- Name: assignments; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE assignments (
    assignment_id character varying NOT NULL,
    assignment_name character varying NOT NULL,
    assignment_due_date timestamp(6) without time zone NOT NULL,
    assignment_late_days integer DEFAULT (-1) NOT NULL,
    assignment_parts_sep integer DEFAULT 0 NOT NULL,
    assignment_parts_id character varying DEFAULT ''::character varying NOT NULL
);


--
-- TOC entry 190 (class 1259 OID 26568)
-- Name: assignments_grading_sections; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE assignments_grading_sections (
    assignment_id character varying NOT NULL,
    grader_id character varying(255) NOT NULL,
    grading_section_id integer NOT NULL
);


--
-- TOC entry 195 (class 1259 OID 26774)
-- Name: assignments_parts; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE assignments_parts (
    assignment_id character varying NOT NULL,
    part_number integer NOT NULL,
    part_id character varying(255) NOT NULL,
    part_name character varying(255) NOT NULL
);


--
-- TOC entry 170 (class 1259 OID 26456)
-- Name: grade_lab_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE grade_lab_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 171 (class 1259 OID 26458)
-- Name: grade_question_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE grade_question_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 172 (class 1259 OID 26460)
-- Name: grade_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE grade_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 173 (class 1259 OID 26462)
-- Name: grade_test_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE grade_test_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 174 (class 1259 OID 26464)
-- Name: grades_assignments; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE grades_assignments (
    grade_id integer DEFAULT nextval('grade_sequence'::regclass) NOT NULL,
    assignment_id character varying NOT NULL,
    grade_student_id character varying(255) NOT NULL,
    grade_grader_id character varying(255),
    grade_finish_timestamp timestamp(6) without time zone,
    grade_comment character varying(10240),
    grade_days_late integer DEFAULT 0 NOT NULL,
    grade_is_regraded integer DEFAULT 0 NOT NULL,
    grade_submitted integer DEFAULT 0 NOT NULL,
    grade_status integer DEFAULT 0 NOT NULL,
    grade_parts_days_late character varying DEFAULT '0'::character varying NOT NULL,
    grade_parts_submitted character varying DEFAULT '0'::character varying NOT NULL,
    grade_parts_status character varying DEFAULT '0'::character varying NOT NULL,
    grade_active_assignment character varying DEFAULT '1'::character varying NOT NULL
);


--
-- TOC entry 175 (class 1259 OID 26479)
-- Name: grades_assignments_academic_integrity; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE grades_assignments_academic_integrity (
    assignment_id character varying NOT NULL,
    student_id character varying(255) NOT NULL,
    penalty numeric(3,3) DEFAULT NULL::numeric
);


--
-- TOC entry 176 (class 1259 OID 26486)
-- Name: grades_labs; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE grades_labs (
    grade_lab_id integer DEFAULT nextval('grade_lab_sequence'::regclass) NOT NULL,
    lab_number integer NOT NULL,
    grade_lab_student_id character varying(255) NOT NULL,
    grade_lab_grader_id character varying(255),
    grade_lab_checkpoint integer NOT NULL,
    grade_lab_value integer DEFAULT 0 NOT NULL,
    grade_finish_timestamp timestamp without time zone
);


--
-- TOC entry 177 (class 1259 OID 26494)
-- Name: grades_others; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE grades_others (
    grades_other_id integer NOT NULL,
    other_id character varying(255) NOT NULL,
    grades_other_student_id character varying NOT NULL,
    grades_other_grader_id character varying(255),
    grades_other_score numeric DEFAULT 0 NOT NULL,
    grades_other_text character varying DEFAULT ''::character varying NOT NULL
);


--
-- TOC entry 178 (class 1259 OID 26502)
-- Name: grades_others_grades_other_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE grades_others_grades_other_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 2154 (class 0 OID 0)
-- Dependencies: 178
-- Name: grades_others_grades_other_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE grades_others_grades_other_id_seq OWNED BY grades_others.grades_other_id;


--
-- TOC entry 179 (class 1259 OID 26504)
-- Name: grades_questions; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE grades_questions (
    grade_question_id integer DEFAULT nextval('grade_question_sequence'::regclass) NOT NULL,
    grade_id integer,
    question_id integer,
    grade_question_score real,
    grade_question_comment character varying(10240)
);


--
-- TOC entry 180 (class 1259 OID 26511)
-- Name: grades_tests; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE grades_tests (
    grade_test_id integer DEFAULT nextval('grade_test_sequence'::regclass) NOT NULL,
    test_id integer,
    grade_test_student_id character varying(255) NOT NULL,
    grade_test_grader_id character varying(255),
    grade_test_questions numeric[],
    grade_test_value numeric DEFAULT 0 NOT NULL,
    grade_test_text character varying[]
);


--
-- TOC entry 181 (class 1259 OID 26519)
-- Name: groups; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE groups (
    group_number integer NOT NULL,
    group_name character varying(255) NOT NULL
);


--
-- TOC entry 182 (class 1259 OID 26522)
-- Name: labs; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE labs (
    lab_number integer NOT NULL,
    lab_title character varying,
    lab_checkpoints integer DEFAULT 1 NOT NULL
);


--
-- TOC entry 183 (class 1259 OID 26529)
-- Name: late_day_exceptions; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE late_day_exceptions (
    assignment_id character varying NOT NULL,
    student_id character varying(255) NOT NULL,
    late_day_exceptions integer DEFAULT 0 NOT NULL
);


--
-- TOC entry 184 (class 1259 OID 26536)
-- Name: late_days; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE late_days (
    user_id character varying(255) NOT NULL,
    allowed_lates integer DEFAULT 0 NOT NULL,
    since_timestamp timestamp without time zone NOT NULL
);


--
-- TOC entry 185 (class 1259 OID 26540)
-- Name: other_grades; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE other_grades (
    other_id character varying NOT NULL,
    other_name character varying NOT NULL,
    other_due_date timestamp(6) without time zone NOT NULL,
    other_score numeric NOT NULL
);


--
-- TOC entry 186 (class 1259 OID 26546)
-- Name: question_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE question_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 187 (class 1259 OID 26548)
-- Name: questions; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE questions (
    question_id integer DEFAULT nextval('question_sequence'::regclass) NOT NULL,
    assignment_id integer NOT NULL,
    question_part_number integer NOT NULL,
    question_number integer NOT NULL,
    question_message character varying,
    question_grading_note character varying,
    question_total real NOT NULL,
    question_extra_credit integer DEFAULT 0,
    question_default character varying
);


--
-- TOC entry 188 (class 1259 OID 26556)
-- Name: relationships_graders; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE relationships_graders (
    grader_id character varying(255) NOT NULL,
    section_number integer NOT NULL
);


--
-- TOC entry 191 (class 1259 OID 26574)
-- Name: sections; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE sections (
    section_number integer NOT NULL,
    section_title character varying(255),
    section_is_enabled integer
);


--
-- TOC entry 192 (class 1259 OID 26577)
-- Name: test_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE test_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 193 (class 1259 OID 26579)
-- Name: tests; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE tests (
    test_id integer DEFAULT nextval('test_sequence'::regclass) NOT NULL,
    test_type character varying NOT NULL,
    test_number integer NOT NULL,
    test_max_grade numeric DEFAULT 100 NOT NULL,
    test_curve numeric DEFAULT 0 NOT NULL,
    test_questions integer DEFAULT 0 NOT NULL,
    test_locked boolean DEFAULT false NOT NULL,
    test_text_fields integer DEFAULT 0 NOT NULL
);


--
-- TOC entry 194 (class 1259 OID 26591)
-- Name: users; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE users (
    user_id character varying(255) NOT NULL,
    user_firstname character varying(255),
    user_lastname character varying(255),
    user_email character varying(255),
    user_group integer DEFAULT 1 NOT NULL,
    user_course_section integer NOT NULL,
    user_assignment_section integer DEFAULT 1 NOT NULL
);


--
-- TOC entry 1940 (class 2604 OID 26599)
-- Name: grades_other_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_others ALTER COLUMN grades_other_id SET DEFAULT nextval('grades_others_grades_other_id_seq'::regclass);


--
-- TOC entry 2004 (class 2606 OID 26637)
-- Name: assignments_grading_sections_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY assignments_grading_sections
    ADD CONSTRAINT assignments_grading_sections_pkey PRIMARY KEY (assignment_id, grader_id, grading_section_id);


--
-- TOC entry 2014 (class 2606 OID 26781)
-- Name: assignments_parts_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY assignments_parts
    ADD CONSTRAINT assignments_parts_pkey PRIMARY KEY (assignment_id, part_number);


--
-- TOC entry 2002 (class 2606 OID 26639)
-- Name: assignments_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY assignments
    ADD CONSTRAINT assignments_pkey PRIMARY KEY (assignment_id);


--
-- TOC entry 1965 (class 2606 OID 26601)
-- Name: grades_assignments_academic_integrity_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY grades_assignments_academic_integrity
    ADD CONSTRAINT grades_assignments_academic_integrity_pkey PRIMARY KEY (assignment_id, student_id);


--
-- TOC entry 1961 (class 2606 OID 26611)
-- Name: grades_assignments_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY grades_assignments
    ADD CONSTRAINT grades_assignments_pkey PRIMARY KEY (grade_id);


--
-- TOC entry 1963 (class 2606 OID 26619)
-- Name: grades_assignments_unique; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY grades_assignments
    ADD CONSTRAINT grades_assignments_unique UNIQUE (assignment_id, grade_student_id);


--
-- TOC entry 1982 (class 2606 OID 26603)
-- Name: grades_labs_copy_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY grades_tests
    ADD CONSTRAINT grades_labs_copy_pkey PRIMARY KEY (grade_test_id);


--
-- TOC entry 1967 (class 2606 OID 26605)
-- Name: grades_labs_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY grades_labs
    ADD CONSTRAINT grades_labs_pkey PRIMARY KEY (grade_lab_id);


--
-- TOC entry 1969 (class 2606 OID 26607)
-- Name: grades_labs_unique; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY grades_labs
    ADD CONSTRAINT grades_labs_unique UNIQUE (lab_number, grade_lab_student_id);


--
-- TOC entry 1971 (class 2606 OID 26609)
-- Name: grades_other_unique; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY grades_others
    ADD CONSTRAINT grades_other_unique UNIQUE (other_id, grades_other_student_id);


--
-- TOC entry 1976 (class 2606 OID 26613)
-- Name: grades_questions_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY grades_questions
    ADD CONSTRAINT grades_questions_pkey PRIMARY KEY (grade_question_id);


--
-- TOC entry 1978 (class 2606 OID 26615)
-- Name: grades_questions_unique; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY grades_questions
    ADD CONSTRAINT grades_questions_unique UNIQUE (grade_id, grade_question_id);


--
-- TOC entry 1984 (class 2606 OID 26617)
-- Name: grades_tests_unique; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY grades_tests
    ADD CONSTRAINT grades_tests_unique UNIQUE (test_id, grade_test_student_id);


--
-- TOC entry 1986 (class 2606 OID 26621)
-- Name: groups_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY groups
    ADD CONSTRAINT groups_pkey PRIMARY KEY (group_number);


--
-- TOC entry 1988 (class 2606 OID 26623)
-- Name: labs_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY labs
    ADD CONSTRAINT labs_pkey PRIMARY KEY (lab_number);


--
-- TOC entry 1990 (class 2606 OID 26625)
-- Name: late_day_exceptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY late_day_exceptions
    ADD CONSTRAINT late_day_exceptions_pkey PRIMARY KEY (assignment_id, student_id);


--
-- TOC entry 1992 (class 2606 OID 26627)
-- Name: late_days_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY late_days
    ADD CONSTRAINT late_days_pkey PRIMARY KEY (user_id, since_timestamp);


--
-- TOC entry 1994 (class 2606 OID 26629)
-- Name: other_grades_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY other_grades
    ADD CONSTRAINT other_grades_pkey PRIMARY KEY (other_id);


--
-- TOC entry 1973 (class 2606 OID 26631)
-- Name: pkey_grades_other_id; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY grades_others
    ADD CONSTRAINT pkey_grades_other_id PRIMARY KEY (grades_other_id);


--
-- TOC entry 1997 (class 2606 OID 26633)
-- Name: questions_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY questions
    ADD CONSTRAINT questions_pkey PRIMARY KEY (question_id);


--
-- TOC entry 2000 (class 2606 OID 26635)
-- Name: relationships_graders_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY relationships_graders
    ADD CONSTRAINT relationships_graders_pkey PRIMARY KEY (grader_id, section_number);


--
-- TOC entry 2006 (class 2606 OID 26641)
-- Name: sections_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY sections
    ADD CONSTRAINT sections_pkey PRIMARY KEY (section_number);


--
-- TOC entry 2008 (class 2606 OID 26643)
-- Name: tests_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY tests
    ADD CONSTRAINT tests_pkey PRIMARY KEY (test_id);


--
-- TOC entry 2010 (class 2606 OID 26645)
-- Name: tests_unique; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY tests
    ADD CONSTRAINT tests_unique UNIQUE (test_type, test_number);


--
-- TOC entry 2012 (class 2606 OID 26647)
-- Name: users_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_pkey PRIMARY KEY (user_id);


--
-- TOC entry 1974 (class 1259 OID 26648)
-- Name: fki_grades_questions_fkey; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX fki_grades_questions_fkey ON grades_questions USING btree (grade_id);


--
-- TOC entry 1979 (class 1259 OID 26649)
-- Name: fki_grades_tests_fkey; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX fki_grades_tests_fkey ON grades_tests USING btree (test_id);


--
-- TOC entry 1980 (class 1259 OID 26650)
-- Name: fki_grades_tests_student_fkey; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX fki_grades_tests_student_fkey ON grades_tests USING btree (grade_test_student_id);


--
-- TOC entry 1995 (class 1259 OID 26651)
-- Name: fki_questions_fkey; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX fki_questions_fkey ON questions USING btree (assignment_id);


--
-- TOC entry 1998 (class 1259 OID 26652)
-- Name: fki_relationships_users_section_fkey; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX fki_relationships_users_section_fkey ON relationships_graders USING btree (section_number);


--
-- TOC entry 2036 (class 2606 OID 26758)
-- Name: assignments_grading_sections_assignment_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY assignments_grading_sections
    ADD CONSTRAINT assignments_grading_sections_assignment_fkey FOREIGN KEY (assignment_id) REFERENCES assignments(assignment_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2035 (class 2606 OID 26753)
-- Name: assignments_grading_sections_grader_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY assignments_grading_sections
    ADD CONSTRAINT assignments_grading_sections_grader_fkey FOREIGN KEY (grader_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- TOC entry 2039 (class 2606 OID 26782)
-- Name: assignments_parts_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY assignments_parts
    ADD CONSTRAINT assignments_parts_fkey FOREIGN KEY (assignment_id) REFERENCES assignments(assignment_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2016 (class 2606 OID 26708)
-- Name: grades_assignment_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_assignments
    ADD CONSTRAINT grades_assignment_fkey FOREIGN KEY (assignment_id) REFERENCES assignments(assignment_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2018 (class 2606 OID 26653)
-- Name: grades_assignments_academic_integerity_student_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_assignments_academic_integrity
    ADD CONSTRAINT grades_assignments_academic_integerity_student_fkey FOREIGN KEY (student_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2019 (class 2606 OID 26658)
-- Name: grades_assignments_academic_integrity_assignment_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_assignments_academic_integrity
    ADD CONSTRAINT grades_assignments_academic_integrity_assignment_fkey FOREIGN KEY (assignment_id) REFERENCES assignments(assignment_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2015 (class 2606 OID 26663)
-- Name: grades_assignments_grader_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_assignments
    ADD CONSTRAINT grades_assignments_grader_fkey FOREIGN KEY (grade_grader_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- TOC entry 2020 (class 2606 OID 26668)
-- Name: grades_labs_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_labs
    ADD CONSTRAINT grades_labs_fkey FOREIGN KEY (lab_number) REFERENCES labs(lab_number) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2021 (class 2606 OID 26673)
-- Name: grades_labs_grader_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_labs
    ADD CONSTRAINT grades_labs_grader_fkey FOREIGN KEY (grade_lab_grader_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- TOC entry 2022 (class 2606 OID 26678)
-- Name: grades_labs_student_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_labs
    ADD CONSTRAINT grades_labs_student_fkey FOREIGN KEY (grade_lab_student_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2023 (class 2606 OID 26683)
-- Name: grades_other_grader_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_others
    ADD CONSTRAINT grades_other_grader_fkey FOREIGN KEY (grades_other_grader_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- TOC entry 2024 (class 2606 OID 26688)
-- Name: grades_others_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_others
    ADD CONSTRAINT grades_others_id_fkey FOREIGN KEY (other_id) REFERENCES other_grades(other_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2025 (class 2606 OID 26693)
-- Name: grades_others_student_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_others
    ADD CONSTRAINT grades_others_student_fkey FOREIGN KEY (grades_other_student_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2026 (class 2606 OID 26698)
-- Name: grades_questions_grade_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_questions
    ADD CONSTRAINT grades_questions_grade_fkey FOREIGN KEY (grade_id) REFERENCES grades_assignments(grade_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2027 (class 2606 OID 26703)
-- Name: grades_questions_question_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_questions
    ADD CONSTRAINT grades_questions_question_fkey FOREIGN KEY (question_id) REFERENCES questions(question_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2017 (class 2606 OID 26713)
-- Name: grades_student_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_assignments
    ADD CONSTRAINT grades_student_fkey FOREIGN KEY (grade_student_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2028 (class 2606 OID 26718)
-- Name: grades_tests_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_tests
    ADD CONSTRAINT grades_tests_fkey FOREIGN KEY (test_id) REFERENCES tests(test_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2029 (class 2606 OID 26723)
-- Name: grades_tests_grader_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_tests
    ADD CONSTRAINT grades_tests_grader_fkey FOREIGN KEY (grade_test_grader_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- TOC entry 2030 (class 2606 OID 26728)
-- Name: grades_tests_student_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_tests
    ADD CONSTRAINT grades_tests_student_fkey FOREIGN KEY (grade_test_student_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2031 (class 2606 OID 26733)
-- Name: late_day_exceptions_assignment_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY late_day_exceptions
    ADD CONSTRAINT late_day_exceptions_assignment_fkey FOREIGN KEY (assignment_id) REFERENCES assignments(assignment_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2032 (class 2606 OID 26738)
-- Name: late_day_exceptions_student_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY late_day_exceptions
    ADD CONSTRAINT late_day_exceptions_student_fkey FOREIGN KEY (student_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2033 (class 2606 OID 26743)
-- Name: relationships_sections_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY relationships_graders
    ADD CONSTRAINT relationships_sections_fkey FOREIGN KEY (section_number) REFERENCES sections(section_number) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2034 (class 2606 OID 26748)
-- Name: relationships_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY relationships_graders
    ADD CONSTRAINT relationships_user_fkey FOREIGN KEY (grader_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2037 (class 2606 OID 26763)
-- Name: users_course_section_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_course_section_fkey FOREIGN KEY (user_course_section) REFERENCES sections(section_number) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2038 (class 2606 OID 26768)
-- Name: users_group_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_group_fkey FOREIGN KEY (user_group) REFERENCES groups(group_number) ON UPDATE RESTRICT ON DELETE RESTRICT;


-- Completed on 2016-04-15 17:28:38 EDT

--
-- PostgreSQL database dump complete
--

