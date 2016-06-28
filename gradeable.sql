DROP TABLE IF EXISTS grading_rotating;
DROP TABLE IF EXISTS grading_registration;
DROP TABLE IF EXISTS late_day_exceptions;
DROP TABLE IF EXISTS late_days;
DROP TABLE IF EXISTS gradeable_component_data;
DROP TABLE IF EXISTS gradeable_data;
DROP TABLE IF EXISTS users; 
DROP TABLE IF EXISTS sections_rotating;
DROP TABLE IF EXISTS sections_registration;
DROP TABLE IF EXISTS gradeable_component;
DROP TABLE IF EXISTS gradeable;

CREATE TABLE gradeable(
    g_id VARCHAR(255) PRIMARY KEY,
    g_title VARCHAR(255) NOT NULL,
    g_overall_ta_instructions VARCHAR NOT NULL,
    g_team_assignment BOOLEAN NOT NULL,
    g_gradeable_type INT NOT NULL,
    g_grade_by_registration BOOLEAN NOT NULL,
    g_grade_start_date TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL,
    g_grade_released_date TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL,
    g_syllabus_bucket VARCHAR(255) NOT NULL,
    g_min_grading_group INT NOT NULL
);

CREATE TABLE gradeable_component(
    gc_id SERIAL PRIMARY KEY,
    g_id VARCHAR(255) NOT NULL REFERENCES gradeable(g_id) ON DELETE CASCADE,
    gc_title VARCHAR(255) NOT NULL,
    gc_ta_comment VARCHAR NOT NULL,
    gc_student_comment VARCHAR NOT NULL,
    gc_max_value NUMERIC NOT NULL,
    gc_is_text BOOLEAN NOT NULL,
    gc_is_extra_credit BOOLEAN NOT NULL,
    gc_order INT NOT NULL
);

CREATE TABLE sections_registration(
    sections_registration_id INT PRIMARY KEY
);

CREATE TABLE sections_rotating(
    sections_rotating_id INT PRIMARY KEY
);

CREATE TABLE users(
    user_id VARCHAR PRIMARY KEY,
    user_firstname VARCHAR NOT NULL,
    user_lastname VARCHAR NOT NULL,
    user_email VARCHAR NOT NULL,
    user_group INT NOT NULL,
    registration_section INT REFERENCES sections_registration(sections_registration_id) NOT NULL,
    rotating_section INT REFERENCES sections_rotating(sections_rotating_id) NOT NULL,
    CHECK (user_group >= 1 AND user_group <= 4)
);

CREATE TABLE gradeable_data(
    gd_id SERIAL PRIMARY KEY,
    g_id VARCHAR(255) NOT NULL REFERENCES gradeable(g_id) ON DELETE CASCADE,
    gd_user_id VARCHAR(255) REFERENCES users(user_id) NOT NULL,
    gd_grader_id VARCHAR(255) REFERENCES users(user_id) NOT NULL,
    gd_overall_comment VARCHAR NOT NULL,
    gd_status INT NOT NULL,
    gd_late_days_used INT NOT NULL,
    gd_active_version INT NOT NULL
);

CREATE TABLE gradeable_component_data(
    gc_id INT NOT NULL REFERENCES gradeable_component(gc_id) ON DELETE CASCADE,
    gd_id INT NOT NULL REFERENCES gradeable_data(gd_id) ON DELETE CASCADE,
    gcd_score NUMERIC NOT NULL,
    gcd_component_comment VARCHAR NOT NULL,
    PRIMARY KEY(gc_id,gd_id)
);

CREATE TABLE late_days(
    user_id VARCHAR(255) PRIMARY KEY REFERENCES users(user_id) NOT NULL,
    allowed_late_days INT NOT NULL,
    since_timestamp TIMESTAMP NOT NULL
);

CREATE TABLE late_day_exceptions(
    g_id VARCHAR(255) REFERENCES gradeable(g_id) NOT NULL,
    user_id VARCHAR(255) REFERENCES users(user_id) NOT NULL,
    late_day_exceptions INT NOT NULL,
    PRIMARY KEY(g_id,user_id)
);

CREATE TABLE grading_registration(
    sections_registration_id INT REFERENCES sections_registration(sections_registration_id) NOT NULL,
    user_id VARCHAR REFERENCES users(user_id) NOT NULL,
    PRIMARY KEY(sections_registration_id, user_id)
);

CREATE TABLE grading_rotating(
    g_id VARCHAR REFERENCES gradeable(g_id) NOT NULL,
    user_id VARCHAR REFERENCES users(user_id) NOT NULL,
    sections_rotating INT REFERENCES sections_rotating(sections_rotating_id) NOT NULL,
    PRIMARY KEY (g_id, user_id)
);

/* CREATE 4 accounts
    student - Joe Student | RCS - student 
    Instructor - Instructor Elric | RCS - instructor
    TA - TA Ross | RCS - ta
    Developer - Developer Jackson | RCS - developer


 
 INSERT INTO users (user_id, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES('student','Joe','Student','student@email.edu','4', '1');
    */