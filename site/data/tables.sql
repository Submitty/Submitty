--
-- PostgreSQL database dump
--

-- Dumped from database version 9.3.11
-- Dumped by pg_dump version 9.4.0
-- Started on 2016-04-12 16:50:56 EDT

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
-- TOC entry 2152 (class 0 OID 0)
-- Dependencies: 196
-- Name: EXTENSION plpgsql; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';


SET search_path = public, pg_catalog;

SET default_with_oids = false;


--
-- TOC entry 171 (class 1259 OID 16395)
-- Name: grade_lab_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE grade_lab_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 172 (class 1259 OID 16397)
-- Name: grade_question_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE grade_question_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 173 (class 1259 OID 16399)
-- Name: grade_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE grade_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 174 (class 1259 OID 16401)
-- Name: grade_test_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE grade_test_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 175 (class 1259 OID 16403)
-- Name: grades; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE grades_rubrics (
    grade_id integer DEFAULT nextval('grade_sequence'::regclass) NOT NULL,
    rubric_id character varying NOT NULL,
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
-- TOC entry 176 (class 1259 OID 16418)
-- Name: grades_academic_integrity; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE grades_rubrics_academic_integrity (
    rubric_id character varying NOT NULL,
    student_id character varying(255) NOT NULL,
    penalty numeric(3,3) DEFAULT NULL::numeric

);


--
-- TOC entry 177 (class 1259 OID 16426)
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
-- TOC entry 178 (class 1259 OID 16433)
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
-- TOC entry 179 (class 1259 OID 16441)
-- Name: grades_others_grades_other_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE grades_others_grades_other_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 2153 (class 0 OID 0)
-- Dependencies: 179
-- Name: grades_others_grades_other_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE grades_others_grades_other_id_seq OWNED BY grades_others.grades_other_id;


--
-- TOC entry 180 (class 1259 OID 16443)
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
-- TOC entry 181 (class 1259 OID 16450)
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
-- TOC entry 195 (class 1259 OID 24856)
-- Name: groups; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE groups (
    group_number integer NOT NULL,
    group_name character varying(255) NOT NULL
);


--
-- TOC entry 183 (class 1259 OID 16466)
-- Name: labs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE labs (
    lab_number integer NOT NULL,
    lab_title character varying,
    lab_checkpoints integer DEFAULT 1 NOT NULL
);


--
-- TOC entry 184 (class 1259 OID 16475)
-- Name: late_day_exceptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE late_day_exceptions (
    rubric_id character varying NOT NULL,
    student_id character varying(255) NOT NULL,
    late_day_exceptions integer DEFAULT 0 NOT NULL
);


--
-- TOC entry 185 (class 1259 OID 16483)
-- Name: late_days; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE late_days (
    user_id character varying(255) NOT NULL,
    allowed_lates integer DEFAULT 0 NOT NULL,
    since_timestamp timestamp without time zone NOT NULL
);


--
-- TOC entry 186 (class 1259 OID 16490)
-- Name: other_grades; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE other_grades (
    other_id character varying NOT NULL,
    other_name character varying NOT NULL,
    other_due_date timestamp(6) without time zone NOT NULL,
    other_score numeric NOT NULL
);


--
-- TOC entry 187 (class 1259 OID 16498)
-- Name: question_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE question_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 188 (class 1259 OID 16500)
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
-- TOC entry 189 (class 1259 OID 16510)
-- Name: relationships_graders; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE relationships_graders (
    grader_id character varying(255) NOT NULL,
    section_number integer NOT NULL
);


--
-- TOC entry 190 (class 1259 OID 16518)
-- Name: rubrics; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE rubrics (
    rubric_id character varying NOT NULL,
    rubric_name character varying NOT NULL,
    rubric_due_date timestamp(6) without time zone NOT NULL,
    rubric_late_days integer DEFAULT (-1) NOT NULL,
    rubric_parts_sep integer DEFAULT 0 NOT NULL,
    rubric_parts_id character varying DEFAULT ''::character varying NOT NULL
);


--
-- TOC entry 182 (class 1259 OID 16460)
-- Name: rubrics_grading_sections; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE rubrics_grading_sections (
    rubric_id character varying NOT NULL,
    grader_id character varying(255) NOT NULL,
    grading_section_id integer NOT NULL
);


--
-- TOC entry 191 (class 1259 OID 16530)
-- Name: sections; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE sections (
    section_number integer NOT NULL,
    section_title character varying(255),
    section_is_enabled integer
);


--
-- TOC entry 192 (class 1259 OID 16544)
-- Name: test_sequence; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE test_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 193 (class 1259 OID 16546)
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
-- TOC entry 194 (class 1259 OID 16560)
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE users (
    user_id character varying(255) NOT NULL,
    user_firstname character varying(255),
    user_lastname character varying(255),
    user_email character varying(255),
    user_group integer DEFAULT 1 NOT NULL,
    user_course_section integer NOT NULL,
    user_rubric_section integer DEFAULT 1 NOT NULL
);


--
-- TOC entry 1940 (class 2604 OID 16571)
-- Name: grades_other_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_others ALTER COLUMN grades_other_id SET DEFAULT nextval('grades_others_grades_other_id_seq'::regclass);


--
-- TOC entry 1967 (class 2606 OID 24805)
-- Name: grades_rubrics_academic_integrity_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_rubrics_academic_integrity
    ADD CONSTRAINT grades_rubrics_academic_integrity_pkey PRIMARY KEY (rubric_id, student_id);


--
-- TOC entry 1984 (class 2606 OID 16576)
-- Name: grades_labs_copy_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_tests
    ADD CONSTRAINT grades_labs_copy_pkey PRIMARY KEY (grade_test_id);


--
-- TOC entry 1969 (class 2606 OID 16578)
-- Name: grades_labs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_labs
    ADD CONSTRAINT grades_labs_pkey PRIMARY KEY (grade_lab_id);


--
-- TOC entry 1971 (class 2606 OID 24715)
-- Name: grades_labs_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_labs
    ADD CONSTRAINT grades_labs_unique UNIQUE (lab_number, grade_lab_student_id);


--
-- TOC entry 1973 (class 2606 OID 24828)
-- Name: grades_other_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_others
    ADD CONSTRAINT grades_other_unique UNIQUE (other_id, grades_other_student_id);


--
-- TOC entry 1963 (class 2606 OID 16580)
-- Name: grades_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_rubrics
    ADD CONSTRAINT grades_rubrics_pkey PRIMARY KEY (grade_id);


--
-- TOC entry 1978 (class 2606 OID 16582)
-- Name: grades_questions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_questions
    ADD CONSTRAINT grades_questions_pkey PRIMARY KEY (grade_question_id);


--
-- TOC entry 1980 (class 2606 OID 24809)
-- Name: grades_questions_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_questions
    ADD CONSTRAINT grades_questions_unique UNIQUE (grade_id, grade_question_id);


--
-- TOC entry 1986 (class 2606 OID 24852)
-- Name: grades_tests_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_tests
    ADD CONSTRAINT grades_tests_unique UNIQUE (test_id, grade_test_student_id);


--
-- TOC entry 1965 (class 2606 OID 24755)
-- Name: grades_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_rubrics
    ADD CONSTRAINT grades_rubrics_unique UNIQUE (rubric_id, grade_student_id);


--
-- TOC entry 2014 (class 2606 OID 24860)
-- Name: groups_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY groups
    ADD CONSTRAINT groups_pkey PRIMARY KEY (group_number);


--
-- TOC entry 1990 (class 2606 OID 24654)
-- Name: labs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY labs
    ADD CONSTRAINT labs_pkey PRIMARY KEY (lab_number);


--
-- TOC entry 1992 (class 2606 OID 24803)
-- Name: late_day_exceptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY late_day_exceptions
    ADD CONSTRAINT late_day_exceptions_pkey PRIMARY KEY (rubric_id, student_id);


--
-- TOC entry 1994 (class 2606 OID 24785)
-- Name: late_days_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY late_days
    ADD CONSTRAINT late_days_pkey PRIMARY KEY (user_id, since_timestamp);


--
-- TOC entry 1996 (class 2606 OID 24811)
-- Name: other_grades_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY other_grades
    ADD CONSTRAINT other_grades_pkey PRIMARY KEY (other_id);


--
-- TOC entry 1975 (class 2606 OID 16596)
-- Name: pkey_grades_other_id; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_others
    ADD CONSTRAINT pkey_grades_other_id PRIMARY KEY (grades_other_id);


--
-- TOC entry 1999 (class 2606 OID 16594)
-- Name: questions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY questions
    ADD CONSTRAINT questions_pkey PRIMARY KEY (question_id);


--
-- TOC entry 2002 (class 2606 OID 24807)
-- Name: relationships_graders_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY relationships_graders
    ADD CONSTRAINT relationships_graders_pkey PRIMARY KEY (grader_id, section_number);


--
-- TOC entry 1988 (class 2606 OID 24855)
-- Name: rubrics_grading_sections_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY rubrics_grading_sections
    ADD CONSTRAINT rubrics_grading_sections_pkey PRIMARY KEY (rubric_id, grader_id, grading_section_id);


--
-- TOC entry 2004 (class 2606 OID 24717)
-- Name: rubrics_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY rubrics
    ADD CONSTRAINT rubrics_pkey PRIMARY KEY (rubric_id);


--
-- TOC entry 2006 (class 2606 OID 24588)
-- Name: sections_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY sections
    ADD CONSTRAINT sections_pkey PRIMARY KEY (section_number);


--
-- TOC entry 2008 (class 2606 OID 16614)
-- Name: tests_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY tests
    ADD CONSTRAINT tests_pkey PRIMARY KEY (test_id);


--
-- TOC entry 2010 (class 2606 OID 24830)
-- Name: tests_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY tests
    ADD CONSTRAINT tests_unique UNIQUE (test_type, test_number);


--
-- TOC entry 2012 (class 2606 OID 24609)
-- Name: users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_pkey PRIMARY KEY (user_id);


--
-- TOC entry 1976 (class 1259 OID 16620)
-- Name: fki_grades_questions_fkey; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fki_grades_questions_fkey ON grades_questions USING btree (grade_id);


--
-- TOC entry 1981 (class 1259 OID 16623)
-- Name: fki_grades_tests_fkey; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fki_grades_tests_fkey ON grades_tests USING btree (test_id);


--
-- TOC entry 1982 (class 1259 OID 24831)
-- Name: fki_grades_tests_student_fkey; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fki_grades_tests_student_fkey ON grades_tests USING btree (grade_test_student_id);


--
-- TOC entry 1997 (class 1259 OID 16626)
-- Name: fki_questions_fkey; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fki_questions_fkey ON questions USING btree (rubric_id);


--
-- TOC entry 2000 (class 1259 OID 16627)
-- Name: fki_relationships_users_section_fkey; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fki_relationships_users_section_fkey ON relationships_graders USING btree (section_number);


--
-- TOC entry 2018 (class 2606 OID 24702)
-- Name: grades_rubrics_academic_integerity_student_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_rubrics_academic_integrity
    ADD CONSTRAINT grades_rubrics_academic_integerity_student_fkey FOREIGN KEY (student_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2019 (class 2606 OID 24759)
-- Name: grades_academic_integrity_rubric_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_rubrics_academic_integrity
    ADD CONSTRAINT grades_rubrics_academic_integrity_rubric_fkey FOREIGN KEY (rubric_id) REFERENCES rubrics(rubric_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2016 (class 2606 OID 24676)
-- Name: grades_rubrics_grader_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_rubrics
    ADD CONSTRAINT grades_rubrics_grader_fkey FOREIGN KEY (grade_grader_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- TOC entry 2021 (class 2606 OID 24688)
-- Name: grades_labs_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_labs
    ADD CONSTRAINT grades_labs_fkey FOREIGN KEY (lab_number) REFERENCES labs(lab_number) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2020 (class 2606 OID 24683)
-- Name: grades_labs_grader_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_labs
    ADD CONSTRAINT grades_labs_grader_fkey FOREIGN KEY (grade_lab_grader_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- TOC entry 2022 (class 2606 OID 24693)
-- Name: grades_labs_student_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_labs
    ADD CONSTRAINT grades_labs_student_fkey FOREIGN KEY (grade_lab_student_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2025 (class 2606 OID 24822)
-- Name: grades_other_grader_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_others
    ADD CONSTRAINT grades_other_grader_fkey FOREIGN KEY (grades_other_grader_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- TOC entry 2023 (class 2606 OID 24812)
-- Name: grades_others_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_others
    ADD CONSTRAINT grades_others_id_fkey FOREIGN KEY (other_id) REFERENCES other_grades(other_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2024 (class 2606 OID 24817)
-- Name: grades_others_student_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_others
    ADD CONSTRAINT grades_others_student_fkey FOREIGN KEY (grades_other_student_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2026 (class 2606 OID 16674)
-- Name: grades_questions_grade_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_questions
    ADD CONSTRAINT grades_questions_grade_fkey FOREIGN KEY (grade_id) REFERENCES grades_rubrics(grade_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2027 (class 2606 OID 16679)
-- Name: grades_questions_question_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_questions
    ADD CONSTRAINT grades_questions_question_fkey FOREIGN KEY (question_id) REFERENCES questions(question_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2017 (class 2606 OID 24749)
-- Name: grades_rubric_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_rubrics
    ADD CONSTRAINT grades_rubric_fkey FOREIGN KEY (rubric_id) REFERENCES rubrics(rubric_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2015 (class 2606 OID 24671)
-- Name: grades_student_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_rubrics
    ADD CONSTRAINT grades_student_fkey FOREIGN KEY (grade_student_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2028 (class 2606 OID 16694)
-- Name: grades_tests_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_tests
    ADD CONSTRAINT grades_tests_fkey FOREIGN KEY (test_id) REFERENCES tests(test_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2030 (class 2606 OID 24846)
-- Name: grades_tests_grader_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_tests
    ADD CONSTRAINT grades_tests_grader_fkey FOREIGN KEY (grade_test_grader_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- TOC entry 2029 (class 2606 OID 24841)
-- Name: grades_tests_student_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY grades_tests
    ADD CONSTRAINT grades_tests_student_fkey FOREIGN KEY (grade_test_student_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2033 (class 2606 OID 24766)
-- Name: late_day_exceptions_rubric_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY late_day_exceptions
    ADD CONSTRAINT late_day_exceptions_rubric_fkey FOREIGN KEY (rubric_id) REFERENCES rubrics(rubric_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2034 (class 2606 OID 24771)
-- Name: late_day_exceptions_student_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY late_day_exceptions
    ADD CONSTRAINT late_day_exceptions_student_fkey FOREIGN KEY (student_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2035 (class 2606 OID 24620)
-- Name: relationships_sections_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY relationships_graders
    ADD CONSTRAINT relationships_sections_fkey FOREIGN KEY (section_number) REFERENCES sections(section_number) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2036 (class 2606 OID 24625)
-- Name: relationships_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY relationships_graders
    ADD CONSTRAINT relationships_user_fkey FOREIGN KEY (grader_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2031 (class 2606 OID 24737)
-- Name: rubrics_grading_sections_grader_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY rubrics_grading_sections
    ADD CONSTRAINT rubrics_grading_sections_grader_fkey FOREIGN KEY (grader_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- TOC entry 2032 (class 2606 OID 24742)
-- Name: rubrics_grading_sections_rubric_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY rubrics_grading_sections
    ADD CONSTRAINT rubrics_grading_sections_rubric_fkey FOREIGN KEY (rubric_id) REFERENCES rubrics(rubric_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2037 (class 2606 OID 24603)
-- Name: users_course_section_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_course_section_fkey FOREIGN KEY (user_course_section) REFERENCES sections(section_number) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2038 (class 2606 OID 24861)
-- Name: users_group_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_group_fkey FOREIGN KEY (user_group) REFERENCES groups(group_number) ON UPDATE RESTRICT ON DELETE RESTRICT;


-- Completed on 2016-04-12 16:50:58 EDT

--
-- PostgreSQL database dump complete
--

