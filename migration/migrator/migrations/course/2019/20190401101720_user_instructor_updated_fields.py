"""Migration for a given Submitty course database."""

# NOTE: This migration is intended to be used with master/20190401101811_trigger_update_user_instructor_updated.py
#       Once this migration and the master migration is done, you may issue the following SQL command in Postgres to sync user data:
#
#       UPDATE users SET user_updated=user_updated AND instructor_updated=instructor_updated;

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
    database.execute("""
ALTER TABLE users ADD COLUMN IF NOT EXISTS user_updated BOOLEAN NOT NULL DEFAULT false;
ALTER TABLE users ADD COLUMN IF NOT EXISTS instructor_updated BOOLEAN NOT NULL DEFAULT false;""")


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
ALTER TABLE users DROP COLUMN IF EXISTS user_updated RESTRICT;
ALTER TABLE users DROP COLUMN IF EXISTS instructor_updated RESTRICT;""")
