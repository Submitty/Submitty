#!/usr/bin/env python3

import os
import sys
import json
from datetime import datetime

from submitty_utils import dateutils


def generatePossibleDatabases():
    current = dateutils.get_current_semester()
    pre = 'submitty_' + current + '_'
    path = "/var/local/submitty/courses/" + current
    return [pre + name for name in sorted(os.listdir(path)) if os.path.isdir(path + "/" + name)]


if(__name__ == "__main__"):
    num_args = len(sys.argv)
    possible_databases = generatePossibleDatabases()

    database = possible_databases[0]

    if(num_args > 2):
        print('Too many arguments. Use --help for help.')
        sys.exit()
    elif(num_args == 2):
        if(sys.argv[1] == '--help' or sys.argv[1] == '-h'):
            print('This tool can be used to test forum scalability -- pg_dump after execution to save the test data which can be sourced later.')
            print('This tool takes in an optional argument: database, so an example usage is: `python3 input_forum_data.py submitty_f18_blank`')
            print('Note this will delete forum data in the database you specify. The database will default to `submitty_f18_blank` if not specified.')
            sys.exit()
        elif(sys.argv[1] not in possible_databases):
            print('Unknown argument: {:s}, use --help or -h for help.'.format(sys.argv[1]))
            sys.exit()
        database = sys.argv[1]

    threads = abs(int(input("Enter number of threads (i.e. 1000): ").strip()))
    posts = abs(int(input("Enter number of posts per thread (i.e. 20): ").strip()))

    usr_path = "/usr/local/submitty"

    settings = json.load(open(os.path.join(usr_path, ".setup", "submitty_conf.json")))

    print("WARNING: This tool is going to delete data from the following tables:\n\tthreads\n\tposts\n\tforum_posts_history\n\tstudent_favorites\n\tviewed_responses\n\tthread_categories\n\tcategories_list")

    answer = input("Do you agree for this data to be removed from {:s}? [yes/no]: ".format(database)).strip()

    if(answer.lower() != "yes"):
        print("Exiting...")
        sys.exit()

    variables = (settings['database_password'], settings['database_host'], settings['database_user'], database)

    os.system("""PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c \"TRUNCATE TABLE threads RESTART IDENTITY CASCADE\" > /dev/null""".format(*variables))

    os.system("""PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c \"DELETE FROM thread_categories\" > /dev/null""".format(*variables))

    os.system("""PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c \"TRUNCATE TABLE categories_list RESTART IDENTITY CASCADE\" > /dev/null""".format(*variables))

    os.system("""PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c \"INSERT INTO categories_list (category_desc) VALUES ('TESTDATA')\" > /dev/null""".format(*variables))

    print()

    for i in range(threads):
        if((i+1) % 10 == 0):
            print("Completed: {:d}/{:d}".format(i+1, threads))
        os.system("""PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c \"INSERT INTO threads (title, created_by, pinned, deleted, merged_thread_id, merged_post_id, is_visible) VALUES (\'{:s}\', \'{:s}\', false, false, -1, -1, true)\" > /dev/null""".format(*variables, "Thread{:d}".format(i+1), "aphacker"))
        os.system("""PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c \"INSERT INTO thread_categories (thread_id, category_id) VALUES ({:d}, 1)\" > /dev/null""".format(*variables, i+1))
        for pid in range(posts):
            os.system("""PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c \"INSERT INTO posts (thread_id, parent_id, author_user_id, content, timestamp, anonymous, deleted, type, has_attachment) VALUES ({}, {}, {}, {}, \'{}\', false, false, 0, false)\" > /dev/null""".format(*variables, i+1, -1 if pid == 0 else i*posts + pid, "'aphacker'", "'Post{:d}'".format(i*posts + pid+1), datetime.now()))
