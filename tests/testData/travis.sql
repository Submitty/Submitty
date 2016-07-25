-- TEST DATA
INSERT INTO sections_registration (sections_registration_id) VALUES (1);
INSERT INTO sections_registration (sections_registration_id) VALUES (2);

-- Developers

INSERT INTO users (user_id, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
VALUES ('developer', 'Bob', 'Developer', 'developer@rpi.edu', 0, 1, NULL);

INSERT INTO users (user_id, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('pevelm-dev', 'Peveler', 'Matthew', 'pevelm@rpi.edu', 0, 1, NULL);

-- Administrators

INSERT INTO users(user_id, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('instructor', 'Steve', 'Instructor', 'instructor@rpi.edu', 1, 1, NULL);

INSERT INTO users (user_id, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('pevelm', 'Peveler', 'Matthew', 'pevelm@rpi.edu', 1, 1, NULL);

INSERT INTO users (user_id, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('cutler', 'Barb', 'Cutler', 'cutler@cs.rpi.edu', 1, 1, NULL);

-- Teaching Assistants

INSERT INTO users (user_id, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('ta', 'Teaching', 'Assistant', 'ta@.rpi.edu', 2, 1, NULL);

-- Undergraduate Assistants

INSERT INTO users (user_id, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('drumhb', 'Brandon', 'Drumheller', 'drumhb@rpi.edu', 3, 1, NULL);

INSERT INTO users (user_id, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('brees', 'Samuel', 'Brees', 'brees@rpi.edu', 3, 1, NULL);

INSERT INTO users (user_id, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('lees', 'Samantha', 'Lee', 'lees@rpi.edu', 3, 1, NULL);

-- Students
INSERT INTO users (user_id, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('smithj', 'John', 'Smith', 'smithj@email.com', 4, 1, NULL);

INSERT INTO users (user_id, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('scottm', 'Michael', 'Scott', 'scottm@dundermifflen.com', 4, 1, NULL);

INSERT INTO users (user_id, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('whitew', 'Walter', 'White', 'breakingbad@email.com', 4, 1, NULL);

INSERT INTO users (user_id, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('bradyb', 'Michael', 'Brady', 'bunch@email.com', 4, 1, NULL);

-- Assign TAs to sections

INSERT INTO grading_registration (sections_registration_id, user_id) VALUES (1, 'ta');
INSERT INTO grading_registration (sections_registration_id, user_id) VALUES (1, 'drumhb');