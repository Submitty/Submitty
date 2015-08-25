--
-- PostgreSQL database dump
--

SET search_path = public, pg_catalog;

--
-- TOC entry 170 (class 1259 OID 18156)
-- Name: grade_lab_sequence; Type: SEQUENCE; Schema: public; Owner: hsdbu
--

CREATE SEQUENCE grade_lab_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;



--
-- TOC entry 171 (class 1259 OID 18158)
-- Name: grade_question_sequence; Type: SEQUENCE; Schema: public; Owner: hsdbu
--

CREATE SEQUENCE grade_question_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;



--
-- TOC entry 172 (class 1259 OID 18160)
-- Name: grade_sequence; Type: SEQUENCE; Schema: public; Owner: hsdbu
--

CREATE SEQUENCE grade_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;



--
-- TOC entry 173 (class 1259 OID 18162)
-- Name: grade_test_sequence; Type: SEQUENCE; Schema: public; Owner: hsdbu
--

CREATE SEQUENCE grade_test_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;



SET default_tablespace = '';

SET default_with_oids = false;

--
-- TOC entry 174 (class 1259 OID 18164)
-- Name: grades; Type: TABLE; Schema: public; Owner: hsdbu; Tablespace: 
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
    student_rcs character varying
);


--
-- TOC entry 175 (class 1259 OID 18171)
-- Name: grades_academic_integrity_seq; Type: SEQUENCE; Schema: public; Owner: hsdbu
--

CREATE SEQUENCE grades_academic_integrity_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;



--
-- TOC entry 176 (class 1259 OID 18173)
-- Name: grades_academic_integrity; Type: TABLE; Schema: public; Owner: hsdbu; Tablespace: 
--

CREATE TABLE grades_academic_integrity (
    gai_id integer DEFAULT nextval('grades_academic_integrity_seq'::regclass) NOT NULL,
    student_id integer,
    student_rcs character varying,
    rubric_id integer,
    penalty numeric(3,3) DEFAULT NULL::numeric
);



--
-- TOC entry 177 (class 1259 OID 18181)
-- Name: grades_labs; Type: TABLE; Schema: public; Owner: hsdbu; Tablespace: 
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
-- TOC entry 178 (class 1259 OID 18188)
-- Name: grades_questions; Type: TABLE; Schema: public; Owner: hsdbu; Tablespace: 
--

CREATE TABLE grades_questions (
    grade_question_id integer DEFAULT nextval('grade_question_sequence'::regclass) NOT NULL,
    grade_id integer,
    question_id integer,
    grade_question_score real,
    grade_question_comment character varying(10240)
);



--
-- TOC entry 179 (class 1259 OID 18195)
-- Name: grades_tests; Type: TABLE; Schema: public; Owner: hsdbu; Tablespace: 
--

CREATE TABLE grades_tests (
    grade_test_id integer DEFAULT nextval('grade_test_sequence'::regclass) NOT NULL,
    test_id integer,
    student_id integer,
    grade_test_user_id integer,
    grade_test_value character varying(16),
    student_rcs character varying
);

--
-- TOC entry 180 (class 1259 OID 18202)
-- Name: lab_sequence; Type: SEQUENCE; Schema: public; Owner: hsdbu
--

CREATE SEQUENCE lab_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;



--
-- TOC entry 181 (class 1259 OID 18204)
-- Name: labs; Type: TABLE; Schema: public; Owner: hsdbu; Tablespace: 
--

CREATE TABLE labs (
    lab_id integer DEFAULT nextval('lab_sequence'::regclass) NOT NULL,
    lab_number integer,
    lab_title character varying,
    lab_checkpoints character varying,
    lab_code character varying(8)
);


CREATE SEQUENCE late_day_exceptions_seq
START WITH 1
INCREMENT BY 1
NO MINVALUE
NO MAXVALUE
CACHE 1;

CREATE TABLE late_day_exceptions (
  ex_id integer DEFAULT nextval('late_day_exceptions_seq'::regclass) NOT NULL,
  ex_student_rcs character varying NOT NULL,
  ex_rubric_id integer NOT NULL,
  ex_late_days integer NOT NULL default 0
);


--
-- TOC entry 182 (class 1259 OID 18211)
-- Name: question_sequence; Type: SEQUENCE; Schema: public; Owner: hsdbu
--

CREATE SEQUENCE question_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;



--
-- TOC entry 183 (class 1259 OID 18213)
-- Name: questions; Type: TABLE; Schema: public; Owner: hsdbu; Tablespace: 
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
-- TOC entry 184 (class 1259 OID 18221)
-- Name: relationship_student_sequence; Type: SEQUENCE; Schema: public; Owner: hsdbu
--

CREATE SEQUENCE relationship_student_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;



--
-- TOC entry 185 (class 1259 OID 18223)
-- Name: relationship_user_sequence; Type: SEQUENCE; Schema: public; Owner: hsdbu
--

CREATE SEQUENCE relationship_user_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;



--
-- TOC entry 186 (class 1259 OID 18225)
-- Name: relationships_students; Type: TABLE; Schema: public; Owner: hsdbu; Tablespace: 
--

CREATE TABLE relationships_students (
    relationship_student_id integer DEFAULT nextval('relationship_student_sequence'::regclass) NOT NULL,
    student_id integer,
    section_id integer,
    student_rcs character varying
);



--
-- TOC entry 187 (class 1259 OID 18232)
-- Name: relationships_users; Type: TABLE; Schema: public; Owner: hsdbu; Tablespace: 
--

CREATE TABLE relationships_users (
    relationship_user_id integer DEFAULT nextval('relationship_user_sequence'::regclass) NOT NULL,
    user_id integer,
    section_id integer
);



--
-- TOC entry 188 (class 1259 OID 18236)
-- Name: reset_sequence; Type: SEQUENCE; Schema: public; Owner: hsdbu
--

CREATE SEQUENCE reset_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;



--
-- TOC entry 189 (class 1259 OID 18238)
-- Name: resets; Type: TABLE; Schema: public; Owner: hsdbu; Tablespace: 
--

CREATE TABLE resets (
    reset_id integer DEFAULT nextval('reset_sequence'::regclass) NOT NULL,
    user_id integer,
    reset_secret character varying(255),
    reset_issue_timestamp timestamp(6) without time zone
);



--
-- TOC entry 190 (class 1259 OID 18242)
-- Name: rubric_sequence; Type: SEQUENCE; Schema: public; Owner: hsdbu
--

CREATE SEQUENCE rubric_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;



--
-- TOC entry 191 (class 1259 OID 18244)
-- Name: rubrics; Type: TABLE; Schema: public; Owner: hsdbu; Tablespace: 
--

CREATE TABLE rubrics (
    rubric_id integer DEFAULT nextval('rubric_sequence'::regclass) NOT NULL,
    rubric_number integer,
    rubric_due_date timestamp(6) without time zone,
    rubric_code character varying(8),
    rubric_parts_sep boolean DEFAULT false NOT NULL
);



--
-- TOC entry 192 (class 1259 OID 18249)
-- Name: section_sequence; Type: SEQUENCE; Schema: public; Owner: hsdbu
--

CREATE SEQUENCE section_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;



--
-- TOC entry 193 (class 1259 OID 18251)
-- Name: sections; Type: TABLE; Schema: public; Owner: hsdbu; Tablespace: 
--

CREATE TABLE sections (
    section_id integer DEFAULT nextval('section_sequence'::regclass) NOT NULL,
    section_title character varying(255),
    section_is_enabled integer
);



--
-- TOC entry 194 (class 1259 OID 18255)
-- Name: session_sequence; Type: SEQUENCE; Schema: public; Owner: hsdbu
--

CREATE SEQUENCE session_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;



--
-- TOC entry 195 (class 1259 OID 18257)
-- Name: sessions; Type: TABLE; Schema: public; Owner: hsdbu; Tablespace: 
--

CREATE TABLE sessions (
    session_id integer DEFAULT nextval('session_sequence'::regclass) NOT NULL,
    user_id integer,
    session_secret character varying(255),
    session_login_timestamp timestamp(6) without time zone,
    session_activity_timestamp timestamp(6) without time zone,
    session_logout_timestamp timestamp(6) without time zone,
    session_ip_address character varying(255),
    session_is_valid integer
);



--
-- TOC entry 196 (class 1259 OID 18264)
-- Name: student_sequence; Type: SEQUENCE; Schema: public; Owner: hsdbu
--

CREATE SEQUENCE student_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;



--
-- TOC entry 197 (class 1259 OID 18266)
-- Name: students; Type: TABLE; Schema: public; Owner: hsdbu; Tablespace: 
--

CREATE TABLE students (
    student_id integer DEFAULT nextval('student_sequence'::regclass) NOT NULL,
    student_rcs character varying(255) NOT NULL,
    student_late_warning integer,
    student_allowed_lates integer,
    student_last_name character varying(64),
    student_first_name character varying(64),
    student_experience integer,
    student_section_id integer,
    student_grading_id integer
);



--
-- TOC entry 198 (class 1259 OID 18270)
-- Name: test_sequence; Type: SEQUENCE; Schema: public; Owner: hsdbu
--

CREATE SEQUENCE test_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;



--
-- TOC entry 199 (class 1259 OID 18272)
-- Name: tests; Type: TABLE; Schema: public; Owner: hsdbu; Tablespace: 
--

CREATE TABLE tests (
    test_id integer DEFAULT nextval('test_sequence'::regclass) NOT NULL,
    test_number integer,
    test_code character varying(8),
    test_max_grade numeric DEFAULT 100 NOT NULL,
    test_curve numeric DEFAULT 0 NOT NULL
);



--
-- TOC entry 200 (class 1259 OID 18281)
-- Name: user_sequence; Type: SEQUENCE; Schema: public; Owner: hsdbu
--

CREATE SEQUENCE user_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;



--
-- TOC entry 201 (class 1259 OID 18283)
-- Name: users; Type: TABLE; Schema: public; Owner: hsdbu; Tablespace: 
--

CREATE TABLE users (
    user_id integer DEFAULT nextval('user_sequence'::regclass) NOT NULL,
    user_firstname character varying(255),
    user_lastname character varying(255),
    user_rcs character varying(255),
    user_email character varying(255),
    user_password_salt character varying(255),
    user_password_salted_hash character varying(255),
    user_creation_timestamp timestamp(6) without time zone,
    user_login_attempts integer,
    user_is_administrator integer,
    user_is_verified integer,
    user_is_locked integer
);



--
-- TOC entry 202 (class 1259 OID 18290)
-- Name: verify_sequence; Type: SEQUENCE; Schema: public; Owner: hsdbu
--

CREATE SEQUENCE verify_sequence
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;



--
-- TOC entry 203 (class 1259 OID 18292)
-- Name: verifies; Type: TABLE; Schema: public; Owner: hsdbu; Tablespace: 
--

CREATE TABLE verifies (
    verify_id integer DEFAULT nextval('verify_sequence'::regclass) NOT NULL,
    user_id integer,
    verify_secret character varying(255),
    verify_issue_timestamp timestamp(6) without time zone
);



--
-- TOC entry 2224 (class 2606 OID 18297)
-- Name: grades_academic_integrity_pkey; Type: CONSTRAINT; Schema: public; Owner: hsdbu; Tablespace: 
--

ALTER TABLE ONLY grades_academic_integrity
    ADD CONSTRAINT grades_academic_integrity_pkey PRIMARY KEY (gai_id);


--
-- TOC entry 2234 (class 2606 OID 18299)
-- Name: grades_labs_copy_pkey; Type: CONSTRAINT; Schema: public; Owner: hsdbu; Tablespace: 
--

ALTER TABLE ONLY grades_tests
    ADD CONSTRAINT grades_labs_copy_pkey PRIMARY KEY (grade_test_id);


--
-- TOC entry 2227 (class 2606 OID 18301)
-- Name: grades_labs_pkey; Type: CONSTRAINT; Schema: public; Owner: hsdbu; Tablespace: 
--

ALTER TABLE ONLY grades_labs
    ADD CONSTRAINT grades_labs_pkey PRIMARY KEY (grade_lab_id);


--
-- TOC entry 2222 (class 2606 OID 18303)
-- Name: grades_pkey; Type: CONSTRAINT; Schema: public; Owner: hsdbu; Tablespace: 
--

ALTER TABLE ONLY grades
    ADD CONSTRAINT grades_pkey PRIMARY KEY (grade_id);


--
-- TOC entry 2230 (class 2606 OID 18305)
-- Name: grades_questions_pkey; Type: CONSTRAINT; Schema: public; Owner: hsdbu; Tablespace: 
--

ALTER TABLE ONLY grades_questions
    ADD CONSTRAINT grades_questions_pkey PRIMARY KEY (grade_question_id);


--
-- TOC entry 2236 (class 2606 OID 18307)
-- Name: labs_pkey; Type: CONSTRAINT; Schema: public; Owner: hsdbu; Tablespace: 
--

ALTER TABLE ONLY labs
    ADD CONSTRAINT labs_pkey PRIMARY KEY (lab_id);


--
-- TOC entry 2239 (class 2606 OID 18309)
-- Name: questions_pkey; Type: CONSTRAINT; Schema: public; Owner: hsdbu; Tablespace: 
--

ALTER TABLE ONLY questions
    ADD CONSTRAINT questions_pkey PRIMARY KEY (question_id);


--
-- TOC entry 2241 (class 2606 OID 18311)
-- Name: relationships_students_pkey; Type: CONSTRAINT; Schema: public; Owner: hsdbu; Tablespace: 
--

ALTER TABLE ONLY relationships_students
    ADD CONSTRAINT relationships_students_pkey PRIMARY KEY (relationship_student_id);


--
-- TOC entry 2245 (class 2606 OID 18313)
-- Name: relationships_users_pkey; Type: CONSTRAINT; Schema: public; Owner: hsdbu; Tablespace: 
--

ALTER TABLE ONLY relationships_users
    ADD CONSTRAINT relationships_users_pkey PRIMARY KEY (relationship_user_id);


--
-- TOC entry 2247 (class 2606 OID 18315)
-- Name: resets_pkey; Type: CONSTRAINT; Schema: public; Owner: hsdbu; Tablespace: 
--

ALTER TABLE ONLY resets
    ADD CONSTRAINT resets_pkey PRIMARY KEY (reset_id);


--
-- TOC entry 2249 (class 2606 OID 18317)
-- Name: rubrics_pkey; Type: CONSTRAINT; Schema: public; Owner: hsdbu; Tablespace: 
--

ALTER TABLE ONLY rubrics
    ADD CONSTRAINT rubrics_pkey PRIMARY KEY (rubric_id);


--
-- TOC entry 2251 (class 2606 OID 18319)
-- Name: sections_pkey; Type: CONSTRAINT; Schema: public; Owner: hsdbu; Tablespace: 
--

ALTER TABLE ONLY sections
    ADD CONSTRAINT sections_pkey PRIMARY KEY (section_id);


--
-- TOC entry 2253 (class 2606 OID 18321)
-- Name: sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: hsdbu; Tablespace: 
--

ALTER TABLE ONLY sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (session_id);


--
-- TOC entry 2255 (class 2606 OID 18323)
-- Name: students_pkey; Type: CONSTRAINT; Schema: public; Owner: hsdbu; Tablespace: 
--

ALTER TABLE ONLY students
    ADD CONSTRAINT students_pkey PRIMARY KEY (student_rcs);


--
-- TOC entry 2257 (class 2606 OID 18325)
-- Name: tests_pkey; Type: CONSTRAINT; Schema: public; Owner: hsdbu; Tablespace: 
--

ALTER TABLE ONLY tests
    ADD CONSTRAINT tests_pkey PRIMARY KEY (test_id);


--
-- TOC entry 2259 (class 2606 OID 18327)
-- Name: users_pkey; Type: CONSTRAINT; Schema: public; Owner: hsdbu; Tablespace: 
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_pkey PRIMARY KEY (user_id);


--
-- TOC entry 2261 (class 2606 OID 18329)
-- Name: verifies_pkey; Type: CONSTRAINT; Schema: public; Owner: hsdbu; Tablespace: 
--

ALTER TABLE ONLY verifies
    ADD CONSTRAINT verifies_pkey PRIMARY KEY (verify_id);


--
-- TOC entry 2225 (class 1259 OID 18330)
-- Name: fki_grades_labs_fkey; Type: INDEX; Schema: public; Owner: hsdbu; Tablespace: 
--

CREATE INDEX fki_grades_labs_fkey ON grades_labs USING btree (lab_id);


--
-- TOC entry 2228 (class 1259 OID 18331)
-- Name: fki_grades_questions_fkey; Type: INDEX; Schema: public; Owner: hsdbu; Tablespace: 
--

CREATE INDEX fki_grades_questions_fkey ON grades_questions USING btree (grade_id);


--
-- TOC entry 2218 (class 1259 OID 18332)
-- Name: fki_grades_rubric_fkey; Type: INDEX; Schema: public; Owner: hsdbu; Tablespace: 
--

CREATE INDEX fki_grades_rubric_fkey ON grades USING btree (rubric_id);


--
-- TOC entry 2219 (class 1259 OID 18333)
-- Name: fki_grades_student_fkey; Type: INDEX; Schema: public; Owner: hsdbu; Tablespace: 
--

CREATE INDEX fki_grades_student_fkey ON grades USING btree (student_rcs);


--
-- TOC entry 2231 (class 1259 OID 18334)
-- Name: fki_grades_tests_fkey; Type: INDEX; Schema: public; Owner: hsdbu; Tablespace: 
--

CREATE INDEX fki_grades_tests_fkey ON grades_tests USING btree (test_id);


--
-- TOC entry 2232 (class 1259 OID 18335)
-- Name: fki_grades_tests_student_fkey; Type: INDEX; Schema: public; Owner: hsdbu; Tablespace: 
--

CREATE INDEX fki_grades_tests_student_fkey ON grades_tests USING btree (student_rcs);


--
-- TOC entry 2220 (class 1259 OID 18336)
-- Name: fki_grades_user_fkey; Type: INDEX; Schema: public; Owner: hsdbu; Tablespace: 
--

CREATE INDEX fki_grades_user_fkey ON grades USING btree (grade_user_id);


--
-- TOC entry 2237 (class 1259 OID 18337)
-- Name: fki_questions_fkey; Type: INDEX; Schema: public; Owner: hsdbu; Tablespace: 
--

CREATE INDEX fki_questions_fkey ON questions USING btree (rubric_id);


--
-- TOC entry 2242 (class 1259 OID 18338)
-- Name: fki_relationships_users_section_fkey; Type: INDEX; Schema: public; Owner: hsdbu; Tablespace: 
--

CREATE INDEX fki_relationships_users_section_fkey ON relationships_users USING btree (section_id);


--
-- TOC entry 2243 (class 1259 OID 18339)
-- Name: fki_relationships_users_user_fkey; Type: INDEX; Schema: public; Owner: hsdbu; Tablespace: 
--

CREATE INDEX fki_relationships_users_user_fkey ON relationships_users USING btree (user_id);


--
-- TOC entry 2265 (class 2606 OID 18340)
-- Name: grades_academic_integrity_rubric_fkey; Type: FK CONSTRAINT; Schema: public; Owner: hsdbu
--

ALTER TABLE ONLY grades_academic_integrity
    ADD CONSTRAINT grades_academic_integrity_rubric_fkey FOREIGN KEY (rubric_id) REFERENCES rubrics(rubric_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2266 (class 2606 OID 18345)
-- Name: grades_academic_integrity_student; Type: FK CONSTRAINT; Schema: public; Owner: hsdbu
--

ALTER TABLE ONLY grades_academic_integrity
    ADD CONSTRAINT grades_academic_integrity_student FOREIGN KEY (student_rcs) REFERENCES students(student_rcs) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2267 (class 2606 OID 18350)
-- Name: grades_labs_fkey; Type: FK CONSTRAINT; Schema: public; Owner: hsdbu
--

ALTER TABLE ONLY grades_labs
    ADD CONSTRAINT grades_labs_fkey FOREIGN KEY (lab_id) REFERENCES labs(lab_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2268 (class 2606 OID 18355)
-- Name: grades_labs_student_fkey; Type: FK CONSTRAINT; Schema: public; Owner: hsdbu
--

ALTER TABLE ONLY grades_labs
    ADD CONSTRAINT grades_labs_student_fkey FOREIGN KEY (student_rcs) REFERENCES students(student_rcs) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2269 (class 2606 OID 18360)
-- Name: grades_questions_grade_fkey; Type: FK CONSTRAINT; Schema: public; Owner: hsdbu
--

ALTER TABLE ONLY grades_questions
    ADD CONSTRAINT grades_questions_grade_fkey FOREIGN KEY (grade_id) REFERENCES grades(grade_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2270 (class 2606 OID 18365)
-- Name: grades_questions_question_fkey; Type: FK CONSTRAINT; Schema: public; Owner: hsdbu
--

ALTER TABLE ONLY grades_questions
    ADD CONSTRAINT grades_questions_question_fkey FOREIGN KEY (question_id) REFERENCES questions(question_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2262 (class 2606 OID 18370)
-- Name: grades_rubric_fkey; Type: FK CONSTRAINT; Schema: public; Owner: hsdbu
--

ALTER TABLE ONLY grades
    ADD CONSTRAINT grades_rubric_fkey FOREIGN KEY (rubric_id) REFERENCES rubrics(rubric_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2263 (class 2606 OID 18375)
-- Name: grades_student_fkey; Type: FK CONSTRAINT; Schema: public; Owner: hsdbu
--

ALTER TABLE ONLY grades
    ADD CONSTRAINT grades_student_fkey FOREIGN KEY (student_rcs) REFERENCES students(student_rcs) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2271 (class 2606 OID 18380)
-- Name: grades_tests_fkey; Type: FK CONSTRAINT; Schema: public; Owner: hsdbu
--

ALTER TABLE ONLY grades_tests
    ADD CONSTRAINT grades_tests_fkey FOREIGN KEY (test_id) REFERENCES tests(test_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2272 (class 2606 OID 18385)
-- Name: grades_tests_students_fkey; Type: FK CONSTRAINT; Schema: public; Owner: hsdbu
--

ALTER TABLE ONLY grades_tests
    ADD CONSTRAINT grades_tests_students_fkey FOREIGN KEY (student_rcs) REFERENCES students(student_rcs) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2264 (class 2606 OID 18390)
-- Name: grades_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: hsdbu
--

ALTER TABLE ONLY grades
    ADD CONSTRAINT grades_user_fkey FOREIGN KEY (grade_user_id) REFERENCES users(user_id) ON UPDATE SET NULL ON DELETE SET NULL;


--
-- TOC entry 2273 (class 2606 OID 18395)
-- Name: questions_fkey; Type: FK CONSTRAINT; Schema: public; Owner: hsdbu
--

ALTER TABLE ONLY questions
    ADD CONSTRAINT questions_fkey FOREIGN KEY (rubric_id) REFERENCES rubrics(rubric_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2274 (class 2606 OID 18400)
-- Name: relationships_users_section_fkey; Type: FK CONSTRAINT; Schema: public; Owner: hsdbu
--

ALTER TABLE ONLY relationships_users
    ADD CONSTRAINT relationships_users_section_fkey FOREIGN KEY (section_id) REFERENCES sections(section_id);


--
-- TOC entry 2275 (class 2606 OID 18405)
-- Name: relationships_users_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: hsdbu
--

ALTER TABLE ONLY relationships_users
    ADD CONSTRAINT relationships_users_user_fkey FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 2276 (class 2606 OID 18410)
-- Name: student_section_fkey; Type: FK CONSTRAINT; Schema: public; Owner: hsdbu
--

ALTER TABLE ONLY students
    ADD CONSTRAINT student_section_fkey FOREIGN KEY (student_section_id) REFERENCES sections(section_id);

