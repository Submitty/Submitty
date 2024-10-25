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
        CREATE TABLE IF NOT EXISTS current_gradable_grader (
            id SERIAL PRIMARY KEY,
            grader_id VARCHAR(255) NOT NULL,
            gc_id integer NOT NULL,
            cgg_user_id character varying(255),
            cgg_team_id character varying(255),
            timestamp TIMESTAMP WITH TIME ZONE NOT NULL,
            FOREIGN KEY (grader_id) REFERENCES users(user_id),
            FOREIGN KEY (gc_id) REFERENCES gradeable_component(gc_id),
            CONSTRAINT cgg_user_team_id_check CHECK (((cgg_user_id IS NOT NULL) OR (cgg_team_id IS NOT NULL))),
            UNIQUE (grader_id, gc_id, cgg_user_id),
            UNIQUE (grader_id, gc_id, cgg_team_id)
        );
    """)
    pass


def down(config, database, semester, course):
    database.execute("DROP TABLE IF EXISTS current_gradable_grader CASCADE;")
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
