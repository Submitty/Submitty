#!/usr/bin/env python3

"""
Deletes all expired sessions from the sessions table in the main Submitty database
"""

import database_queries
import datetime
from sqlalchemy import text


def delete_expired_sessions(db):
    """Delete the sessions which have expired."""

    return db.execute(text("""
        WITH deleted AS (
            DELETE FROM sessions
            WHERE session_expires < current_timestamp
            RETURNING *
        ) SELECT COUNT(*) FROM deleted
        """)).fetchone()[0]


def main():
    try:
        db, metadata = database_queries.setup_db()

        print("Deleting expired sessions...")

        result = delete_expired_sessions(db)  # returns a count of the number of sessions deleted

        print(f"Successfully deleted {result} expired sessions\n")

    except Exception as e:
        e_str = f"[{datetime.datetime.now()}] Error while deleting sessions: {e}"
        database_queries.LOG_FILE.write(e_str+"\n")
        print(e_str)


if __name__ == "__main__":
    main()
