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

    # delete all duplicate anon_ids

    duplicate_anon_ids_res = database.execute("SELECT anon_id FROM gradeable_teams GROUP BY anon_id HAVING COUNT(*) > 1;")

    for dup in duplicate_anon_ids_res:
        database.session.execute("UPDATE gradeable_teams SET anon_id = NULL WHERE anon_id = :val", {"val": dup[0]})

    # below is a bugfix of 20230111115942_create_missing_gradeable_team_anon_ids.py

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
        anon_ids.add(anon_id)
        anon_id = None

    # end bugfix

    # add database constraint to enforce unique anon_ids
    database.execute("ALTER TABLE gradeable_teams ADD CONSTRAINT anon_id_unique UNIQUE (anon_id)")


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
    database.execute("ALTER TABLE gradeable_teams DROP CONSTRAINT anon_id_unique")
