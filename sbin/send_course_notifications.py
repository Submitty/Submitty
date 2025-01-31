"""
Script to be called as cron job to release notification regarding released
gradeable scores.This script scans over all current courses, determining
if any current electronic gradeable with student view has a grade release
date in the past with no notification sent to students.
"""
import json
import os
import datetime
import sys
import getpass
from sqlalchemy import create_engine  # pylint: disable=import-error

try:
    CONFIG_PATH = os.path.join(
        os.path.dirname(os.path.realpath(__file__)), "..", "config"
    )
    with open(os.path.join(CONFIG_PATH, "submitty_users.json"),
              "r", encoding="utf-8") as file:
        USER_DATA = json.load(file)

        # Confirm that submitty_daemon user is running this script
        if USER_DATA["daemon_user"] != getpass.getuser():
            raise RuntimeError("- script must be run by the daemon user")
    with open(os.path.join(CONFIG_PATH, "submitty.json"),
              encoding="utf-8") as open_file:
        SUBMITTY_CONFIG = json.load(open_file)

    with open(os.path.join(CONFIG_PATH, "database.json"),
              encoding="utf-8") as open_file:
        DATABASE_CONFIG = json.load(open_file)

    DB_HOST = DATABASE_CONFIG["database_host"]
    DB_USER = DATABASE_CONFIG["database_user"]
    DB_PASSWORD = DATABASE_CONFIG["database_password"]
except Exception as config_fail_error:  # pylint: disable=broad-except
    print(
        f"[{datetime.datetime.now()}] ERROR: CORE SUBMITTY CONFIGURATION ERROR"
        + {config_fail_error}
    )
    sys.exit(1)

DATA_DIR_PATH = SUBMITTY_CONFIG["submitty_data_dir"]
BASE_URL_PATH = SUBMITTY_CONFIG["submission_url"]
NOTIFICATION_LOG_PATH = os.path.join(DATA_DIR_PATH, "logs", "notifications")
TODAY = datetime.datetime.now()
LOG_FILE = open(  # pylint: disable=consider-using-with
    os.path.join(
        NOTIFICATION_LOG_PATH,
        f"{TODAY.year:04d}{TODAY.month:02d}{TODAY.day:02d}.txt",
    ),
    "a",
    encoding="utf-8"
)


def connect_db(db_name):
    """Set up a connection with the specific database."""
    if os.path.isdir(DB_HOST):
        connection = (f"postgresql://{DB_USER}:{DB_PASSWORD}@/{db_name}"
                      f"?host={DB_HOST}")
    else:
        connection = (f"postgresql://{DB_USER}:{DB_PASSWORD}@{DB_HOST}"
                      f"/{db_name}")

    engine = create_engine(connection)
    db = engine.connect()
    return db


def notify_pending_gradeables():
    """Send pending gradeable notifications, if any."""
    master_db = connect_db("submitty")
    course_query = "SELECT term, course FROM courses WHERE status = '1';"
    courses = master_db.execute(course_query)
    total_notified_gradeables = 0

    for term, course in courses:
        course_db = connect_db(f"submitty_{term}_{course}")
        notified_gradeables = []

        gradeables = course_db.execute(
            """
            SELECT gradeable.g_id, gradeable.g_title
            FROM electronic_gradeable
            JOIN gradeable ON gradeable.g_id =  electronic_gradeable.g_id
            WHERE gradeable.g_grade_released_date <= NOW()
            AND electronic_gradeable.eg_student_view = true
            AND gradeable.g_notification_sent = false;
        """
        )
        for row in gradeables:
            gradeable = {"id": row[0], "title": row[1]}
            timestamp = str(datetime.datetime.now())

            # Construct gradeable URL into valid JSON string
            gradeable_url = (f"{BASE_URL_PATH}/courses/{term}/{course}"
                             f"/gradeable/{gradeable['id']}")
            metadata = json.dumps({"url": gradeable_url})

            # Send out notifications
            notification_list = []
            notification_content = "Grade Released: " + gradeable["title"]
            if len(notification_content) > 40:
                # Max length for content of notification is 40
                notification_content = notification_content[:36] + "..."
            notification_recipients = course_db.execute(
                """
                SELECT users.user_id , users.user_email
                FROM users
                JOIN notification_settings
                ON notification_settings.user_id = users.user_id
                WHERE all_released_grades = true;
            """
            )
            for recipient in notification_recipients:
                user_id = recipient[0]
                values = (f"('grading','{metadata}','{notification_content}',"
                          f"'{timestamp}','submitty-admin','{user_id}')")
                notification_list.append(values)
            # Send notifications to all potential recipients
            if len(notification_list) > 0:
                course_db.execute(
                    f"""INSERT INTO notifications
                    (component, metadata, content, created_at, from_user_id,
                    to_user_id)
                    VALUES {", ".join(notification_list)};"""
                )

            # Send out emails using both course and master database
            email_list = []
            email_subject = (f"[Submitty {course}] Grade Released: "
                             f"{gradeable['title']}")

            email_body = (f"An Instructor has released scores in:\n{course}"
                          f"\n\nScores have been released for "
                          f"{gradeable['title']}.\n\nAuthor: System\n"
                          f"Click here for more info: {gradeable_url}\n\n"
                          "--\n"
                          "NOTE: This is an automated email notification, "
                          "which is unable to receive replies.\n"
                          "Please refer to the course syllabus for contact "
                          "information for your teaching staff.")

            email_recipients = course_db.execute(
                """
                SELECT users.user_id , users.user_email
                FROM users
                JOIN notification_settings
                ON notification_settings.user_id = users.user_id
                WHERE notification_settings.all_released_grades_email = true;
            """
            )

            for recipient in email_recipients:
                user_id, user_email = recipient[0], recipient[1]
                email_list.append(
                    f"('{email_subject}', '{email_body}', '{timestamp}', "
                    f"'{user_id}', '{user_email}', '{term}', '{course}')"
                )

            if len(email_list) > 0:
                master_db.execute(
                    f"""INSERT INTO emails
                    (subject, body, created, user_id, email_address, term,
                    course)
                    VALUES {", ".join(email_list)};"""
                )

            # Add successfully notified gradeables to update state
            notified_gradeables.append(f"'{gradeable['id']}'")

        # Update all successfully sent notifications for current course
        if len(notified_gradeables) > 0:
            course_db.execute(
                f"""UPDATE gradeable SET g_notification_sent = true
                WHERE g_id in ({", ".join(notified_gradeables)})"""
            )
            total_notified_gradeables += 1

        # Close the course database connection
        course_db.close()
    return total_notified_gradeables


def main():
    """Driver method to release pending notifications for gradeables"""
    try:
        notified = notify_pending_gradeables()
        m = (f"[{datetime.datetime.now()}] Successfully released {notified} "
             f"gradeable notification{'s' if notified != 1 else ''}")
        LOG_FILE.write(m+"\n")
    except Exception as notification_error:  # pylint: disable=broad-except
        m = (f"[{datetime.datetime.now()}] Error Sending Notification(s): "
             f"{str(notification_error)}")
        LOG_FILE.write(m + "\n")
        print(m)


if __name__ == "__main__":
    main()
