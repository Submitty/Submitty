--
-- PostgreSQL database dump
--

-- Dumped from database version 9.3.13
-- Dumped by pg_dump version 9.5.1

-- Started on 2016-07-14 16:19:06 EDT

SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET row_security = off;

--
-- TOC entry 1 (class 3079 OID 11756)
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
-- TOC entry 171 (class 1259 OID 17456)
-- Name: assignments; Type: TABLE; Schema: public; Owner: -
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
-- TOC entry 172 (class 1259 OID 17465)
-- Name: assignments_grading_sections; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE assignments_grading_sections (
    assignment_id character varying NOT NULL,
    grader_id character varying(255) NOT NULL,
    grading_section_id integer NOT NULL
);


--
-- TOC entry 173 (class 1259 OID 17471)
-- Name: assignments_parts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE assignments_parts (
    assignment_id character varying NOT NULL,
    part_number integer NOT NULL,
    part_id character varying(255) NOT NULL,
    part_name character varying(255) NOT NULL
);


--
-- TOC entry 174 (class 1259 OID 17477)
-- Name: grade_lab_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE grade_lab_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 175 (class 1259 OID 17479)
-- Name: grade_question_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE grade_question_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 176 (class 1259 OID 17481)
-- Name: grade_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE grade_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 177 (class 1259 OID 17483)
-- Name: grade_test_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE grade_test_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 178 (class 1259 OID 17485)
-- Name: grades_assignments; Type: TABLE; Schema: public; Owner: -
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
-- TOC entry 179 (class 1259 OID 17500)
-- Name: grades_assignments_academic_integrity; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE grades_assignments_academic_integrity (
    assignment_id character varying NOT NULL,
    student_id character varying(255) NOT NULL,
    penalty numeric(3,3) DEFAULT NULL::numeric
);


--
-- TOC entry 180 (class 1259 OID 17507)
-- Name: grades_labs; Type: TABLE; Schema: public; Owner: -
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
-- TOC entry 181 (class 1259 OID 17515)
-- Name: grades_others; Type: TABLE; Schema: public; Owner: -
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
-- TOC entry 182 (class 1259 OID 17523)
-- Name: grades_others_grades_other_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE grades_others_grades_other_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 2162 (class 0 OID 0)
-- Dependencies: 182
-- Name: grades_others_grades_other_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE grades_others_grades_other_id_seq OWNED BY grades_others.grades_other_id;


--
-- TOC entry 183 (class 1259 OID 17525)
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
-- TOC entry 184 (class 1259 OID 17532)
-- Name: grades_tests; Type: TABLE; Schema: public; Owner: -
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
-- TOC entry 185 (class 1259 OID 17540)
-- Name: groups; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE groups (
    group_number integer NOT NULL,
    group_name character varying(255) NOT NULL
);


--
-- TOC entry 186 (class 1259 OID 17543)
-- Name: labs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE labs (
    lab_number integer NOT NULL,
    lab_title character varying,
    lab_checkpoints integer DEFAULT 1 NOT NULL
);


--
-- TOC entry 187 (class 1259 OID 17550)
-- Name: late_day_exceptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE late_day_exceptions (
    assignment_id character varying NOT NULL,
    student_id character varying(255) NOT NULL,
    late_day_exceptions integer DEFAULT 0 NOT NULL
);


--
-- TOC entry 188 (class 1259 OID 17557)
-- Name: late_days; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE late_days (
    user_id character varying(255) NOT NULL,
    allowed_lates integer DEFAULT 0 NOT NULL,
    since_timestamp timestamp without time zone NOT NULL
);


--
-- TOC entry 189 (class 1259 OID 17561)
-- Name: other_grades; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE other_grades (
    other_id character varying NOT NULL,
    other_name character varying NOT NULL,
    other_due_date timestamp(6) without time zone NOT NULL,
    other_score numeric NOT NULL
);


--
-- TOC entry 190 (class 1259 OID 17567)
-- Name: question_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE question_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 191 (class 1259 OID 17569)
-- Name: questions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE questions (
    question_id integer DEFAULT nextval('question_sequence'::regclass) NOT NULL,
    question_part_number integer NOT NULL,
    question_number integer NOT NULL,
    question_message character varying,
    question_grading_note character varying,
    question_total real NOT NULL,
    question_extra_credit integer DEFAULT 0,
    question_default character varying,
    assignment_id character varying NOT NULL
);


--
-- TOC entry 192 (class 1259 OID 17577)
-- Name: relationships_graders; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE relationships_graders (
    grader_id character varying(255) NOT NULL,
    section_number integer NOT NULL
);


--
-- TOC entry 193 (class 1259 OID 17580)
-- Name: sections; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE sections (
    section_number integer NOT NULL,
    section_title character varying(255),
    section_is_enabled integer
);


--
-- TOC entry 197 (class 1259 OID 17802)
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE sessions (
    session_id character varying(255) NOT NULL,
    user_id character varying(255) NOT NULL,
    csrf_token character varying(255) NOT NULL,
    session_expires timestamp without time zone NOT NULL
);


--
-- TOC entry 194 (class 1259 OID 17583)
-- Name: test_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE test_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 195 (class 1259 OID 17585)
-- Name: tests; Type: TABLE; Schema: public; Owner: -
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
-- TOC entry 196 (class 1259 OID 17597)
-- Name: users; Type: TABLE; Schema: public; Owner: -
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
-- TOC entry 1948 (class 2604 OID 17605)
-- Name: grades_other_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_others ALTER COLUMN grades_other_id SET DEFAULT nextval('grades_others_grades_other_id_seq'::regclass);


--
-- TOC entry 1968 (class 2606 OID 17607)
-- Name: assignments_grading_sections_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY assignments_grading_sections
    ADD CONSTRAINT assignments_grading_sections_pkey PRIMARY KEY (assignment_id, grader_id, grading_section_id);


--
-- TOC entry 1970 (class 2606 OID 17609)
-- Name: assignments_parts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY assignments_parts
    ADD CONSTRAINT assignments_parts_pkey PRIMARY KEY (assignment_id, part_number);


--
-- TOC entry 1966 (class 2606 OID 17611)
-- Name: assignments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY assignments
    ADD CONSTRAINT assignments_pkey PRIMARY KEY (assignment_id);


--
-- TOC entry 1976 (class 2606 OID 17613)
-- Name: grades_assignments_academic_integrity_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_assignments_academic_integrity
    ADD CONSTRAINT grades_assignments_academic_integrity_pkey PRIMARY KEY (assignment_id, student_id);


--
-- TOC entry 1972 (class 2606 OID 17615)
-- Name: grades_assignments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_assignments
    ADD CONSTRAINT grades_assignments_pkey PRIMARY KEY (grade_id);


--
-- TOC entry 1974 (class 2606 OID 17617)
-- Name: grades_assignments_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_assignments
    ADD CONSTRAINT grades_assignments_unique UNIQUE (assignment_id, grade_student_id);


--
-- TOC entry 1993 (class 2606 OID 17619)
-- Name: grades_labs_copy_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_tests
    ADD CONSTRAINT grades_labs_copy_pkey PRIMARY KEY (grade_test_id);


--
-- TOC entry 1978 (class 2606 OID 17621)
-- Name: grades_labs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_labs
    ADD CONSTRAINT grades_labs_pkey PRIMARY KEY (grade_lab_id);


--
-- TOC entry 1980 (class 2606 OID 17623)
-- Name: grades_labs_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_labs
    ADD CONSTRAINT grades_labs_unique UNIQUE (lab_number, grade_lab_student_id);


--
-- TOC entry 1982 (class 2606 OID 17625)
-- Name: grades_other_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_others
    ADD CONSTRAINT grades_other_unique UNIQUE (other_id, grades_other_student_id);


--
-- TOC entry 1987 (class 2606 OID 17627)
-- Name: grades_questions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_questions
    ADD CONSTRAINT grades_questions_pkey PRIMARY KEY (grade_question_id);


--
-- TOC entry 1989 (class 2606 OID 17629)
-- Name: grades_questions_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_questions
    ADD CONSTRAINT grades_questions_unique UNIQUE (grade_id, grade_question_id);


--
-- TOC entry 1995 (class 2606 OID 17631)
-- Name: grades_tests_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_tests
    ADD CONSTRAINT grades_tests_unique UNIQUE (test_id, grade_test_student_id);


--
-- TOC entry 1997 (class 2606 OID 17633)
-- Name: groups_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY groups
    ADD CONSTRAINT groups_pkey PRIMARY KEY (group_number);


--
-- TOC entry 1999 (class 2606 OID 17635)
-- Name: labs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY labs
    ADD CONSTRAINT labs_pkey PRIMARY KEY (lab_number);


--
-- TOC entry 2001 (class 2606 OID 17637)
-- Name: late_day_exceptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY late_day_exceptions
    ADD CONSTRAINT late_day_exceptions_pkey PRIMARY KEY (assignment_id, student_id);


--
-- TOC entry 2003 (class 2606 OID 17639)
-- Name: late_days_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY late_days
    ADD CONSTRAINT late_days_pkey PRIMARY KEY (user_id, since_timestamp);


--
-- TOC entry 2005 (class 2606 OID 17641)
-- Name: other_grades_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY other_grades
    ADD CONSTRAINT other_grades_pkey PRIMARY KEY (other_id);


--
-- TOC entry 1984 (class 2606 OID 17643)
-- Name: pkey_grades_other_id; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_others
    ADD CONSTRAINT pkey_grades_other_id PRIMARY KEY (grades_other_id);


--
-- TOC entry 2007 (class 2606 OID 17645)
-- Name: questions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY questions
    ADD CONSTRAINT questions_pkey PRIMARY KEY (question_id);


--
-- TOC entry 2010 (class 2606 OID 17647)
-- Name: relationships_graders_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY relationships_graders
    ADD CONSTRAINT relationships_graders_pkey PRIMARY KEY (grader_id, section_number);


--
-- TOC entry 2012 (class 2606 OID 17649)
-- Name: sections_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY sections
    ADD CONSTRAINT sections_pkey PRIMARY KEY (section_number);


--
-- TOC entry 2020 (class 2606 OID 17809)
-- Name: sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (session_id);


--
-- TOC entry 2014 (class 2606 OID 17651)
-- Name: tests_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY tests
    ADD CONSTRAINT tests_pkey PRIMARY KEY (test_id);


--
-- TOC entry 2016 (class 2606 OID 17653)
-- Name: tests_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY tests
    ADD CONSTRAINT tests_unique UNIQUE (test_type, test_number);


--
-- TOC entry 2018 (class 2606 OID 17655)
-- Name: users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_pkey PRIMARY KEY (user_id);


--
-- TOC entry 1985 (class 1259 OID 17656)
-- Name: fki_grades_questions_fkey; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fki_grades_questions_fkey ON grades_questions USING btree (grade_id);


--
-- TOC entry 1990 (class 1259 OID 17657)
-- Name: fki_grades_tests_fkey; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fki_grades_tests_fkey ON grades_tests USING btree (test_id);


--
-- TOC entry 1991 (class 1259 OID 17658)
-- Name: fki_grades_tests_student_fkey; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fki_grades_tests_student_fkey ON grades_tests USING btree (grade_test_student_id);


--
-- TOC entry 2008 (class 1259 OID 17659)
-- Name: fki_relationships_users_section_fkey; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fki_relationships_users_section_fkey ON relationships_graders USING btree (section_number);


--
-- TOC entry 2021 (class 2606 OID 17660)
-- Name: assignments_grading_sections_assignment_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY assignments_grading_sections
    ADD CONSTRAINT assignments_grading_sections_assignment_fkey FOREIGN KEY (assignment_id) REFERENCES assignments(assignment_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2022 (class 2606 OID 17665)
-- Name: assignments_grading_sections_grader_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY assignments_grading_sections
    ADD CONSTRAINT assignments_grading_sections_grader_fkey FOREIGN KEY (grader_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- TOC entry 2023 (class 2606 OID 17670)
-- Name: assignments_parts_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY assignments_parts
    ADD CONSTRAINT assignments_parts_fkey FOREIGN KEY (assignment_id) REFERENCES assignments(assignment_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2024 (class 2606 OID 17675)
-- Name: grades_assignment_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_assignments
    ADD CONSTRAINT grades_assignment_fkey FOREIGN KEY (assignment_id) REFERENCES assignments(assignment_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2027 (class 2606 OID 17680)
-- Name: grades_assignments_academic_integerity_student_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_assignments_academic_integrity
    ADD CONSTRAINT grades_assignments_academic_integerity_student_fkey FOREIGN KEY (student_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2028 (class 2606 OID 17685)
-- Name: grades_assignments_academic_integrity_assignment_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_assignments_academic_integrity
    ADD CONSTRAINT grades_assignments_academic_integrity_assignment_fkey FOREIGN KEY (assignment_id) REFERENCES assignments(assignment_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2025 (class 2606 OID 17690)
-- Name: grades_assignments_grader_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_assignments
    ADD CONSTRAINT grades_assignments_grader_fkey FOREIGN KEY (grade_grader_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- TOC entry 2029 (class 2606 OID 17695)
-- Name: grades_labs_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_labs
    ADD CONSTRAINT grades_labs_fkey FOREIGN KEY (lab_number) REFERENCES labs(lab_number) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2030 (class 2606 OID 17700)
-- Name: grades_labs_grader_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_labs
    ADD CONSTRAINT grades_labs_grader_fkey FOREIGN KEY (grade_lab_grader_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- TOC entry 2031 (class 2606 OID 17705)
-- Name: grades_labs_student_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_labs
    ADD CONSTRAINT grades_labs_student_fkey FOREIGN KEY (grade_lab_student_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2032 (class 2606 OID 17710)
-- Name: grades_other_grader_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_others
    ADD CONSTRAINT grades_other_grader_fkey FOREIGN KEY (grades_other_grader_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- TOC entry 2033 (class 2606 OID 17715)
-- Name: grades_others_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_others
    ADD CONSTRAINT grades_others_id_fkey FOREIGN KEY (other_id) REFERENCES other_grades(other_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2034 (class 2606 OID 17720)
-- Name: grades_others_student_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_others
    ADD CONSTRAINT grades_others_student_fkey FOREIGN KEY (grades_other_student_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2035 (class 2606 OID 17725)
-- Name: grades_questions_grade_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_questions
    ADD CONSTRAINT grades_questions_grade_fkey FOREIGN KEY (grade_id) REFERENCES grades_assignments(grade_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2036 (class 2606 OID 17730)
-- Name: grades_questions_question_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_questions
    ADD CONSTRAINT grades_questions_question_fkey FOREIGN KEY (question_id) REFERENCES questions(question_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2026 (class 2606 OID 17735)
-- Name: grades_student_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_assignments
    ADD CONSTRAINT grades_student_fkey FOREIGN KEY (grade_student_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2037 (class 2606 OID 17740)
-- Name: grades_tests_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_tests
    ADD CONSTRAINT grades_tests_fkey FOREIGN KEY (test_id) REFERENCES tests(test_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2038 (class 2606 OID 17745)
-- Name: grades_tests_grader_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_tests
    ADD CONSTRAINT grades_tests_grader_fkey FOREIGN KEY (grade_test_grader_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- TOC entry 2039 (class 2606 OID 17750)
-- Name: grades_tests_student_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_tests
    ADD CONSTRAINT grades_tests_student_fkey FOREIGN KEY (grade_test_student_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2040 (class 2606 OID 17755)
-- Name: late_day_exceptions_assignment_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY late_day_exceptions
    ADD CONSTRAINT late_day_exceptions_assignment_fkey FOREIGN KEY (assignment_id) REFERENCES assignments(assignment_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2041 (class 2606 OID 17760)
-- Name: late_day_exceptions_student_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY late_day_exceptions
    ADD CONSTRAINT late_day_exceptions_student_fkey FOREIGN KEY (student_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2042 (class 2606 OID 17765)
-- Name: questions_assignment_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY questions
    ADD CONSTRAINT questions_assignment_fkey FOREIGN KEY (assignment_id) REFERENCES assignments(assignment_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2043 (class 2606 OID 17770)
-- Name: relationships_sections_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY relationships_graders
    ADD CONSTRAINT relationships_sections_fkey FOREIGN KEY (section_number) REFERENCES sections(section_number) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2044 (class 2606 OID 17775)
-- Name: relationships_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY relationships_graders
    ADD CONSTRAINT relationships_user_fkey FOREIGN KEY (grader_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2047 (class 2606 OID 17810)
-- Name: sessions_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY sessions
    ADD CONSTRAINT sessions_fkey FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2045 (class 2606 OID 17780)
-- Name: users_course_section_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_course_section_fkey FOREIGN KEY (user_course_section) REFERENCES sections(section_number) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2046 (class 2606 OID 17785)
-- Name: users_group_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_group_fkey FOREIGN KEY (user_group) REFERENCES groups(group_number) ON UPDATE RESTRICT ON DELETE RESTRICT;


-- Completed on 2016-07-14 16:19:07 EDT

--
-- PostgreSQL database dump complete
--

