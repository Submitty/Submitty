"""Migration for a given Submitty course database."""
import os
import json

def course_config_location(semester, course):
    """
    Returns path to course config file for a given semester and course.
    
    :param semester: Semester of the course being migrated
    :type semester: str
    :param course: Code of course being migrated
    :type course: str
    """
    return f"/var/local/submitty/courses/{semester}/{course}/config/config.json"



def up(config, database, semester, course):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    :param semester: Semester of the course being migrated
    :type semester: str
    :param course: Code of course being migrated
    :type course: str
    """

    # First we need to patch missing constraints from existing courses prior to Fall 2019
    database.execute("""

        alter table electronic_gradeable drop constraint if exists eg_regrade_allowed_true;

        alter table electronic_gradeable add constraint eg_regrade_allowed_true CHECK
            (((eg_regrade_allowed IS TRUE) OR (eg_regrade_allowed IS FALSE)));

    """)


    database.execute("""
        ALTER TABLE regrade_discussion
            RENAME TO grade_inquiry_discussion;
        -- Rename Tables

        ALTER TABLE regrade_requests
            RENAME TO grade_inquiries;
   

    -- Rename Columns
        ALTER TABLE electronic_gradeable
            RENAME COLUMN eg_regrade_allowed TO eg_grade_inquiry_allowed;

        ALTER TABLE grade_inquiry_discussion
            RENAME COLUMN regrade_id TO grade_inquiry_id;
    

    -- Rename Constraints
        -- electronic_gradeable:
            ALTER TABLE electronic_gradeable
                RENAME CONSTRAINT eg_regrade_allowed_true TO eg_grade_inquiry_allowed_true;


        -- grade_inquiry_discussion:
            ALTER TABLE grade_inquiry_discussion
                RENAME CONSTRAINT regrade_discussion_pkey TO grade_inquiry_discussion_pkey;

            ALTER TABLE grade_inquiry_discussion
                RENAME CONSTRAINT regrade_discussion_fk0 TO grade_inquiry_discussion_fk0;

            ALTER TABLE grade_inquiry_discussion
                RENAME CONSTRAINT regrade_discussion_fk1 TO grade_inquiry_discussion_fk1;

            ALTER TABLE grade_inquiry_discussion
                RENAME CONSTRAINT regrade_discussion_regrade_requests_id_fk TO
                grade_inquiry_discussion_grade_inquiries_id_fk;


         -- grade_inquiries:
            ALTER TABLE grade_inquiries
                RENAME CONSTRAINT regrade_requests_pkey TO grade_inquiries_pkey;

            ALTER TABLE grade_inquiries
                RENAME CONSTRAINT regrade_requests_fk0 TO grade_inquiries_fk0;

            ALTER TABLE grade_inquiries
                RENAME CONSTRAINT regrade_requests_fk1 TO grade_inquiries_fk1;

            ALTER TABLE grade_inquiries
                RENAME CONSTRAINT regrade_requests_fk2 TO grade_inquiries_fk2;

            ALTER TABLE grade_inquiries
                RENAME CONSTRAINT regrade_requests_fk3 TO grade_inquiries_fk3;


    -- Rename Sequences
        ALTER SEQUENCE regrade_discussion_id_seq RENAME TO grade_inquiry_discussion_id_seq;

        ALTER SEQUENCE regrade_requests_id_seq RENAME TO grade_inquiries_id_seq;
    """)


    # Update Course Config
    path = course_config_location(semester, course)
    if not os.path.isfile(path) or os.path.getsize(path) == 0: # Empty file
        return

    with open(path, "r") as config_file:
        # Get regrade_message
        course_config = json.load(config_file)
    
    # Rename key
    grade_inquiry_message = course_config["course_details"].pop("regrade_message")
    course_config["course_details"]["grade_inquiry_message"] = grade_inquiry_message

    with open(path, "w") as config_file:
        json.dump(course_config, config_file, indent=4)




def down(config, database, semester, course):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    :param semester: Semester of the course being migrated
    :type semester: str
    :param course: Code of course being migrated
    :type course: str
    """

    
    database.execute("""
        ALTER SEQUENCE grade_inquiry_discussion_id_seq RENAME TO regrade_discussion_id_seq;
        -- Revert Sequences

        ALTER SEQUENCE grade_inquiries_id_seq RENAME TO regrade_requests_id_seq;


    -- Revert Cconstraints
        -- electronic_gradeable:
            ALTER TABLE electronic_gradeable
                RENAME CONSTRAINT eg_grade_inquiry_allowed_true TO eg_regrade_allowed_true;


        -- grade_inquiry_discussion:
            ALTER TABLE grade_inquiry_discussion
                RENAME CONSTRAINT grade_inquiry_discussion_pkey TO regrade_discussion_pkey;

            ALTER TABLE grade_inquiry_discussion
                RENAME CONSTRAINT grade_inquiry_discussion_fk0 TO regrade_discussion_fk0;

            ALTER TABLE grade_inquiry_discussion
                RENAME CONSTRAINT grade_inquiry_discussion_fk1 TO regrade_discussion_fk1;

            ALTER TABLE grade_inquiry_discussion
                RENAME CONSTRAINT grade_inquiry_discussion_grade_inquiries_id_fk TO
                regrade_discussion_regrade_requests_id_fk;


        -- grade_inquiries:
            ALTER TABLE grade_inquiries
                RENAME CONSTRAINT grade_inquiries_pkey TO regrade_requests_pkey;

            ALTER TABLE grade_inquiries
                RENAME CONSTRAINT grade_inquiries_fk0 TO regrade_requests_fk0;

             ALTER TABLE grade_inquiries
                RENAME CONSTRAINT grade_inquiries_fk1 TO regrade_requests_fk1;

            ALTER TABLE grade_inquiries
                RENAME CONSTRAINT grade_inquiries_fk2 TO regrade_requests_fk2;

            ALTER TABLE grade_inquiries
                RENAME CONSTRAINT grade_inquiries_fk3 TO regrade_requests_fk3;


    -- Revert Column Names
        ALTER TABLE electronic_gradeable
            RENAME COLUMN eg_grade_inquiry_allowed TO eg_regrade_allowed;
    
        ALTER TABLE grade_inquiry_discussion
            RENAME COLUMN grade_inquiry_id TO regrade_id;

    -- Revert Table Names
        ALTER TABLE grade_inquiry_discussion
            RENAME TO regrade_discussion;

        ALTER TABLE grade_inquiries
            RENAME TO regrade_requests;
    """)


    # Update Course Config
    path = course_config_location(semester, course)
    if not os.path.isfile(path) or os.path.getsize(path) == 0: # Empty file
        return

    with open(path, "r") as config_file:
        # Get regrade_message
        course_config = json.load(config_file)
    
    # Rename key
    regrade_message = course_config["course_details"].pop("grade_inquiry_message")
    course_config["course_details"]["regrade_message"] = regrade_message

    with open(path, "w") as config_file:
        json.dump(course_config, config_file, indent=4)
