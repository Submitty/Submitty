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

        CREATE TABLE IF NOT EXISTS chatroom_participants (
            id SERIAL PRIMARY KEY,
            chatroom_id integer NOT NULL REFERENCES chatrooms(id) ON DELETE CASCADE,
            user_id character varying NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
            anon_salt character varying(64) NOT NULL,
            session_snapshot character varying(32) DEFAULT NULL,
            anon_name character varying(64) DEFAULT NULL,
            CONSTRAINT unique_participant UNIQUE (chatroom_id, user_id)
        );
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
        DROP TABLE IF EXISTS chatroom_participants;
        ALTER TABLE chatrooms DROP COLUMN IF EXISTS allow_read_only_after_end;
        """
    )

