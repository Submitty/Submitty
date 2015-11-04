-- TEST DATA
INSERT INTO sections (section_title, section_is_enabled) VALUES ('Section 1', 1);

INSERT INTO students (student_rcs, student_last_name, student_first_name, student_section_id, student_grading_id)
    VALUES ('pevelm', 'Peveler', 'Matthew', 1, 1);

INSERT INTO users (user_firstname, user_lastname, user_rcs, user_email)
    VALUES ('user', 'user', 'user', 'user@users.com');
INSERT INTO users (user_firstname, user_lastname, user_rcs, user_email, user_is_administrator)
    VALUES ('admin', 'admin', 'admin', 'admin@users.com',1);
INSERT INTO users (user_firstname, user_lastname, user_rcs, user_email, user_is_developer)
    VALUES ('developer', 'developer', 'developer', 'developer@users.com', 1);