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
        CREATE TABLE IF NOT EXISTS active_graders (
            id SERIAL PRIMARY KEY,
            grader_id VARCHAR(255) NOT NULL,
            gc_id integer NOT NULL,
            ag_user_id character varying(255),
            ag_team_id character varying(255),
            timestamp TIMESTAMP WITH TIME ZONE NOT NULL,
            FOREIGN KEY (grader_id) REFERENCES users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (gc_id) REFERENCES gradeable_component(gc_id) ON DELETE CASCADE,
            FOREIGN KEY (ag_user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (ag_team_id) REFERENCES gradeable_teams(team_id) ON DELETE CASCADE,
            CONSTRAINT ag_user_team_id_check CHECK ((ag_user_id IS NOT NULL) OR (ag_team_id IS NOT NULL)),
            UNIQUE (grader_id, gc_id, ag_user_id),
            UNIQUE (grader_id, gc_id, ag_team_id)
        );
    """)
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
