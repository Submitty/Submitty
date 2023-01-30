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
    database.execute("ALTER TABLE electronic_gradeable RENAME COLUMN eg_scanned_exam TO eg_student_download")
    database.execute("UPDATE electronic_gradeable SET eg_student_download = NOT eg_student_download")
    


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
    database.execute("ALTER TABLE electronic_gradeable RENAME COLUMN eg_student_download TO eg_scanned_exam")
    database.execute("UPDATE electronic_gradeable SET eg_scanned_exam = eg_student_view AND eg_student_view_after_grades")

