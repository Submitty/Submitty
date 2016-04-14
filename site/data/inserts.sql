--
-- SYSTEM INSERTS
-- This file contains all necessary inserts for the system to run. This must be run after tables.sql.
--


--
-- Section Inserts
--
INSERT INTO sections (section_number, section_title, section_is_enabled) VALUES (-1, 'Disabled Section', 0);


--
-- Group Inserts
--
INSERT INTO groups (group_number, group_name) VALUES (1, 'Student');
INSERT INTO groups (group_number, group_name) VALUES (2, 'Grader');
INSERT INTO groups (group_number, group_name) VALUES (3, 'TA');
INSERT INTO groups (group_number, group_name) VALUES (4, 'Instructor');
INSERT INTO groups (group_number, group_name) VALUES (5, 'Developer');