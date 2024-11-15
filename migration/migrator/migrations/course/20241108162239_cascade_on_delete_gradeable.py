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
    database.execute("""
        ALTER TABLE gradeable_anon DROP CONSTRAINT gradeable_anon_g_id_fkey;
        ALTER TABLE gradeable_anon ADD CONSTRAINT gradeable_anon_g_id_fkey
            FOREIGN KEY (g_id) REFERENCES gradeable(g_id)
            ON UPDATE CASCADE ON DELETE CASCADE;

        ALTER TABLE peer_grading_panel DROP CONSTRAINT peer_grading_panel_g_id_fkey;
        ALTER TABLE peer_grading_panel ADD CONSTRAINT peer_grading_panel_g_id_fkey
            FOREIGN KEY (g_id) REFERENCES public.electronic_gradeable(g_id)
            ON UPDATE CASCADE ON DELETE CASCADE;
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
    pass
