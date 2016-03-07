-- Users

INSERT INTO users (user_firstname, user_lastname, user_rcs, user_email, user_is_administrator, user_is_developer)
    VALUES ('Instructor', 'Elric', 'instructor', 'instructor@email.edu', 1, 0);

INSERT INTO users (user_firstname, user_lastname, user_rcs, user_email, user_is_administrator, user_is_developer)
    VALUES ('TA', 'Ross', 'ta', 'ta@email.edu', 0, 0);

INSERT INTO users (user_firstname, user_lastname, user_rcs, user_email, user_is_administrator, user_is_developer)
    VALUES ('Developer', 'Jackson', 'developer', 'instructor@email.edu', 1, 1);

-- Sections
INSERT INTO sections (section_title, section_is_enabled)
    VALUES ('Section 1', 1);

-- Relationships
INSERT INTO relationships_users (user_id, section_id)
    VALUES (2, 1);

-- Students
INSERT INTO students (student_rcs, student_last_name, student_first_name, student_section_id, student_grading_id, student_manual)
    VALUES ('student', 'Joe', 'Student', 1, 1, 0);