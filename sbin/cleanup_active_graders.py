#!/usr/bin/env python3

"""
Deletes old entries from the active_graders table in all course databases.
This script is intended to be run periodically (e.g. by cron) to cleanup stale locks.
"""

import database_queries
from sqlalchemy import create_engine, text
import os


def main():
    print("Starting cleanup of active_graders...")

    # 1. Connect to Master DB to get list of courses
    try:
        master_conn, _ = database_queries.setup_db()
    except Exception as e:
        print(f"Error connecting to master database: {e}")
        return

    # 2. Get list of active courses
    # status=1 usually implies the course is valid/active.
    try:
        query = text("SELECT term, course FROM courses WHERE status=1")
        result = master_conn.execute(query)
    except Exception as e:
        print(f"Error fetching courses: {e}")
        return

    # We need database credentials to connect to course DBs
    db_user = database_queries.DB_USER
    db_pass = database_queries.DB_PASSWORD
    db_host = database_queries.DB_HOST

    count = 0

    for row in result:
        # Handle row access safely
        try:
            term = row.term
            course = row.course
        except AttributeError:
            term = row[0]
            course = row[1]

        # 3. Construct Course DB Connection String
        db_name = f"submitty_{term}_{course}"

        # Logic adapted from database_queries.setup_db
        if os.path.isdir(db_host):
            conn_string = "postgresql://{}:{}@/{}?host={}".format(
                db_user, db_pass, db_name, db_host)
        else:
            conn_string = "postgresql://{}:{}@{}/{}".format(
                db_user, db_pass, db_host, db_name)

        try:
            engine = create_engine(conn_string)
            course_conn = engine.connect()

            # 4. Execute Delete
            # Using 48 hours as the threshold
            # "timestamp" is quoted because it is a reserved word in SQL
            delete_query = text("""
                DELETE FROM active_graders
                WHERE "timestamp" < NOW() - INTERVAL '2 day'
            """)
            course_conn.execute(delete_query)
            course_conn.close()
            count += 1

        except Exception as e:
            print(f"Failed to cleanup {db_name}: {e}")

    print(f"Finished active_graders cleanup. Processed {count} courses.")


if __name__ == "__main__":
    main()
