#!/usr/bin/env python3

"""
This script creates a database connection to be used in other misc scripts
"""

import json
import os
import datetime
from sqlalchemy import create_engine, MetaData
import sys

from submitty_utils import db_utils


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
    DB_PORT = DATABASE_CONFIG.get('database_port', 5432)
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
    conn_string = db_utils.generate_connect_string(
        DB_HOST, DB_PORT, db_name, DB_USER, DB_PASSWORD)

    engine = create_engine(conn_string)
    db = engine.connect()
    metadata = MetaData()
    return db, metadata
