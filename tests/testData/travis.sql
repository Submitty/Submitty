-- TEST DATA
INSERT INTO sections_registration (sections_registration_id) VALUES (1);
INSERT INTO sections_registration (sections_registration_id) VALUES (2);

-- Developers

INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('developer', '$2y$10$ixKdNjozeJItoI.qpg/As.pL.mGo6QH3vyl.d1fCzztlLOQmp/kDG', 'Developer', 'Jackson', 'developer@rpi.edu', 0, 1, NULL);

-- Administrators

INSERT INTO users(user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('instructor', '$2y$10$TF4/JQHhsyS2jXkmXp5aj.e62rZca8xqW/80dIODi2Uj3swzosljG', 'Instructor', 'Elric', 'instructor@rpi.edu', 1, 1, NULL);

-- Teaching Assistants

INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('ta', '$2y$10$19mUGBfp6YPFVIisGcKJTOLyjubAwuZeQ04pbuVcwCrYQB6Qkyrtq', 'TA', 'Ross', 'ta@.rpi.edu', 2, 1, NULL);

-- Undergraduate Assistants

INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('drumhb', '$2y$10$u/4T8c2gceACYFVXQb9Xo.Wnrx7GxiGtr9B5gtggkSLogVJe.W51.', 'Brandon', 'Drumheller', 'drumhb@rpi.edu', 3, 1, NULL);

INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('brees', '$2y$10$PQJJ9J84IarewSxWeF21puuTDO0/yXAU9zrOrt711fgxHXV0IP0yO', 'Samuel', 'Brees', 'brees@rpi.edu', 3, 1, NULL);

INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('lees', '$2y$10$V7H8gKssTPvVMGSxxkL4..c/J9Mp.f.THXvTvOB4BEAH27bb4BysK', 'Samantha', 'Lee', 'lees@rpi.edu', 3, 1, NULL);

-- Students
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('smithj', '$2y$10$QWgS95.ylFDcBO9WmYuUIeIPOBLFcZwJ6mv57n3kE0L/BNCuNr/lu', 'John', 'Smith', 'smithj@email.com', 4, 1, NULL);

INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('student', '$2y$10$auNE9Ih6T7OO8WzEWVlFF.AQVRWtANFR5jYvS2fyz9sRgG2ozL2OO', 'Joe', 'Student', 'student@email.com', 4, 1, NULL);

-- Assign TAs to sections

INSERT INTO grading_registration (sections_registration_id, user_id) VALUES (1, 'ta');
INSERT INTO grading_registration (sections_registration_id, user_id) VALUES (1, 'drumhb');