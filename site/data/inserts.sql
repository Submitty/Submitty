--
-- SYSTEM INSERTS
-- This file contains all necessary inserts for the system to run. This must be run after tables.sql.
--

--
-- Config Inserts
--
INSERT INTO config (config_name, config_type, config_value) VALUES ('course_name',4,'My Course Name');
INSERT INTO config (config_name, config_type, config_value) VALUES ('default_hw_late_days',1,'2');
INSERT INTO config (config_name, config_type, config_value) VALUES ('default_student_late_days',1,'3');
INSERT INTO config (config_name, config_type, config_value) VALUES ('use_autograder',3,'true');
INSERT INTO config (config_name, config_type, config_value) VALUES ('generate_diff',3,'true');
INSERT INTO config (config_name, config_type, config_value) VALUES ('zero_rubric_grades',3,'false');


--
-- Section Inserts
--
INSERT INTO sections (section_number, section_title, section_is_enabled) VALUES (-1, 'Disabled Section', 0);