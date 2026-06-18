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
       CREATE TABLE IF NOT EXISTS gradeable_access (
            id SERIAL NOT NULL PRIMARY KEY,
            g_id character varying(255) NOT NULL REFERENCES gradeable (g_id) ON DELETE CASCADE,
            user_id character varying(255) REFERENCES users (user_id) ON DELETE CASCADE,
            team_id character varying(255) REFERENCES gradeable_teams (team_id),
            accessor_id character varying(255) REFERENCES users (user_id) ON DELETE CASCADE,
            "timestamp" timestamp with time zone NOT NULL,
            CONSTRAINT access_team_id_check CHECK (((user_id IS NOT NULL) OR (team_id IS NOT NULL)))
        );
        """
    )
