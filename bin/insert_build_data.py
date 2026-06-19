#!/usr/bin/env python3

"""
Handles updating the database with the
autograding testcase details for this gradeable
"""

from sqlalchemy import create_engine, MetaData, insert, delete, exc, Table 	# pylint: disable=import-error
import datetime
import os
import sys
import json

try:
    CONFIG_PATH = os.path.join(
        os.path.dirname(os.path.realpath(__file__)), '..', 'config')
    with open(os.path.join(CONFIG_PATH, 'submitty.json')) as submitty_config_file:
        SUBMITTY_CONFIG = json.load(submitty_config_file)
except Exception as config_fail_error:					# pylint: disable=broad-exception-caught
    print(f"[{datetime.datetime.now()}] \
    ERROR: CORE SUBMITTY CONFIGURATION ERROR \
    s{config_fail_error}")
    sys.exit(1)

CONFIG_FILE_PATH = sys.argv[1]
GRADEABLE = sys.argv[2]
SEMESTER = sys.argv[3]
COURSE = sys.argv[4]


def setup_db():
    """Set up a connection with the course database."""
    with open(os.path.join(CONFIG_PATH, 'database.json')) as open_file:
        db_config = json.load(open_file)
    db_name = f"submitty_{SEMESTER}_{COURSE}"
    # If using a UNIX socket, have to specify a slightly different connection string
    if os.path.isdir(db_config['database_host']):
        conn_string = "postgresql://{}:{}@/{}?host={}".format(		# pylint: disable=consider-using-f-string
            db_config['database_user'],
            db_config['database_password'],
            db_name,
            db_config['database_host']
        )
    else:
        conn_string = "postgresql://{}:{}@{}/{}".format(			# pylint: disable=consider-using-f-string
            db_config['database_user'],
            db_config['database_password'],
            db_config['database_host'],
            db_name
        )

    engine = create_engine(conn_string)
    db = engine.connect()
    metadata = MetaData()
    return db, metadata, engine


def send_data(db, metadata, engine, testcases):
    """
    If testcase entries already exist for this gradeable and specifications are met,
    delete them all and re-insert fresh ones.
    """
    testcase_table = Table('autograding_testcase', metadata, autoload_with=engine)
    existing = db.execute(
        testcase_table.select()
        .where(testcase_table.c.g_id == GRADEABLE)
        .order_by(testcase_table.c.testcase_order)
    ).fetchall()
    # rollback the select before db.begin
    db.rollback()

    if check_invalidated(existing, testcases):
        print(f"Rebuilding gradeable '{GRADEABLE}': removing {len(existing)} existing testcase(s).")
        with db.begin():
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
        print(f"Inserted {len(testcases)} testcase(s) for gradeable '{GRADEABLE}'.")
    else:
        print(f"Gradeable '{GRADEABLE}' is up to date, skipping database insertion.")


def check_invalidated(existing_rows, testcases):
    """
    Check for invalidated autograding (some gradeable rebuilds are small enough
    that autograding shouldn't be deleted) i.e. points per testcase is altered,
    number of testcases is altered, etc.
    """

    if len(existing_rows) != len(testcases):
        return True

    for i, (db_row, new_tc) in enumerate(zip(existing_rows, testcases)):
        if (
            db_row.testcase_id != new_tc.get('testcase_id')
            or db_row.testcase_order != i
            or db_row.hidden != new_tc.get('hidden', False)
            or db_row.extra_credit != new_tc.get('extra_credit', False)
            or db_row.points_possible != new_tc.get('points', 0)
        ):
            return True

    return False


def main():
    with open(CONFIG_FILE_PATH) as config_file:
        config_data = json.loads(config_file.read())
    try:
        db, metadata, engine = setup_db()
        send_data(db, metadata, engine, config_data['testcases'])
        db.close()
        engine.dispose()
    except exc.IntegrityError as e:
        print(f"ERROR: IntegrityError - {e}")
        db.close()
        engine.dispose()
        sys.exit(1)


if __name__ == "__main__":
    main()
