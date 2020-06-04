"""Migration for the Submitty master database."""

import re
from datetime import datetime

def up(config, database):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """

    def enter_and_validate_date(suggested_date, msg):
        """
        Validate user entered date string.

        :param suggested_date: date string suggested to user.
        :type suggested_date: String
        :param msg: message to user asking for date.
        :type msg: String
        :return: validated date recieved from user
        :rtype: String
        """
        while True:
            date = input(msg)
            if date == "":
                date = suggested_date[1:-1]
            # Validate date entry.
            try:
                datetime.strptime(date, "%m/%d/%Y")
                return date
            except:
                print(f"Invalid date: '{date}'\n")
    # END function enter_and_validate_date()

    # There is a fair bit to do, so we will begin a transaction that can be ROLLBACKed should something go wrong.
    database.execute("BEGIN;")

    # Create terms table
    try:
        database.execute("""
CREATE TABLE IF NOT EXISTS terms (
    term_id character varying(255) PRIMARY KEY,
    name character varying(255) NOT NULL,
    start_date date NOT NULL,
    end_date date NOT NULL,
    CONSTRAINT terms_check CHECK (end_date > start_date)
);""")
        database.execute("ALTER TABLE ONLY terms OWNER TO submitty_dbuser;")
    except Exception as e:
        database.execute("ROLLBACK;")
        raise SystemExit("Error creating terms table.\n" + str(e))

    # Retrieve DISTINCT set of term codes from courses table.
    # These codes need to be INSERTed before creating the FK referencing terms table.
    term_codes = database.execute("SELECT DISTINCT semester FROM courses ORDER BY semester ASC;")

    # We need information about each term code to INSERT into the terms table.
    # Init list of information that will be INSERTed into terms table.
    terms_table_values = []

    # Get information about each term code.
    # 1. ask sysadmin for name for each term.
    #   a. suggest that sXX = "Spring 20XX".
    #   b. suggest that fXX = "Fall 20XX".
    #   c. suggest that uXX = "Summer 20XX".
    #   d. Do not suggest anything if code is not 'f', 's', or 'u' + "XX".
    # 2. ask sysadmin for start/end dates for each term.
    #   a. suggest that sXX = 01/02/XX to 05/30/XX.
    #   b. suggest that fXX = 09/01/XX to 12/23/XX.
    #   c. suggest that uXX = 06/01/XX to 08/31/XX.
    #   d. Do not suggest anything if code is not 'f', 's', or 'u' + "XX".
    for code in term_codes:
        # Get raw string from SELECT query result (semester column)
        code = code['semester']

        # if term code is style used at RPI (e.g. 'f19' = 'Fall 2019')...
        if re.fullmatch("^[fsu]\d{2}$", code):
            if code[0:1] == "f":
                suggested_name = "[Fall 20" + code[1:3] + "]"
                suggested_start_date = "[09/01/20" + code[1:3] + "]"
                suggested_end_date = "[12/23/20" + code[1:3] + "]"
            elif code[0:1] == "s":
                suggested_name = "[Spring 20" + code[1:3] + "]"
                suggested_start_date = "[01/02/20" + code[1:3] + "]"
                suggested_end_date = "[05/30/20" + code[1:3] + "]"
            else:
                suggested_name = "[Summer 20" + code[1:3] + "]"
                suggested_start_date = "[06/01/20" + code[1:3] + "]"
                suggested_end_date = "[08/31/20" + code[1:3] + "]"
        else:
            suggested_name = ""
            suggested_start_date = ""
            suggested_end_date = ""

        msg_name = f"What is the name for term '{code}' " + suggested_name + "? "
        msg_start_date = f"What is the start date for term '{code}' (MM/DD/YYYY) " + suggested_start_date + "? "
        msg_end_date = f"What is the end date for term '{code}' (MM/DD/YYYY) " + suggested_end_date + "? "

        name = input(msg_name)
        if name == "":
            name = suggested_name[1:-1]
        start_date = enter_and_validate_date(suggested_start_date, msg_start_date)
        end_date = enter_and_validate_date(suggested_end_date, msg_end_date)

        # Add term_code, name, start/end dates to list that will be transacted to DB
        terms_table_values.append((code, name, start_date, end_date))

    # INSERT term codes into new terms table.
    try:
        database.execute("LOCK TABLE terms IN ACCESS EXCLUSIVE MODE;")
        for values in terms_table_values:
            database.execute(f"INSERT INTO terms (term_id, name, start_date, end_date) VALUES ('{values[0]}', '{values[1]}', '{values[2]}', '{values[3]}') ON CONFLICT DO NOTHING;")
    except Exception as e:
        database.execute("ROLLBACK;")
        raise SystemExit("Error INSERTing values into terms table.\n" + str(e))

    # Create FK, courses table (semester) references terms table (term_id)
    try:
        database.execute("ALTER TABLE ONLY courses DROP CONSTRAINT IF EXISTS courses_fkey;")
        database.execute("ALTER TABLE ONLY courses ADD CONSTRAINT courses_fkey FOREIGN KEY (semester) REFERENCES terms (term_id) ON UPDATE CASCADE;")
    except Exception as e:
        database.execute("ROLLBACK;")
        raise SystemExit("Error creating FK for courses(semester) references terms(term_id)\n" + str(e))

    database.execute("COMMIT;")
# END function up()

def down(config, database):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    pass

# EOF
