"""Migration for a given Submitty course database."""
def up(config, database):
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
    # Self Registration Types: 0 == None, 1 == Users request to join when registering (added later), 2 == All users auto join when registering
    database.execute("""
                     ALTER TABLE courses ADD COLUMN IF NOT EXISTS self_registration_type smallint default 0,
                     ADD COLUMN IF NOT EXISTS default_section_id VARCHAR(255)
                     """)

def down(config, database):
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
