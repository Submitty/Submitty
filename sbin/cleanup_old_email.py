#!/usr/bin/env python3

"""
Cleanup old email from main Submitty database emails table.

This script takes 2 optional arguments:
 - the number of days of sent email records to preserve (default 360)
 - the maximum number of emails to delete per call to this script (default 1,000)
"""

import database_queries
import datetime
import sys
from sqlalchemy import text


def delete_old_emails(db, days_to_preserve, maximum_to_delete):
    """Collect the emails to be deleted and information about errors and unsent email."""

    query = """SELECT count(*) FROM emails;"""
    result = db.execute(text(query))
    print(f"total email count: {result.scalar() or 0}")

    query = """SELECT count(*) FROM emails where error != '';"""
    result = db.execute(text(query))
    error_count = result.scalar() or 0
    print(f"error email count: {error_count}")

    if error_count > 0:
        print(f"WARNING: {error_count} unsent emails in database WITH ERRORS.")

    query = """SELECT count(*) FROM emails where sent is NULL AND error = '';"""
    result = db.execute(text(query))
    unsent_count = result.scalar() or 0
    print(f"unsent email count: {unsent_count}")

    if unsent_count > 0:
        print(f"WARNING: {unsent_count} UNSENT emails in database without errors.")

    last_week = str(datetime.datetime.now() - datetime.timedelta(days=days_to_preserve))

    query = """SELECT count(*) FROM emails WHERE sent is not NULL
    AND sent < :format AND error = '';"""
    result = db.execute(text(query), {"format": last_week})
    before = result.scalar() or 0
    print(f"email to delete before count: {before}")

    if before == 0:
        print("Nothing to delete, exiting\n")
        return

    query = """delete from emails WHERE ctid in (select ctid from emails
    where sent is not NULL AND sent < :format AND error = '' LIMIT :foo);"""
    result = db.execute(text(query), {"format": last_week, "foo": str(maximum_to_delete)})
    db.commit()

    query = """SELECT count(*) FROM emails WHERE sent is not NULL
    AND sent < :format AND error = '';"""
    result = db.execute(text(query), {"format": last_week})
    after = result.scalar() or 0
    print(f"email to delete after count: {after}")

    print(f"deleted email count {before-after}\n")


def main():
    try:
        db, metadata = database_queries.setup_db()

        print("\nChecking Submitty Database Emails Table")

        days_to_preserve = 360
        if len(sys.argv) > 1:
            days_to_preserve = int(sys.argv[1])
        if (days_to_preserve < 7):
            print("ERROR: Should preserve at least 1 week of email")
            return
        print(f"preserving {days_to_preserve} days of email")

        maximum_to_delete = 1000
        if len(sys.argv) > 2:
            maximum_to_delete = int(sys.argv[2])
        if (maximum_to_delete < 10 or maximum_to_delete > 100000):
            print("ERROR: maximum to delete should be between 10 and 100000")
            return
        print(f"deleting at most {maximum_to_delete} emails")

        delete_old_emails(db, days_to_preserve, maximum_to_delete)

    except Exception as email_send_error:
        e = "[{}] Error Sending Email: {}".format(
            str(datetime.datetime.now()), str(email_send_error))
        database_queries.LOG_FILE.write(e+"\n")
        print(e)


if __name__ == "__main__":
    main()
