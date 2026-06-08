#!/usr/bin/env python3

"""
Handles updating the database with the
autograding testcase details for this gradeable
"""

from sqlalchemy import create_engine, MetaData, insert, delete, exc
import datetime
import os
import sys
import json

try:
    CONFIG_PATH = os.path.join(
        os.path.dirname(os.path.realpath(__file__)), '..', 'config')
    with open(os.path.join(CONFIG_PATH, 'submitty.json')) as submitty_config_file:
        SUBMITTY_CONFIG = json.load(submitty_config_file)
except Exception as config_fail_error:
    print(f"[{datetime.datetime.now()}] \
    	ERROR: CORE SUBMITTY CONFIGURATION ERROR \
    	{config_fail_error}")
    sys.exit(1)

CONFIG_FILE_PATH = sys.argv[1]
SEMESTER = sys.argv[2]
COURSE = sys.argv[3]
GRADEABLE = sys.argv[4]


def setup_db():
    """Set up a connection with the course database."""
    with open(os.path.join(CONFIG_PATH, 'database.json')) as db_config_file:
        db_config = json.load(db_config_file)
    db_name = f"submitty_{SEMESTER}_{COURSE}"
    if os.path.isdir(db_config['database_host']):
        conn_string = "postgresql://{}:{}@/{}?host={}".format(
            db_config['database_user'],
            db_config['database_password'],
            db_name,
            db_config['database_host']
        )
    else:
        conn_string = "postgresql://{}:{}@{}/{}".format(
            db_config['database_user'],
            db_config['database_password'],
            db_config['database_host'],
            db_name
        )
    engine = create_engine(conn_string)
    db = engine.connect()
    metadata = MetaData()
    metadata.reflect(bind=engine)
    return db, metadata


def send_data(db, metadata, testcases):
    """
    If testcase entries already exist for this gradeable, delete them all
    and re-insert fresh ones.
    """
    testcase_table = metadata.tables['autograding_testcase']
    existing = db.execute(
        testcase_table.select().where(testcase_table.c.g_id == GRADEABLE)
    ).fetchall()
    if existing:
        print(f"Rebuilding gradeable '{GRADEABLE}': removing {len(existing)} existing testcase(s).")
        db.execute(
            delete(testcase_table).where(testcase_table.c.g_id == GRADEABLE)
        )
    for order, testcase in enumerate(testcases):
        db.execute(
            insert(testcase_table).values(
                g_id=GRADEABLE,
                testcase_id=testcase['testcase_id'],
                testcase_order=order,
                hidden=testcase.get('hidden', False),
                extra_credit=testcase.get('extra_credit', False),
                points_possible=testcase.get('points', 0)
            )
        )
    db.commit()
    print(f"Inserted {len(testcases)} testcase(s) for gradeable '{GRADEABLE}'.")


def main():
    with open(CONFIG_FILE_PATH) as config_file:
        config_data = json.loads(config_file.read())
    try:
        db, metadata = setup_db()
        send_data(db, metadata, config_data['testcases'])
    except exc.IntegrityError as e:
        print(f"ERROR: IntegrityError - {e}")
        sys.exit(1)


if __name__ == "__main__":
    main()
