"""Migration for a given Submitty course database."""
import json
from collections import OrderedDict

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
    database.execute(
        """
        CREATE TABLE IF NOT EXISTS gradeable_anon (
            user_id character varying NOT NULL,
            g_id character varying(255) NOT NULL,
            anon_id character varying(255),
            PRIMARY KEY (g_id, anon_id),
            FOREIGN KEY(user_id)
                REFERENCES users(user_id)
                ON UPDATE CASCADE,
            FOREIGN KEY(g_id)
                REFERENCES gradeable(g_id)
                ON UPDATE CASCADE
        );
        """
    )
    database_file = config.config_path / 'database.json'
    with(database_file).open('r') as db_file:
        db_info = json.load(db_file, object_pairs_hook=OrderedDict)
        database.execute("GRANT SELECT, INSERT, UPDATE, DELETE ON gradeable_anon TO {}".format(db_info['database_course_user']))

    database.execute("""
        INSERT INTO gradeable_anon (
            SELECT u.user_id, g_id, u.anon_id
            FROM gradeable g JOIN users u ON 1=1 WHERE NOT EXISTS (SELECT 1 FROM gradeable_anon WHERE user_id=u.user_id AND g_id=g.g_id) AND u.anon_id IS NOT NULL
        );
    """)
    database.execute("ALTER TABLE users DROP COLUMN IF EXISTS anon_id;")

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
    database.execute("ALTER TABLE users ADD COLUMN IF NOT EXISTS anon_id character varying(255);")
    database.execute("UPDATE users u SET anon_id=(SELECT ga.anon_id FROM gradeable_anon ga WHERE ga.user_id=u.user_id LIMIT 1);")
