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
    gradeable_ids_raw = database.execute("SELECT g_id FROM gradeable WHERE g_gradeable_type = 0")
    user_ids_raw = database.execute("SELECT user_id FROM users")
    gradeable_ids = set()
    user_ids = set()
    for gradeable_id in gradeable_ids_raw:
        gradeable_ids.add(gradeable_id[0])
    for user_id in user_ids_raw:
        user_ids.add(user_id[0])
    for gradeable_id in gradeable_ids:
        raw_anon_user_ids = database.execute("SELECT user_id FROM gradeable_anon WHERE g_id = '{}'".format(gradeable_id))
        anon_user_ids = set()
        for anon_user_id in raw_anon_user_ids:
            anon_user_ids.add(anon_user_id[0])
        for user_id in user_ids:
            if user_id not in anon_user_ids:
                anon_id = ""
                for i in range(15):
                    anon_id += alpha[random.randrange(len(alpha))]
                database.execute("INSERT INTO gradeable_anon (g_id, user_id, anon_id) VALUES ('{}', '{}', '{}')".format(gradeable_id, user_id, anon_id))
    pass


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
