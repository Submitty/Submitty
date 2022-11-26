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

    # Find all ids of students and graders
    all_student_ids = [x['user_id'] for x in user_table if x['user_group'] == 4]
    all_grader_ids = [x['user_id'] for x in user_table if x['user_group'] < 4]

    # create a lookup table from user id to name
    name_lookup = dict()
    for x in user_table:
        student_name = ""

        if x["user_preferred_firstname"] != None:
            student_name += x["user_preferred_firstname"]
        else:
            student_name += x["user_firstname"]
        
        student_name += " "

        if x["user_preferred_lastname"] != None:
            student_name += x["user_preferred_lastname"]
        else:
            student_name += x["user_lastname"]
        
        name_lookup[x["user_id"]] = student_name

    # Hardcoding options that we could use
    queue_current_states = ["done", "being_helped", "waiting"]
    queue_removal_types = ["helped", "self", "emptied", "self_helped", "removed"]

    for _ in range(100):
        queue_entry = dict()
        queue_entry["current_state"] = random.choice(queue_current_states)
        
        if queue_entry["current_state"] == "done":
            queue_entry["removal_type"] = random.choice(queue_removal_types)
        else:
            queue_entry["removal_type"] = None
        
        queue_entry["queue_code"] = random.choice(all_queues)
        queue_entry["user_id"] = random.choice(all_student_ids)
        queue_entry["name"] = queue_entry["user_id"]

        # FUTURE ME PLEASE GENERATE RANDOM TIME
        queue_entry["time_in"] = datetime.datetime.now()

        if queue_entry["current_state"] == "done":
            queue_entry["time_out"] = datetime.datetime.now()
        else:
            queue_entry["time_out"] = None

        queue_entry["added_by"] = queue_entry["user_id"]

        if queue_entry["current_state"] == "waiting" or queue_entry["removal_type"] == "self":
            queue_entry["help_started_by"] = None
        else:
            queue_entry["help_started_by"] = random.choice(all_grader_ids)
        
        if queue_entry["removal_type"] == None:
            queue_entry["removed_by"] = None
        elif queue_entry["removal_type"] == "helped":
            # usually the grader who started will finish helping
            if random.random() < 0.9:
                queue_entry["removed_by"] = queue_entry["help_started_by"]
            else:
                queue_entry["removed_by"] = random.choice(all_grader_ids)
        elif queue_entry["removal_type"] == "self" or queue_entry["removal_type"] == "self_helped":
            queue_entry["removed_by"] = queue_entry["user_id"]
        else:
            queue_entry["removed_by"] = random.choice(all_grader_ids)
        
        queue_entry["contact_info"] = queue_entry["user_id"] + "@sample.com"

        # FIX THE TIME LATER
        queue_entry["last_time_in_queue"] = datetime.datetime.now()

        if queue_entry["help_started_by"] != None:
            queue_entry["time_help_start"] = datetime.datetime.now()
        else:
            queue_entry["time_help_start"] = None
        
        # people usually won't be pausing
        if random.random() < 0.9:
            queue_entry["paused"] = False
        else:
            queue_entry["paused"] = True
        
        if queue_entry["paused"]:
            queue_entry["time_paused"] = random.randint(0, 120)
        else:
            queue_entry["time_paused"] = 0
        
        if queue_entry["paused"]:
            queue_entry["time_paused_start"] = datetime.datetime.now()
        else:
            queue_entry["time_paused_start"] = None

    # print("All student ids:", all_student_ids)
    # print("All grader ids:", all_grader_ids)
    # print("All queue codes:", all_queues)



if __name__ == "__main__":
    main()