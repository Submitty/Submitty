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

    # Add a column for soft deletion
    database.execute("""
                     ALTER TABLE course_materials ADD COLUMN IF NOT EXISTS is_deleted BOOLEAN DEFAULT FALSE;
                     """)

    # Update existing data (optional, depending on your needs)
    database.execute("""
                     UPDATE course_materials SET is_deleted = FALSE;
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

    # Remove the added column
    database.execute("""
                     ALTER TABLE course_materials DROP COLUMN IF EXISTS is_deleted;
                     """)
