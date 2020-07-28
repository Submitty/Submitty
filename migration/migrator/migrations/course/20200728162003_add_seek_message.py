"""Migration for a given Submitty course database."""


def up(config, database, semester, course):
    database.execute('ALTER TABLE IF EXISTS seeking_team ADD COLUMN IF NOT EXISTS message character varying;')
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
    pass


def down(config, database, semester, course):
    pass
