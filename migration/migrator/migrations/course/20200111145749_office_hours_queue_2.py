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

    # Remove old queue by just removing any old data
    database.execute("DROP TABLE IF EXISTS queue;")
    database.execute(
        """
        CREATE TABLE IF NOT EXISTS queue(
        	entry_id SERIAL PRIMARY KEY,
        	status TEXT NOT NULL,
        	queue_code TEXT NOT NULL,
        	user_id TEXT NOT NULL REFERENCES users(user_id),
        	name TEXT NOT NULL,
        	time_in TIMESTAMP NOT NULL,
        	time_help_start TIMESTAMP,
        	time_out TIMESTAMP,
        	added_by TEXT REFERENCES users(user_id),
        	help_started_by TEXT REFERENCES users(user_id),
        	removed_by TEXT REFERENCES users(user_id)
        );
        """
    )
    database.execute("DROP TABLE IF EXISTS queue_settings;")
    database.execute(
        """
        CREATE TABLE IF NOT EXISTS queue_settings(
          id serial PRIMARY KEY,
          open boolean NOT NULL,
          code text NOT NULL
        );
        """
    )
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

    database.execute("DROP TABLE IF EXISTS queue;")
    # run the old migration for the old version of the queue
    database.execute(
        "CREATE TABLE IF NOT EXISTS queue(entry_id serial PRIMARY KEY, user_id VARCHAR(20) NOT NULL  REFERENCES users(user_id), name VARCHAR (20) NOT NULL, time_in TIMESTAMP NOT NULL, time_helped TIMESTAMP, time_out TIMESTAMP, removed_by VARCHAR (20)  REFERENCES users(user_id), status SMALLINT NOT NULL)"
    )
    pass
