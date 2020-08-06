"""Migration for a given Submitty course database."""


def up(config, database, semester, course):
    database.execute("""CREATE TABLE IF NOT EXISTS solution_ta_notes (
        g_id character varying(255) NOT NULL,
        g_type int NOT NULL,
        que_part_id int NOT NULL,
        solution_notes text NOT NULL,
        author character varying NOT NULL,
        edited_at timestamp with time zone NOT NULL
    )""")
    database.execute("ALTER TABLE IF EXISTS ONLY solution_ta_notes DROP CONSTRAINT IF EXISTS solution_ta_notes_g_id_fk;")
    database.execute("ALTER TABLE IF EXISTS ONLY solution_ta_notes DROP CONSTRAINT IF EXISTS solution_ta_notes_author_fk;")
    database.execute('ALTER TABLE solution_ta_notes ADD CONSTRAINT solution_ta_notes_g_id_fk FOREIGN KEY (g_id) REFERENCES gradeable(g_id)')
    database.execute('ALTER TABLE solution_ta_notes ADD CONSTRAINT solution_ta_notes_author_fk FOREIGN KEY (author) REFERENCES users(user_id)')


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
