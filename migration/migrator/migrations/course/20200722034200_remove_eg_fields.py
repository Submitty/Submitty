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

    database.execute(
        """
        ALTER TABLE electronic_gradeable
            DROP COLUMN IF EXISTS eg_peer_grading,
            DROP COLUMN IF EXISTS eg_peer_grade_set
        """
    )

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
    database.execute(
        """
        ALTER TABLE electronic_gradeable
            ADD COLUMN eg_peer_grading boolean DEFAULT false NOT NULL,
            ADD COLUMN eg_peer_grade_set integer DEFAULT 0 NOT NULL
        """
    )
    database.execute(
        """
        UPDATE electronic_gradeable
        SET eg_peer_grading = component.is_gradeable_peer
        FROM
            (SELECT g_id, bool_or(gc_is_peer) AS is_gradeable_peer
            FROM gradeable_component
            GROUP BY g_id) component
        WHERE electronic_gradeable.g_id = component.g_id
        """
    )
