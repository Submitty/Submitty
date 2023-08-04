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
    ALTER TABLE poll_responses DROP CONSTRAINT IF EXISTS poll_responses_id_seq;
    ALTER TABLE poll_responses ADD COLUMN IF NOT EXISTS id serial NOT NULL;
    ALTER TABLE poll_responses DROP CONSTRAINT IF EXISTS poll_responses_pkey;
    ALTER TABLE poll_responses ADD PRIMARY KEY (id);
    ALTER TABLE poll_options RENAME COLUMN option_id TO option_id_old;
    ALTER TABLE poll_options DROP CONSTRAINT IF EXISTS poll_options_option_id_seq;
    ALTER TABLE poll_options ADD COLUMN option_id serial NOT NULL;
    ALTER TABLE poll_options DROP CONSTRAINT IF EXISTS poll_options_pkey;
    ALTER TABLE poll_options ADD PRIMARY KEY (option_id);
    UPDATE poll_responses SET (option_id) = (SELECT option_id FROM poll_options WHERE poll_options.option_id_old = poll_responses.option_id AND poll_options.poll_id = poll_responses.poll_id);
    ALTER TABLE poll_options DROP COLUMN option_id_old;
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
