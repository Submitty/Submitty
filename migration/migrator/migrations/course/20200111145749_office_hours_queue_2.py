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

    # Old status code meanings
    # const STATUS_CODE_IN_QUEUE = 0;//student is waiting in the queue
    # const STATUS_CODE_BEING_HELPED = 1;//student is currently being helped
    # const STATUS_CODE_SUCCESSFULLY_HELPED = 2;//student was successfully helped and is no longer in the queue
    # const STATUS_CODE_REMOVED_BY_INSTRUCTOR = 3;//student was removed by the instructor
    # const STATUS_CODE_REMOVED_THEMSELVES = 4;//student removed themselves from the queue
    # const STATUS_CODE_BULK_REMOVED = 5;//student was removed after the empty queue button was pressed


    # New status code meanings
    # Status Code Key: "ABC" where ABC is a string of 3 numbers
    #   A: who added you to the queue
    #       0:Self
    #       1:Mentor/TA/instructor
    #   B: Current state in queue
    #       0:Waiting
    #       1:Being helped
    #       2:Done/Fully out of the queue
    #   C: Who removed you
    #       0:Still in queue
    #       1:Removed yourself
    #       2:Mentor/TA helped you
    #       3:Mentor/TA removed you
    #       4:Kicked out because queue emptied
    #       5:You helped you

    rows = database.execute("select * from queue;")
    for row in rows:
        status_code_dict =	{
          0: "024",#I am going to move anyone that is still in the queue to now no longer be in the queue
          1: "024",#I am going to move anyone that is still in the queue to now no longer be in the queue
          2: "022",
          3: "023",
          4: "021",
          5: "024"
        }


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

        removed_by = "null"
        if(row[6]):
            removed_by = "'"+row[6]+"'"

        database.execute(
        """
        INSERT INTO queue_new
            (
                status,
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
                '"""+status_code_dict[row[7]]+"""',
                'old_queue',
                '"""+row[1]+"""',
                '"""+row[2]+"""',
                """+time_in+""",
                """+time_help_start+""",
                """+time_out+""",
                '"""+row[1]+"""',
                """+removed_by+""",
                """+removed_by+"""
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
