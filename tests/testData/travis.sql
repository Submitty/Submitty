-- TEST DATA
INSERT INTO sections (section_title, section_is_enabled) VALUES ('Section 1', 1);

INSERT INTO students (student_rcs, student_last_name, student_first_name, student_section_id, student_grading_id)
    VALUES ('pevelm', 'Peveler', 'Matthew', 1, 1);
INSERT INTO students (student_rcs, student_last_name, student_first_name, student_section_id, student_grading_id)
    VALUES ('cutler', 'Cutler', 'Barb', 1, 1);

INSERT INTO users (user_firstname, user_lastname, user_rcs, user_email)
    VALUES ('Teaching', 'Assistant', 'ta', 'ta@rpi.edu');
INSERT INTO users (user_firstname, user_lastname, user_rcs, user_email, user_is_administrator)
    VALUES ('Instructor', 'Bob', 'instructor', 'instructor@rpi.edu',1);
INSERT INTO users (user_firstname, user_lastname, user_rcs, user_email, user_is_developer)
    VALUES ('Developer', 'Sid', 'developer', 'developer@rpi.edu', 1);
INSERT INTO users (user_firstname, user_lastname, user_rcs, user_email, user_is_developer)
    VALUES ('Matthew', 'Peveler', 'pevelm', 'pevelm@rpi.edu', 1);

INSERT INTO labs (lab_number, lab_title, lab_checkpoints, lab_code)
    VALUES (1, 'Lab 1', 'Checkpoint 1,Checkpoint 2,Checkpoint 3', '');
INSERT INTO labs (lab_number, lab_title, lab_checkpoints, lab_code)
    VALUES (2, 'Lab 2', 'Checkpoint 1,Checkpoint 2,Checkpoint 3', '');