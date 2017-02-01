-- Add all registration sections

INSERT INTO sections_registration(sections_registration_id)
    VALUES (1);
INSERT INTO sections_registration(sections_registration_id)
    VALUES (2);
INSERT INTO sections_registration(sections_registration_id)
    VALUES (3);
INSERT INTO sections_registration(sections_registration_id)
    VALUES (4);
INSERT INTO sections_registration(sections_registration_id)
    VALUES (5);
INSERT INTO sections_registration(sections_registration_id)
    VALUES (6);
INSERT INTO sections_registration(sections_registration_id)
    VALUES (7);
INSERT INTO sections_registration(sections_registration_id)
    VALUES (8);
INSERT INTO sections_registration(sections_registration_id)
    VALUES (9);


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
    VALUES ('joness', '$2y$10$eJlE7iFFI0K3XppPfPsjTO3noelTrhLAQmQwvgN8m.XueOREhR38a', 'Sally', 'Jones', 'joness@email.com', 4, 2, NULL);

INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('browna', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'Alex', 'Brown', 'browna@email.com', 4, 2, NULL);



-- Add 100 extra students just for bulk
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo00', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First00', 'Last00', 'foo00@email.com', 4, 1, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo01', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First01', 'Last01', 'foo01@email.com', 4, 2, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo02', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First02', 'Last02', 'foo02@email.com', 4, 1, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo03', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First03', 'Last03', 'foo03@email.com', 4, 4, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo04', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First04', 'Last04', 'foo04@email.com', 4, 5, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo05', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First05', 'Last05', 'foo05@email.com', 4, 6, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo06', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First06', 'Last06', 'foo06@email.com', 4, 7, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo07', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First07', 'Last07', 'foo07@email.com', 4, 8, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo08', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First08', 'Last08', 'foo08@email.com', 4, 9, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo09', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First09', 'Last09', 'foo09@email.com', 4, 1, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo10', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First10', 'Last10', 'foo10@email.com', 4, 2, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo11', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First11', 'Last11', 'foo11@email.com', 4, 3, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo12', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First12', 'Last12', 'foo12@email.com', 4, 4, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo13', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First13', 'Last13', 'foo13@email.com', 4, 5, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo14', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First14', 'Last14', 'foo14@email.com', 4, 6, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo15', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First15', 'Last15', 'foo15@email.com', 4, 7, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo16', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First16', 'Last16', 'foo16@email.com', 4, 8, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo17', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First17', 'Last17', 'foo17@email.com', 4, 9, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo18', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First18', 'Last18', 'foo18@email.com', 4, 1, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo19', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First19', 'Last19', 'foo19@email.com', 4, 2, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo20', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First20', 'Last20', 'foo20@email.com', 4, 3, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo21', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First21', 'Last21', 'foo21@email.com', 4, 4, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo22', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First22', 'Last22', 'foo22@email.com', 4, 5, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo23', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First23', 'Last23', 'foo23@email.com', 4, 6, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo24', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First24', 'Last24', 'foo24@email.com', 4, 7, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo25', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First25', 'Last25', 'foo25@email.com', 4, 8, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo26', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First26', 'Last26', 'foo26@email.com', 4, 9, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo27', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First27', 'Last27', 'foo27@email.com', 4, 1, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo28', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First28', 'Last28', 'foo28@email.com', 4, 2, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo29', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First29', 'Last29', 'foo29@email.com', 4, 3, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo30', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First30', 'Last30', 'foo30@email.com', 4, 4, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo31', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First31', 'Last31', 'foo31@email.com', 4, 5, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo32', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First32', 'Last32', 'foo32@email.com', 4, 6, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo33', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First33', 'Last33', 'foo33@email.com', 4, 7, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo34', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First34', 'Last34', 'foo34@email.com', 4, 8, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo35', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First35', 'Last35', 'foo35@email.com', 4, 9, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo36', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First36', 'Last36', 'foo36@email.com', 4, 1, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo37', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First37', 'Last37', 'foo37@email.com', 4, 2, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo38', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First38', 'Last38', 'foo38@email.com', 4, 3, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo39', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First39', 'Last39', 'foo39@email.com', 4, 4, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo40', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First40', 'Last40', 'foo40@email.com', 4, 5, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo41', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First41', 'Last41', 'foo41@email.com', 4, 6, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo42', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First42', 'Last42', 'foo42@email.com', 4, 7, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo43', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First43', 'Last43', 'foo43@email.com', 4, 8, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo44', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First44', 'Last44', 'foo44@email.com', 4, 9, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo45', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First45', 'Last45', 'foo45@email.com', 4, 1, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo46', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First46', 'Last46', 'foo46@email.com', 4, 2, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo47', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First47', 'Last47', 'foo47@email.com', 4, 3, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo48', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First48', 'Last48', 'foo48@email.com', 4, 4, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo49', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First49', 'Last49', 'foo49@email.com', 4, 5, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo50', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First50', 'Last50', 'foo50@email.com', 4, 6, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo51', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First51', 'Last51', 'foo51@email.com', 4, 7, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo52', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First52', 'Last52', 'foo52@email.com', 4, 8, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo53', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First53', 'Last53', 'foo53@email.com', 4, 9, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo54', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First54', 'Last54', 'foo54@email.com', 4, 1, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo55', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First55', 'Last55', 'foo55@email.com', 4, 2, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo56', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First56', 'Last56', 'foo56@email.com', 4, 3, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo57', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First57', 'Last57', 'foo57@email.com', 4, 4, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo58', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First58', 'Last58', 'foo58@email.com', 4, 5, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo59', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First59', 'Last59', 'foo59@email.com', 4, 6, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo60', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First60', 'Last60', 'foo60@email.com', 4, 7, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo61', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First61', 'Last61', 'foo61@email.com', 4, 8, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo62', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First62', 'Last62', 'foo62@email.com', 4, 9, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo63', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First63', 'Last63', 'foo63@email.com', 4, 1, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo64', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First64', 'Last64', 'foo64@email.com', 4, 2, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo65', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First65', 'Last65', 'foo65@email.com', 4, 3, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo66', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First66', 'Last66', 'foo66@email.com', 4, 4, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo67', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First67', 'Last67', 'foo67@email.com', 4, 5, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo68', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First68', 'Last68', 'foo68@email.com', 4, 6, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo69', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First69', 'Last69', 'foo69@email.com', 4, 7, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo70', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First70', 'Last70', 'foo70@email.com', 4, 8, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo71', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First71', 'Last71', 'foo71@email.com', 4, 9, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo72', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First72', 'Last72', 'foo72@email.com', 4, 1, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo73', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First73', 'Last73', 'foo73@email.com', 4, 2, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo74', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First74', 'Last74', 'foo74@email.com', 4, 3, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo75', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First75', 'Last75', 'foo75@email.com', 4, 4, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo76', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First76', 'Last76', 'foo76@email.com', 4, 5, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo77', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First77', 'Last77', 'foo77@email.com', 4, 6, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo78', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First78', 'Last78', 'foo78@email.com', 4, 7, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo79', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First79', 'Last79', 'foo79@email.com', 4, 8, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo80', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First80', 'Last80', 'foo80@email.com', 4, 9, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo81', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First81', 'Last81', 'foo81@email.com', 4, 1, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo82', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First82', 'Last82', 'foo82@email.com', 4, 2, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo83', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First83', 'Last83', 'foo83@email.com', 4, 3, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo84', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First84', 'Last84', 'foo84@email.com', 4, 4, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo85', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First85', 'Last85', 'foo85@email.com', 4, 5, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo86', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First86', 'Last86', 'foo86@email.com', 4, 6, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo87', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First87', 'Last87', 'foo87@email.com', 4, 7, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo88', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First88', 'Last88', 'foo88@email.com', 4, 8, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo89', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First89', 'Last89', 'foo89@email.com', 4, 9, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo90', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First90', 'Last90', 'foo90@email.com', 4, 1, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo91', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First91', 'Last91', 'foo91@email.com', 4, 2, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo92', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First92', 'Last92', 'foo92@email.com', 4, 3, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo93', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First93', 'Last93', 'foo93@email.com', 4, 4, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo94', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First94', 'Last94', 'foo94@email.com', 4, 5, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo95', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First95', 'Last95', 'foo95@email.com', 4, 6, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo96', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First96', 'Last96', 'foo96@email.com', 4, 7, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo97', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First97', 'Last97', 'foo97@email.com', 4, 8, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo98', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First98', 'Last98', 'foo98@email.com', 4, 9, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo99', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First99', 'Last99', 'foo99@email.com', 4, 1, NULL);


INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo100', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First100', 'Last100', 'foo100@email.com', 4, 2, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo101', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First101', 'Last101', 'foo101@email.com', 4, 3, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo102', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First102', 'Last102', 'foo102@email.com', 4, 4, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo103', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First103', 'Last103', 'foo103@email.com', 4, 5, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo104', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First104', 'Last104', 'foo104@email.com', 4, 6, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo105', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First105', 'Last105', 'foo105@email.com', 4, 7, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo106', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First106', 'Last106', 'foo106@email.com', 4, 8, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo107', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First107', 'Last107', 'foo107@email.com', 4, 9, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo108', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First108', 'Last108', 'foo108@email.com', 4, 1, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo109', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First109', 'Last109', 'foo109@email.com', 4, 2, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo110', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First110', 'Last110', 'foo110@email.com', 4, 3, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo111', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First111', 'Last111', 'foo111@email.com', 4, 4, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo112', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First112', 'Last112', 'foo112@email.com', 4, 5, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo113', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First113', 'Last113', 'foo113@email.com', 4, 6, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo114', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First114', 'Last114', 'foo114@email.com', 4, 7, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo115', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First115', 'Last115', 'foo115@email.com', 4, 8, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo116', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First116', 'Last116', 'foo116@email.com', 4, 9, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo117', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First117', 'Last117', 'foo117@email.com', 4, 1, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo118', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First118', 'Last118', 'foo118@email.com', 4, 2, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo119', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First119', 'Last119', 'foo119@email.com', 4, 3, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo120', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First120', 'Last120', 'foo120@email.com', 4, 4, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo121', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First121', 'Last121', 'foo121@email.com', 4, 5, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo122', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First122', 'Last122', 'foo122@email.com', 4, 6, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo123', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First123', 'Last123', 'foo123@email.com', 4, 7, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo124', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First124', 'Last124', 'foo124@email.com', 4, 8, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo125', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First125', 'Last125', 'foo125@email.com', 4, 9, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo126', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First126', 'Last126', 'foo126@email.com', 4, 1, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo127', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First127', 'Last127', 'foo127@email.com', 4, 2, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo128', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First128', 'Last128', 'foo128@email.com', 4, 3, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo129', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First129', 'Last129', 'foo129@email.com', 4, 4, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo130', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First130', 'Last130', 'foo130@email.com', 4, 5, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo131', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First131', 'Last131', 'foo131@email.com', 4, 6, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo132', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First132', 'Last132', 'foo132@email.com', 4, 7, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo133', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First133', 'Last133', 'foo133@email.com', 4, 8, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo134', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First134', 'Last134', 'foo134@email.com', 4, 9, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo135', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First135', 'Last135', 'foo135@email.com', 4, 1, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo136', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First136', 'Last136', 'foo136@email.com', 4, 2, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo137', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First137', 'Last137', 'foo137@email.com', 4, 3, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo138', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First138', 'Last138', 'foo138@email.com', 4, 4, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo139', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First139', 'Last139', 'foo139@email.com', 4, 5, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo140', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First140', 'Last140', 'foo140@email.com', 4, 6, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo141', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First141', 'Last141', 'foo141@email.com', 4, 7, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo142', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First142', 'Last142', 'foo142@email.com', 4, 8, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo143', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First143', 'Last143', 'foo143@email.com', 4, 9, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo144', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First144', 'Last144', 'foo144@email.com', 4, 1, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo145', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First145', 'Last145', 'foo145@email.com', 4, 2, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo146', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First146', 'Last146', 'foo146@email.com', 4, 3, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo147', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First147', 'Last147', 'foo147@email.com', 4, 4, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo148', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First148', 'Last148', 'foo148@email.com', 4, 5, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo149', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First149', 'Last149', 'foo149@email.com', 4, 6, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo150', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First150', 'Last150', 'foo150@email.com', 4, 7, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo151', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First151', 'Last151', 'foo151@email.com', 4, 8, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo152', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First152', 'Last152', 'foo152@email.com', 4, 9, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo153', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First153', 'Last153', 'foo153@email.com', 4, 1, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo154', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First154', 'Last154', 'foo154@email.com', 4, 2, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo155', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First155', 'Last155', 'foo155@email.com', 4, 3, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo156', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First156', 'Last156', 'foo156@email.com', 4, 4, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo157', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First157', 'Last157', 'foo157@email.com', 4, 5, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo158', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First158', 'Last158', 'foo158@email.com', 4, 6, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo159', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First159', 'Last159', 'foo159@email.com', 4, 7, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo160', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First160', 'Last160', 'foo160@email.com', 4, 8, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo161', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First161', 'Last161', 'foo161@email.com', 4, 9, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo162', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First162', 'Last162', 'foo162@email.com', 4, 1, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo163', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First163', 'Last163', 'foo163@email.com', 4, 2, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo164', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First164', 'Last164', 'foo164@email.com', 4, 3, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo165', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First165', 'Last165', 'foo165@email.com', 4, 4, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo166', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First166', 'Last166', 'foo166@email.com', 4, 5, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo167', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First167', 'Last167', 'foo167@email.com', 4, 6, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo168', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First168', 'Last168', 'foo168@email.com', 4, 7, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo169', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First169', 'Last169', 'foo169@email.com', 4, 8, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo170', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First170', 'Last170', 'foo170@email.com', 4, 9, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo171', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First171', 'Last171', 'foo171@email.com', 4, 1, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo172', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First172', 'Last172', 'foo172@email.com', 4, 2, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo173', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First173', 'Last173', 'foo173@email.com', 4, 3, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo174', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First174', 'Last174', 'foo174@email.com', 4, 4, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo175', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First175', 'Last175', 'foo175@email.com', 4, 5, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo176', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First176', 'Last176', 'foo176@email.com', 4, 6, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo177', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First177', 'Last177', 'foo177@email.com', 4, 7, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo178', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First178', 'Last178', 'foo178@email.com', 4, 8, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo179', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First179', 'Last179', 'foo179@email.com', 4, 9, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo180', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First180', 'Last180', 'foo180@email.com', 4, 1, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo181', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First181', 'Last181', 'foo181@email.com', 4, 2, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo182', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First182', 'Last182', 'foo182@email.com', 4, 3, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo183', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First183', 'Last183', 'foo183@email.com', 4, 4, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo184', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First184', 'Last184', 'foo184@email.com', 4, 5, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo185', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First185', 'Last185', 'foo185@email.com', 4, 6, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo186', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First186', 'Last186', 'foo186@email.com', 4, 7, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo187', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First187', 'Last187', 'foo187@email.com', 4, 8, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo188', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First188', 'Last188', 'foo188@email.com', 4, 9, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo189', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First189', 'Last189', 'foo189@email.com', 4, 1, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo190', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First190', 'Last190', 'foo190@email.com', 4, 2, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo191', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First191', 'Last191', 'foo191@email.com', 4, 3, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo192', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First192', 'Last192', 'foo192@email.com', 4, 4, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo193', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First193', 'Last193', 'foo193@email.com', 4, 5, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo194', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First194', 'Last194', 'foo194@email.com', 4, 6, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo195', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First195', 'Last195', 'foo195@email.com', 4, 7, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo196', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First196', 'Last196', 'foo196@email.com', 4, 8, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo197', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First197', 'Last197', 'foo197@email.com', 4, 9, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo198', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First198', 'Last198', 'foo198@email.com', 4, 1, NULL);
INSERT INTO users (user_id, user_password, user_firstname, user_lastname, user_email, user_group, registration_section, rotating_section)
    VALUES ('foo199', '$2y$10$jkeq0rvNQZRYSoJ2e.435Ot2BKyM67hzJPqMx97qFcVpWEKqVV31W', 'First199', 'Last199', 'foo199@email.com', 4, 2, NULL);




    
-- Late days    
--  INSERT INTO late_days (user_id, allowed_late_days, since_timestamp)
--      VALUES ('student', 3, timestamp '1970-01-01 00:00:00');
--
--  INSERT INTO late_days (user_id, allowed_late_days, since_timestamp)
--      VALUES ('smithj', 3, timestamp '1970-01-01 00:00:00');
--
--  INSERT INTO late_days (user_id, allowed_late_days, since_timestamp)
--      VALUES ('joness', 3, timestamp '1970-01-01 00:00:00');
--
--  INSERT INTO late_days (user_id, allowed_late_days, since_timestamp)
--      VALUES ('browna', 3, timestamp '1970-01-01 00:00:00');
