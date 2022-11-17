#!/usr/bin/env python3

import argparse
from sqlalchemy import create_engine, Table, MetaData, bindparam, select, join, func
import random
import os
import datetime

DB_HOST = "localhost"
DB_PORT = 5432
DB_USER = "submitty_dbuser"
DB_PASS = "submitty_dbuser"

def main():

    # get today's date to determine date and time
    today = datetime.datetime.today()
    year = str(today.year)
    if today.month < 7:
        term_id = "s" + year[-2:]
    else:
        term_id = "f" + year[-2:]
    
    database = "submitty_" + term_id + "_sample"
    engine = create_engine("postgresql:///{}?host={}&port={}&user={}&password={}"
                           .format(database, DB_HOST, DB_PORT, DB_USER, DB_PASS))
    conn = engine.connect()
    metadata = MetaData(bind=engine)
    # queues_table = Table("queue_settings", metadata, autoload=True)
    # queue_entries_table = Table("queue", metadata, autoload=True)

    # Find all queues in class
    res = conn.execute("SELECT code FROM queue_settings")
    tmp = res.fetchall()
    all_queues = [x[0] for x in tmp]
    res.close()

    # Find all students in class
    res = conn.execute("SELECT * from users")
    user_table = res.fetchall()
    res.close()

    all_student_ids = [x['user_id'] for x in user_table if x['user_group'] == 4]
    all_grader_ids = [x['user_id'] for x in user_table if x['user_group'] < 4]

    print(all_student_ids)
    print(all_grader_ids)
    print(all_queues)



if __name__ == "__main__":
    main()