-- Add all registration sections

INSERT INTO sections_registration(sections_registration_id)
    VALUES (1);

-- Add all instructors / TAs, administrators
INSERT INTO users (user_id, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('developer', 'Developer', 'Jackson', 'instructor@email.edu', 0, NULL, NULL);

INSERT INTO users (user_id, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('instructor', 'Instructor', 'Elric', 'instructor@email.edu', 1, NULL, NULL);

INSERT INTO users (user_id, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('ta', 'TA', 'Ross', 'ta@email.edu', 2, 1, NULL);

-- Assign TAs to registration_sections
INSERT INTO grading_registration(sections_registration_id, user_id)
    VALUES(1, 'ta');

-- Students
INSERT INTO users (user_id, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('student', 'Joe', 'Student', 'student@email.com', 4, 1, NULL);