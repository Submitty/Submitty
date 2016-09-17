-- Add all registration sections

INSERT INTO sections_registration(sections_registration_id)
    VALUES (1);
INSERT INTO sections_registration(sections_registration_id)
    VALUES (2);

-- Add all instructors / TAs, administrators
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('developer', '$2y$10$DuPIEHfBo5ka582lxrY5Au0roVsH3x7DAuoIVKHKrlM6wYu7zHGWG', 'Developer', 'Jackson', 'instructor@email.edu', 0, NULL, NULL);

INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('instructor', '$2y$10$ppe59K2lOzIwq5iilQ3pmedtt1hBtwhzU3SbXalX3h2LSm2jMcXVG', 'Instructor', 'Elric', 'instructor@email.edu', 1, NULL, NULL);

INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('ta', '$2y$10$x0snjvDkpM156aDVJdvO2u/kUDJzf1vcGQt7Qufzg.j9RdG0jbTSO', 'TA', 'Ross', 'ta@email.edu', 2, 1, NULL);

-- Assign TAs to registration_sections
INSERT INTO grading_registration(sections_registration_id, user_id)
    VALUES(1, 'ta');

-- Students
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('student', '$2y$10$B22S4NnFss8.JrJIL51QSO0DpiiEuQkZfhyHk29.CPH1zlNbFSsXO', 'Joe', 'Student', 'student@email.com', 4, 1, NULL);
    
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('smithj', '$2y$10$QmZt5iv6gYlUrKXiKVoQee169ruXqNAk0loj835wbG7/CXX7BGboy', 'John', 'Smith', 'smithj@email.com', 4, 1, NULL);

INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('joness', '$2y$10$QmZt5iv6gYlUrKXiKVoQee169ruXqNAk0loj835wbG7/CXX7BGboy', 'Sally', 'Jones', 'joness@email.com', 4, 2, NULL);

INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('browna', '$2y$10$QmZt5iv6gYlUrKXiKVoQee169ruXqNAk0loj835wbG7/CXX7BGboy', 'Alex', 'Brown', 'browna@email.com', 4, 2, NULL);
    
-- Late days    
INSERT INTO late_days (user_id, allowed_late_days, since_timestamp) 
    VALUES ('student', 3, timestamp '1970-01-01 00:00:00');
    
INSERT INTO late_days (user_id, allowed_late_days, since_timestamp) 
    VALUES ('smithj', 3, timestamp '1970-01-01 00:00:00');

INSERT INTO late_days (user_id, allowed_late_days, since_timestamp) 
    VALUES ('joness', 3, timestamp '1970-01-01 00:00:00');

INSERT INTO late_days (user_id, allowed_late_days, since_timestamp) 
    VALUES ('browna', 3, timestamp '1970-01-01 00:00:00');
