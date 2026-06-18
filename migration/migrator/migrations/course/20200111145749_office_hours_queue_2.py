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
    database.execute(
        """
        CREATE TABLE IF NOT EXISTS queue_new(
        	entry_id SERIAL PRIMARY KEY,
            current_state TEXT NOT NULL,
            removal_type TEXT,
        	queue_code TEXT NOT NULL,
        	user_id TEXT NOT NULL REFERENCES users(user_id),
        	name TEXT NOT NULL,
        	time_in TIMESTAMP NOT NULL,
        	time_help_start TIMESTAMP,
        	time_out TIMESTAMP,
        	added_by TEXT NOT NULL REFERENCES users(user_id),
        	help_started_by TEXT REFERENCES users(user_id),
        	removed_by TEXT REFERENCES users(user_id)
        );
        """
    )

    # Old status code meanings
    # const STATUS_CODE_IN_QUEUE = 0;//student is waiting in the queue
    # const STATUS_CODE_BEING_HELPED = 1;//student is currently being helped
    # const STATUS_CODE_SUCCESSFULLY_HELPED = 2;//student was successfully helped and is no longer in the queue
    # const STATUS_CODE_REMOVED_BY_INSTRUCTOR = 3;//student was removed by the instructor
    # const STATUS_CODE_REMOVED_THEMSELVES = 4;//student removed themselves from the queue
    # const STATUS_CODE_BULK_REMOVED = 5;//student was removed after the empty queue button was pressed

    # maps old codes to their removal type
    status_code_dict =	{
      0: 'self',
      1: 'self',
      2: 'helped',
      3: 'removed',
      4: 'self',
      5: 'emptied'
    }

    rows = database.execute("select * from queue;")
    for row in rows:
        # Some values are going to default to null
        # If the value exists they get replaced with the true value, set as a string hence the "'"
        time_in = "null"
        time_help_start = "null"
        time_out = "null"
        if(row[3]):
            time_in = "'"+row[3].isoformat()+"'"
        if(row[4]):
            time_help_start = "'"+row[4].isoformat()+"'"
        if(row[5]):
            time_out = "'"+row[5].isoformat()+"'"
        else:
            time_out = time_in

        removed_by = row[1]
        if(row[6]):
            removed_by = row[6]

        database.execute(
        """
        INSERT INTO queue_new
            (
                current_state,
                removal_type,
                queue_code,
                user_id,
                name,
                time_in,
                time_help_start,
                time_out,
                added_by,
                help_started_by,
                removed_by
            ) VALUES (
                'done',
                '"""+status_code_dict[row[7]]+"""',
                'old_queue',
                '"""+row[1]+"""',
                '"""+row[2]+"""',
                """+time_in+""",
                """+time_help_start+""",
                """+time_out+""",
                '"""+row[1]+"""',
                '"""+removed_by+"""',
                '"""+removed_by+"""'
            )
        """
        )
    database.execute("DROP TABLE IF EXISTS queue;")#Remove the old database
    database.execute("ALTER TABLE IF EXISTS queue_new RENAME TO queue;")#rename this database to now be called queue


    # There is no reason to keep the old queue_settings
    # There was nothing being stored in here that anyone would care about in the future
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

    # I left this code in here just in case. This code will recreate the old queue database however it will also destroy all queue data so most likely we never want to run it.

    # database.execute("DROP TABLE IF EXISTS queue;")
    # # run the old migration for the old version of the queue
    # database.execute(
    #     "CREATE TABLE IF NOT EXISTS queue(entry_id serial PRIMARY KEY, user_id VARCHAR(20) NOT NULL  REFERENCES users(user_id), name VARCHAR (20) NOT NULL, time_in TIMESTAMP NOT NULL, time_helped TIMESTAMP, time_out TIMESTAMP, removed_by VARCHAR (20)  REFERENCES users(user_id), status SMALLINT NOT NULL)"
    # )
    #
    # database.execute("DROP TABLE IF EXISTS queue_settings;")
    # database.execute("CREATE TABLE IF NOT EXISTS queue_settings(id serial PRIMARY KEY, open boolean NOT NULL, code VARCHAR (20) NOT NULL)")

    pass
