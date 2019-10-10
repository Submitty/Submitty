"""Migration for the Submitty master database."""


def up(config, database, semester, course):
    database.execute("CREATE TABLE IF NOT EXISTS queue(entry_id serial PRIMARY KEY, user_id VARCHAR(20) NOT NULL, name VARCHAR (20) NOT NULL, time_in TIMESTAMP NOT NULL, time_helped TIMESTAMP, time_out TIMESTAMP, status SMALLINT NOT NULL)");


def down(config, database, semester, course):
    database.execute("DROP TABLE IF EXISTS queue");
