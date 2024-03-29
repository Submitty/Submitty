import json
import os
import datetime
from sqlalchemy import create_engine, MetaData, Table, bindparam, text
import sys
import requests
import psutil

my_program_name = sys.argv[0]

my_pid = os.getpid()

# Loop over all active processes on the server
for p in psutil.pids():
    try:
        cmdline = psutil.Process(p).cmdline()
        if (len(cmdline) < 2):
            continue
        # If anything on the command line matches the name of the program
        if cmdline[0].find("python") != -1 and cmdline[1].find(my_program_name) != -1:
            if p != my_pid:
                print("ERROR!  Another copy of '" + my_program_name +
                      "' is already running on the server.  Exiting.")
                sys.exit(1)
    except psutil.NoSuchProcess:
        # Whoops, the process ended before we could look at it. But that's ok!
        pass

try:
    CONFIG_PATH = os.path.join(
        os.path.dirname(os.path.realpath(__file__)), '..', 'config')

    with open(os.path.join(CONFIG_PATH, 'submitty.json')) as open_file:
        SUBMITTY_CONFIG = json.load(open_file)

    with open(os.path.join(CONFIG_PATH, 'database.json')) as open_file:
        DATABASE_CONFIG = json.load(open_file)

except Exception as config_fail_error:
    print("[{}] ERROR: CORE SUBMITTY CONFIGURATION ERROR {}".format(
        str(datetime.datetime.now()), str(config_fail_error)))
    sys.exit(1)


DATA_DIR_PATH = SUBMITTY_CONFIG['submitty_data_dir']
# TODO ~ Create this new directory in logs
NOTIFICATION_LOG_PATH = os.path.join(DATA_DIR_PATH, "logs", "notifications")
TODAY = datetime.datetime.now()
LOG_FILE = open(os.path.join(
    NOTIFICATION_LOG_PATH, "{:04d}{:02d}{:02d}.txt".format(TODAY.year, TODAY.month,
                                                    TODAY.day)), 'a')
try:
    DB_HOST = DATABASE_CONFIG['database_host']
    DB_USER = DATABASE_CONFIG['database_user']
    DB_PASSWORD = DATABASE_CONFIG['database_password']
except Exception as config_fail_error:
    e = "[{}] ERROR: Database Configuration Failed {}".format(
        str(datetime.datetime.now()), str(config_fail_error))
    LOG_FILE.write(e+"\n")
    print(e)
    sys.exit(1)

def setup_db():
    """Set up a connection with the submitty database."""
    db_name = "submitty"
    # If using a UNIX socket, have to specify a slightly different connection string
    if os.path.isdir(DB_HOST):
        conn_string = "postgresql://{}:{}@/{}?host={}".format(
            DB_USER, DB_PASSWORD, db_name, DB_HOST)
    else:
        conn_string = "postgresql://{}:{}@{}/{}".format(
            DB_USER, DB_PASSWORD, DB_HOST, db_name)

    engine = create_engine(conn_string)
    db = engine.connect()
    metadata = MetaData(bind=db)
    return db, metadata

def fetchPendingGradeables(db):
    query = """SELECT * FROM scheduled_notifications WHERE date <= NOW()"""
    result = db.execute(text(query))
    pending_gradeable_notifications = []

    for row in result:
        pending_gradeable_notifications.append({
            'id': row[0],
            'reference_id': row[1],
            'type': row[2],
            'term': row[3],
            'course': row[4],
            'date': row[5]
            })

    return pending_gradeable_notifications

def notifyPendingGradeables(db):
    pending_gradeable_notifications = fetchPendingGradeables(db)

    for gradeable in pending_gradeable_notifications:
        payload = {} # use gradeable data...
        requests.post("http://localhost:1511/courses/course/semester/...", data = payload)
        continue

    if len(pending_gradeable_notifications) >= 1:
        query = "DELETE FROM scheduled_notifications WHERE date <= NOW()"
        db.execute(text(query))

def main():
    try:
        db, metadata = setup_db()
        notifyPendingGradeables(db)
    except Exception as notification_send_error:
        e = "[{}] Error Sending Notification(s): {}".format(
            str(datetime.datetime.now()), str(notification_send_error))
        LOG_FILE.write(e+"\n")
        print(e)

if __name__ == "__main__":
    main()
