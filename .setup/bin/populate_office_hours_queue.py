#!/usr/bin/env python3

import argparse
from sqlalchemy import create_engine, Table, MetaData, select, update, insert, func
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
    queues_table = Table("queue_settings", metadata, autoload=True)
    queue_entries_table = Table("queue", metadata, autoload=True)
    users_table = Table("users", metadata, autoload=True)

    # Find all queues in class
    res = conn.execute(select(queues_table))
    queues_lookup = {x["code"]: x for x in res}
    res.close()

    all_queues = [x for x in queues_lookup if queues_lookup[x]["open"]]

    # if there are no open queues, don't populate anything
    if len(all_queues) == 0:
        return

    # Find all students in class
    res = conn.execute(select(users_table))
    all_users = res.fetchall()
    res.close()

    # Find all ids of students and graders
    all_student_ids = [x['user_id'] for x in all_users if x['user_group'] == 4]
    all_grader_ids = [x['user_id'] for x in all_users if x['user_group'] < 4]

    # create a lookup table from user id to name
    name_lookup = dict()
    for x in all_users:
        user_name = ""

        if x["user_preferred_firstname"] != None:
            user_name += x["user_preferred_firstname"]
        else:
            user_name += x["user_firstname"]
        
        user_name += " "

        if x["user_preferred_lastname"] != None:
            user_name += x["user_preferred_lastname"]
        else:
            user_name += x["user_lastname"]
        
        name_lookup[x["user_id"]] = user_name

    # To make it easier to code, we will empty everyone from the queue
    # something to do with updating queue_entries_table
    update_dict = dict()
    update_dict["current_state"] = "done"
    update_dict["removal_type"] = "emptied"
    update_dict["time_out"] = datetime.datetime.now()
    update_dict["removed_by"] = random.choice(all_grader_ids)

    update_query = update(queue_entries_table)
    update_query = update_query.values(update_dict)
    update_query = update_query.where(queue_entries_table.c.current_state != "done")

    # TODO: maybe "finish help" if student is in queue instead of straight out
    # emptying the queue
    conn.execute(update_query)

    # Hardcoding options that we could use
    queue_current_states = ["done", "being_helped", "waiting"]
    queue_removal_types = ["helped", "self", "emptied", "self_helped", "removed"]

    queue_data = []
    # start generating queue data
    for _ in range(10):
        # since we are removing students from our list, we have to break
        # from the loop when we have no more options left
        if len(all_student_ids) == 0:
            break

        queue_entry = dict()
        queue_entry["current_state"] = random.choice(queue_current_states)
        
        if queue_entry["current_state"] == "done":
            queue_entry["removal_type"] = random.choice(queue_removal_types)
        else:
            queue_entry["removal_type"] = None
        
        queue_entry["queue_code"] = random.choice(all_queues)
        queue_entry["user_id"] = random.choice(all_student_ids)
        # Once again to make it easier to code, each student can only join once
        all_student_ids.remove(queue_entry["user_id"])
        queue_entry["name"] = name_lookup[queue_entry["user_id"]]

        time_in = random.randint(300, 600)
        queue_entry["time_in"] = datetime.datetime.now() - datetime.timedelta(seconds = time_in)

        time_out = random.randint(0, 300)
        if queue_entry["current_state"] == "done":
            queue_entry["time_out"] = datetime.datetime.now() - datetime.timedelta(seconds = time_out)
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
        
        if queues_lookup[queue_entry["queue_code"]]["contact_information"]:
            queue_entry["contact_info"] = queue_entry["user_id"] + "@sample.com"
        else:
            queue_entry["contact_info"] = None

        # FIX: GET ACCURATE TIME
        res = conn.execute("SELECT max(time_in) FROM queue WHERE user_id = '{}' AND UPPER(TRIM(queue_code)) = UPPER(TRIM('{}')) AND (removal_type IN ('helped', 'self_helped') OR help_started_by IS NOT NULL)".format(queue_entry["user_id"], queue_entry["queue_code"]))
        queue_entry["last_time_in_queue"] = res.fetchall()[0][0]
        res.close()

        if queue_entry["help_started_by"] != None:
            time_start = random.randint(time_out, time_in)
            queue_entry["time_help_start"] = datetime.datetime.now() - datetime.timedelta(seconds = time_start)
        else:
            queue_entry["time_help_start"] = None
        
        # FIX: REWRITE THIS WHOLE PART TO ACCURATELY REFLECT OH QUEUE BEHAVIORS
        # people usually won't be pausing
        # if random.random() < 0.9:
        #     queue_entry["paused"] = False
        # else:
        #     queue_entry["paused"] = True
        queue_entry["paused"] = False

        queue_entry["time_paused"] = 0

        queue_entry["time_paused_start"] = None

        queue_data.append(queue_entry)
    
    conn.execute(insert(queue_entries_table).values(queue_data))

    # print("All student ids:", all_student_ids)
    # print("All grader ids:", all_grader_ids)
    # print("All queue codes:", all_queues)



if __name__ == "__main__":
    main()