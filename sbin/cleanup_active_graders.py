#!/usr/bin/env python3

"""
Deletes old entries from the active_graders table in all course databases.
This script is intended to be run periodically (e.g. by cron) to cleanup stale locks.
"""

import database_queries
from sqlalchemy import text
from sqlalchemy.exc import SQLAlchemyError


def _cleanup_course(db_name):
    """Delete stale active_graders rows (> 24 hours) from one course database."""
    try:
        course_conn = database_queries.setup_course_db(db_name)
    except SQLAlchemyError as e:
        print(f"Error connecting to course database {db_name}: {e}")
        return 0

    # "timestamp" is quoted because it is a reserved word in SQL
    delete_query = text("""
        DELETE FROM active_graders
        WHERE timestamp < NOW() - INTERVAL '1 day'
    """)

    result = course_conn.execute(delete_query)
    course_conn.commit()
    course_conn.close()
    return result.rowcount


def main():
    """Connect to all active course databases and remove stale active_graders locks."""
    print("Starting cleanup of active_graders...")

    # 1. Connect to Master DB to get list of courses
    try:
        master_conn, _ = database_queries.setup_db()
    except SQLAlchemyError as e:
        print(f"Error connecting to master database: {e}")
        return

    # 2. Get list of active courses (status=1 means the course is active)
    try:
        query = text("SELECT term, course FROM courses WHERE status=1")
        result = master_conn.execute(query)
    except SQLAlchemyError as e:
        print(f"Error fetching courses: {e}")
        return

    master_conn.close()

    count = 0

    for row in result:
        try:
            term = row.term
            course = row.course
        except AttributeError:
            term = row[0]
            course = row[1]

        db_name = f"submitty_{term}_{course}"

        try:
            c = _cleanup_course(db_name)
            if c > 0:
                print(f"Cleaned up {c} stale grader(s) from course {db_name}")
            count += 1
        except SQLAlchemyError as e:
            print(f"Failed to cleanup {db_name}: {e}")

    print(f"Finished active_graders cleanup. Processed {count} courses.")


if __name__ == "__main__":
    main()
