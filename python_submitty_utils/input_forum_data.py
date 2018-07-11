#!/usr/bin/env python3

import os
import sys
import json
from datetime import datetime

usr_path = "/usr/local/submitty"

settings = json.load(open(os.path.join(usr_path, ".setup", "submitty_conf.json")))

possible_databases = ['submitty_f18_blank', 'submitty_f18_development', 'submitty_f18_sample', 'submitty_f18_tutorial']

database = 'submitty_f18_blank'

if(len(sys.argv) == 2 and sys.argv[1] not in possible_databases):
    database = sys.argv[1]


print("WARNING: This tool is going to delete data from the following tables:\n\tthreads\n\tposts\n\tviewed_responses\n\tthread_categories\n\tcategories_list")

answer = input("Do you agree for this data to be removed from {:s}? [yes/no]: ".format(database)).strip()

if(answer.lower() != "yes"):
    print("Exiting...")
    sys.exit()

variables = (settings['database_password'], settings['database_host'], settings['database_user'], database)

os.system("""PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c \"TRUNCATE TABLE threads RESTART IDENTITY CASCADE\"""".format(*variables))

os.system("""PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c \"DELETE FROM thread_categories\"""".format(*variables))

os.system("""PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c \"TRUNCATE TABLE categories_list RESTART IDENTITY CASCADE\"""".format(*variables))

os.system("""PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c \"INSERT INTO categories_list (category_desc) VALUES ('TESTDATA')\"""".format(*variables))

for i in range(1000):
        os.system("""PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c \"INSERT INTO threads (title, created_by, pinned, deleted, merged_thread_id, merged_post_id, is_visible) VALUES (\'{:s}\', \'{:s}\', false, false, -1, -1, true)\"""".format(*variables, "Thread{:d}".format(i+1), "aphacker"))
        os.system("""PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c \"INSERT INTO thread_categories (thread_id, category_id) VALUES ({:d}, 1)\"""".format(*variables, i+1))
        for pid in range(20):
                os.system("""PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c \"INSERT INTO posts (thread_id, parent_id, author_user_id, content, timestamp, anonymous, deleted, resolved, type, has_attachment) VALUES ({}, {}, {}, {}, \'{}\', false, false, false, 0, false)\"""".format(*variables, i+1, -1 if pid == 0 else i*20 + pid, "'aphacker'", "'Post{:d}'".format(i*20 + pid+1), datetime.now()))


