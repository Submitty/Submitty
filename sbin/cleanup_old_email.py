#!/usr/bin/env python3

"""
Cleanup old email from main Submitty database emails table.

This script takes 2 optional arguments:
 - the number of days of sent email records to preserve (default 360)
 - the maximum number of emails to delete per call to this script (default 1,000)
"""

import json
import os
import datetime
from sqlalchemy import create_engine, MetaData, text
import sys


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
EMAIL_LOG_PATH = os.path.join(DATA_DIR_PATH, "logs", "emails")
TODAY = datetime.datetime.now()
LOG_FILE = open(os.path.join(
    EMAIL_LOG_PATH, "{:04d}{:02d}{:02d}.txt".format(TODAY.year, TODAY.month,
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


def delete_old_emails(db, days_to_preserve, maximum_to_delete):
    """Collect the emails to be deleted and information about errors and unsent email."""

    query = """SELECT count(*) FROM emails;"""
    result = db.execute(text(query))
    for row in result:
        print(f"total email count: {row[0]}")

    query = """SELECT count(*) FROM emails where error != '';"""
    result = db.execute(text(query))
    for row in result:
        error_count = row[0]
        print(f"error email count: {row[0]}")

    if error_count > 0:
        print(f"WARNING: {error_count} unsent emails in database WITH ERRORS.")

    query = """SELECT count(*) FROM emails where sent is NULL AND error = '';"""
    result = db.execute(text(query))
    for row in result:
        unsent_count = row[0]
        print(f"unsent email count: {row[0]}")

    if unsent_count > 0:
        print(f"WARNING: {unsent_count} UNSENT emails in database without errors.")

    last_week = str(TODAY - datetime.timedelta(days=days_to_preserve))

    query = """SELECT count(*) FROM emails WHERE sent is not NULL
    AND sent < :format AND error = '';"""
    result = db.execute(text(query), format=last_week)
    for row in result:
        before = row[0]
        print(f"email to delete before count: {row[0]}")

    if before == 0:
        print("Nothing to delete, exiting\n")
        return

    query = """delete from emails WHERE ctid in (select ctid from emails
    where sent is not NULL AND sent < :format AND error = '' LIMIT :foo);"""
    result = db.execute(text(query), format=last_week, foo=str(maximum_to_delete))

    query = """SELECT count(*) FROM emails WHERE sent is not NULL
    AND sent < :format AND error = '';"""
    result = db.execute(text(query), format=last_week)
    for row in result:
        after = row[0]
        print(f"email to delete after count: {row[0]}")

    print(f"deleted email count {before-after}\n")


def main():
    try:
        db, metadata = setup_db()

        print("\nChecking Submitty Database Emails Table")

        days_to_preserve = 360
        if len(sys.argv) > 1:
            days_to_preserve = int(sys.argv[1])
        if (days_to_preserve < 7):
            print("ERROR: Should preserve at least 1 week of email")
            return
        print(f"preserving {days_to_preserve} days of email")

        maximum_to_delete = 1000
        if len(sys.argv) > 2:
            maximum_to_delete = int(sys.argv[2])
        if (maximum_to_delete < 10 or maximum_to_delete > 100000):
            print("ERROR: maximum to delete should be between 10 and 100000")
            return
        print(f"deleting at most {maximum_to_delete} emails")

        delete_old_emails(db, days_to_preserve, maximum_to_delete)

    except Exception as email_send_error:
        e = "[{}] Error Sending Email: {}".format(
            str(datetime.datetime.now()), str(email_send_error))
        LOG_FILE.write(e+"\n")
        print(e)


if __name__ == "__main__":
    main()
