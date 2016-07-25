INSERT INTO sections (section_number, section_title, section_is_enabled) VALUES (1, 'Section 1', 1);

INSERT INTO users (user_id, user_firstname, user_lastname, user_email, user_group, user_course_section, user_assignment_section)
    VALUES ('student', 'Joe', 'Student', 'student@rpi.edu', 1, 1, 1);

INSERT INTO users (user_id, user_firstname, user_lastname, user_email, user_group, user_course_section, user_assignment_section)
    VALUES ('ta', 'John', 'TA', 'ta@rpi.edu', 3, 1, 1);

INSERT INTO users (user_id, user_firstname, user_lastname, user_email, user_group, user_course_section, user_assignment_section)
    VALUES ('instructor', 'Sam', 'Instructor', 'instructor@rpi.edu', 4, 1, 1);

INSERT INTO users (user_id, user_firstname, user_lastname, user_email, user_group, user_course_section, user_assignment_section)
    VALUES ('developer', 'Pete', 'Developer', 'developer@rpi.edu', 5, 1, 1);