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
    database.execute('CREATE TABLE course_material_info (user_id character varying(255) NOT NULL,course_file_path TEXT NOT NULL,release_date timestamp with time zone NOT NULL,seen BOOLEAN DEFAULT FALSE NOT NULL)')
    database.execute('ALTER TABLE ONLY course_material_info ADD CONSTRAINT course_material_info_pkey PRIMARY KEY (user_id, course_file_path)')
    database.execute('ALTER TABLE ONLY course_material_info ADD CONSTRAINT course_material_info_fkey FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE')


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
    pass
