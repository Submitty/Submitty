"""Migration for a given Submitty course database."""


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

    # Rename tables
    database.execute("""
        ALTER TABLE regrade_discussion
            RENAME TO grade_inquiry_discussion;

        ALTER TABLE regrade_requests
            RENAME TO grade_inquiries;
    """)

    # Rename columns
    database.execute("""
        ALTER TABLE electronic_gradeable
            RENAME COLUMN eg_regrade_allowed TO eg_grade_inquiry_allowed;

        ALTER TABLE grade_inquiry_discussion
            RENAME COLUMN regrade_id TO grade_inquiry_id;
    """)

    # Rename Constraints
    database.execute("""
        ALTER TABLE electronic_gradeable
            RENAME CONSTRAINT eg_regrade_allowed_true TO eg_grade_inquiry_allowed_true;

        ALTER TABLE grade_inquiry_discussion
            RENAME CONSTRAINT regrade_discussion_pkey TO grade_inquiry_discussion_pkey;

        ALTER TABLE grade_inquiries
            RENAME CONSTRAINT regrade_requests_pkey TO grade_inquiries_pkey;
    """)

    # Rename Sequences
    database.execute("""
        ALTER SEQUENCE regrade_discussion_id_seq RENAME TO grade_inquiry_discussion_id_seq;

        ALTER SEQUENCE regrade_requests_id_seq RENAME TO grade_inquiries_id_seq;
    """)


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

    # Revert Sequences
    database.execute("""
        ALTER SEQUENCE grade_inquiry_discussion_id_seq RENAME TO regrade_discussion_id_seq;

        ALTER SEQUENCE grade_inquiries_id_seq RENAME TO regrade_requests_id_seq;
    """)

    # Revert Constraints
    database.execute("""
        ALTER TABLE electronic_gradeable
            RENAME CONSTRAINT eg_grade_inquiry_allowed_true TO eg_regrade_allowed_true;

        ALTER TABLE grade_inquiry_discussion
            RENAME CONSTRAINT grade_inquiry_discussion_pkey TO regrade_discussion_pkey;
        
        ALTER TABLE grade_inquiries
            RENAME CONSTRAINT grade_inquiries_pkey TO regrade_requests_pkey;
     """)

    # Revert column names
    database.execute("""
        ALTER TABLE electronic_gradeable
            RENAME COLUMN eg_grade_inquiry_allowed TO eg_regrade_allowed;
    
        ALTER TABLE grade_inquiry_discussion
            RENAME COLUMN grade_inquiry_id TO regrade_id;
    """)

    # Revert table names
    database.execute("""
        ALTER TABLE grade_inquiry_discussion
            RENAME TO regrade_discussion;

        ALTER TABLE grade_inquiries
            RENAME TO regrade_requests;
    """)