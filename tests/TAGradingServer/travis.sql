--------------------------
-- SECTIONS
--------------------------

CREATE SEQUENCE section_sequence
INCREMENT 1
MINVALUE 1
MAXVALUE 9223372036854775807
START 1
CACHE 1;
ALTER TABLE section_sequence
OWNER TO test_hwgrading;

CREATE TABLE sections
(
  section_id integer NOT NULL DEFAULT nextval('section_sequence'::regclass),
  section_title character varying(255),
  section_is_enabled integer DEFAULT 0,
  CONSTRAINT sections_pkey PRIMARY KEY (section_id)
);

--------------------------
-- STUDENTS
--------------------------

CREATE SEQUENCE student_sequence
INCREMENT 1
MINVALUE 1
MAXVALUE 9223372036854775807
START 1
CACHE 1;
ALTER TABLE student_sequence
OWNER TO test_hwgrading;

CREATE TABLE students
(
  student_id integer NOT NULL DEFAULT nextval('student_sequence'::regclass),
  student_rcs character varying(255) NOT NULL,
  student_late_warning integer NOT NULL DEFAULT 0,
  student_allowed_lates integer NOT NULL DEFAULT -1,
  student_last_name character varying(64),
  student_first_name character varying(64),
  student_experience integer,
  student_section_id integer,
  student_grading_user_id integer,
  CONSTRAINT students_pkey PRIMARY KEY (student_rcs),
  CONSTRAINT student_section_fkey FOREIGN KEY (student_section_id)
  REFERENCES sections (section_id) MATCH SIMPLE
  ON UPDATE NO ACTION ON DELETE NO ACTION
);

-- TEST DATA
INSERT INTO sections (section_title, section_is_enabled) VALUES ('Section 1', 1);
INSERT INTO students (student_rcs, student_last_name, student_first_name, student_section_id, student_grading_user_id)
    VALUES ('pevelm', 'Peveler', 'Matthew', 1, 1);

--------------------------
-- Users
--------------------------
CREATE SEQUENCE user_sequence
START WITH 1
INCREMENT BY 1
NO MINVALUE
NO MAXVALUE
CACHE 1;

CREATE TABLE users (
  user_id integer DEFAULT nextval('user_sequence'::regclass) NOT NULL,
  user_firstname character varying(255),
  user_lastname character varying(255),
  user_rcs character varying(255),
  user_email character varying(255),
  user_is_administrator integer DEFAULT 0 NOT NULL,
  user_is_developer integer DEFAULT 0 NOT NULL
);

ALTER TABLE ONLY users ADD CONSTRAINT users_pkey PRIMARY KEY (user_id);

INSERT INTO users (user_firstname, user_lastname, user_rcs, user_email) 
    VALUES ('user', 'user', 'user', 'user@users.com');
INSERT INTO users (user_firstname, user_lastname, user_rcs, user_email, user_is_administrator)
    VALUES ('admin', 'admin', 'admin', 'admin@users.com',1);
INSERT INTO users (user_firstname, user_lastname, user_rcs, user_email, user_is_developer)
    VALUES ('developer', 'developer', 'developer', 'developer@users.com', 1);