DROP TABLE IF EXISTS gradeable_component_data;
DROP TABLE IF EXISTS gradeable_data;
DROP TABLE IF EXISTS gradeable_component;
DROP TABLE IF EXISTS gradeable;


CREATE TABLE gradeable(
    g_id VARCHAR PRIMARY KEY,
    g_title VARCHAR NOT NULL,
    g_overall_ta_instructions VARCHAR NOT NULL,
    g_team_assignment BOOLEAN NOT NULL,
    g_gradeable_type INT NOT NULL,
    g_grade_by_registration BOOLEAN NOT NULL,
    g_grade_start_date TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL,
    g_grade_released_date TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL,
    g_syllabus_bucket VARCHAR NOT NULL
);

CREATE TABLE gradeable_component(
    gc_id SERIAL PRIMARY KEY,
    g_id VARCHAR REFERENCES gradeable(g_id),
    gc_title VARCHAR NOT NULL,
    gc_ta_comment VARCHAR NOT NULL,
    gc_student_comment VARCHAR NOT NULL,
    gc_max_value NUMERIC NOT NULL,
    gc_is_text BOOLEAN NOT NULL,
    gc_is_extra_credit BOOLEAN NOT NULL,
    gc_order INT NOT NULL
);

CREATE TABLE gradeable_data(
    gd_id SERIAL PRIMARY KEY,
    g_id VARCHAR REFERENCES gradeable(g_id) NOT NULL,
    gd_user_id VARCHAR REFERENCES students(student_rcs) NOT NULL,
    gd_grader_id INT REFERENCES users(user_id) NOT NULL,
    gd_overall_comment VARCHAR NOT NULL,
    gd_status INT NOT NULL,
    gd_late_days_used INT NOT NULL,
    gd_active_version INT NOT NULL
);

CREATE TABLE gradeable_component_data(
    gc_id INT REFERENCES gradeable_component(gc_id) NOT NULL,
    gd_id INT REFERENCES gradeable_data(gd_id) NOT NULL,
    gcd_score NUMERIC NOT NULL,
    gcd_component_score VARCHAR NOT NULL
);