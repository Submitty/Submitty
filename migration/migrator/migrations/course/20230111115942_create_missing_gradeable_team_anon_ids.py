"""Migration for a given Submitty course database."""
import random

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
    alpha = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890"
    anon_ids_raw = database.execute("SELECT anon_id FROM gradeable_teams")
    anon_ids = set()
    for anon_id in anon_ids_raw:
        anon_ids.add(anon_id[0])
    rows = database.execute('SELECT team_id FROM gradeable_teams WHERE anon_id IS NULL')
    anon_id = None
    for row in rows:
        while anon_id is None or anon_id in anon_ids:
            anon_id = ""
            for i in range(15):
                anon_id += alpha[random.randrange(len(alpha))]
        database.execute("UPDATE gradeable_teams SET anon_id='{}' WHERE team_id='{}'".format(anon_id, row[0]))


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
