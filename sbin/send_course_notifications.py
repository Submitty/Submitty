"""
Handles generating course-related notifications in Submitty.

This is done by scanning each course database for related actions,
such as releasing grade notifications.
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
        # submitty_daemon user authentication
        USER_DATA = json.load(file)

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
COURSES_PATH = os.path.join(DATA_DIR_PATH, 'courses')
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


def notify_gradeable_scores():
    """Send gradeable notifications for released scores, if any."""
    notified = 0
    master_db = connect_db("submitty")
    active_courses_query = "SELECT term, course FROM courses WHERE status = '1';"
    courses = master_db.execute(active_courses_query)

    for term, course in courses:
        course_db = connect_db(f"submitty_{term}_{course}")
        notified_gradeables = []
        course_config_path = os.path.join(
            COURSES_PATH, term, course, 'config', 'config.json')
        course_name = course.strip().upper()

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

        # Following still needs to fix version conflicts
        available_grades = course_db.execute(
            """
            SELECT
                g.g_id AS g_id,
                g.g_title AS g_title,
                egv.user_id AS user_id,
                eg.eg_use_ta_grading AS eg_use_ta_grading,
                egd.autograding_complete AS autograding,
                egv.active_version AS active_version,
                gcd.gcd_graded_version AS gcd_graded_version
            FROM gradeable AS g
            INNER JOIN electronic_gradeable AS eg
                ON g.g_id = eg.g_id
            INNER JOIN electronic_gradeable_version AS egv
                ON g.g_id = egv.g_id
            INNER JOIN gradeable_component AS gc
                ON g.g_id = gc.g_id
            LEFT JOIN gradeable_component_data AS gcd
                ON gc.gc_id = gcd.gc_id
            LEFT JOIN electronic_gradeable_data AS egd
                ON gc.g_id = egd.g_id
            WHERE g.g_grade_released_date <= NOW()
                AND eg.eg_student_view = TRUE
                AND COALESCE(egd.autograding_complete, TRUE)
            GROUP BY g.g_id, g.g_title, egv.user_id, eg.eg_use_ta_grading, egd.autograding_complete, egv.active_version, gcd.gcd_graded_version, egd.g_version
            HAVING
                (COUNT(DISTINCT gc.gc_id) = COUNT(DISTINCT gcd.gc_id) OR (eg.eg_use_ta_grading = 'false' AND egd.autograding_complete = 'true'))
                AND (egv.active_version = gcd.gcd_graded_version AND egv.active_version = egd.g_version);
            """
        )

        print(available_grades)

        if gradeables:
            # Retrieve the full course name from the course config.json
            with open(course_config_path, 'r') as f:
                data = json.load(f)

                if 'course_name' in data['course_details']:
                    full_name = data['course_details']['course_name'].strip()

                    if len(full_name) > 0:
                        course_name += ": " + full_name

        for g in gradeables:
            gradeable = {"id": g[0], "title": g[1]}
            timestamp = str(datetime.datetime.now())

            # Construct gradeable URL into a valid JSON format
            gradeable_url = (f"{BASE_URL_PATH}/courses/{term}/{course}"
                             f"/gradeable/{gradeable['id']}")
            metadata = json.dumps({"url": gradeable_url})

            # Formulate respective recipient lists
            general_list, email_list = [], []
            notification_content = "Scores Released: " + gradeable["title"]
            email_subject = (f"[Submitty {course}] Scores Released: "
                             f"{gradeable['title']}")
            email_body = (f"An Instructor has released scores in:\n"
                          f"{course_name}\n\nScores have been released for "
                          f"{gradeable['title']}.\n\nAuthor: System\n"
                          f"Click here for more info: {gradeable_url}\n\n"
                          "--\n"
                          "NOTE: This is an automated email notification, "
                          "which is unable to receive replies.\n"
                          "Please refer to the course syllabus for contact "
                          "information for your teaching staff.")

            if len(notification_content) > 40:
                notification_content = notification_content[:36] + "..."

            # Fetch all potential recipients (general and/or email)
            notification_recipients = course_db.execute(
                """
                SELECT
                users.user_id,
                users.user_email,
                (notification_settings.all_released_grades) AS general_enabled,
                (notification_settings.all_released_grades_email) AS email_enabled
                FROM users
                JOIN notification_settings
                ON notification_settings.user_id = users.user_id
                WHERE notification_settings.all_released_grades = true
                OR notification_settings.all_released_grades_email = true;
                """
            )

            for r in notification_recipients:
                user_id, user_email, general, email = r[0], r[1], r[2], r[3]

                if general:
                    general_list.append(
                        f"('grading','{metadata}','{notification_content}',"
                        f"'{timestamp}','submitty-admin','{user_id}')"
                    )

                if email:
                    email_list.append(
                        f"('{email_subject}', '{email_body}', '{timestamp}', "
                        f"'{user_id}', '{user_email}', '{term}', '{course}')"
                    )

            # Insert notifications
            if general_list:
                course_db.execute(
                    f"""INSERT INTO notifications
                    (component, metadata, content, created_at, from_user_id,
                    to_user_id)
                    VALUES {", ".join(general_list)};"""
                )

            if email_list:
                master_db.execute(
                    f"""INSERT INTO emails
                    (subject, body, created, user_id, email_address, term,
                    course)
                    VALUES {", ".join(email_list)};"""
                )

            m = (f"[{timestamp}] ({course}) {gradeable['title']}: "
                 f"{len(general_list)} general, {len(email_list)} "
                 f"email\n")
            LOG_FILE.write(m)

            # Add successfully notified gradeables to update state
            notified_gradeables.append(f"'{gradeable['id']}'")

        # Update all successfully sent notifications for current course
        if len(notified_gradeables) > 0:
            course_db.execute(
                f"""UPDATE gradeable SET g_notification_sent = true
                WHERE g_id in ({", ".join(notified_gradeables)})"""
            )
            notified += 1

        # Close the course database connection
        course_db.close()

    return notified


def main():
    """Driver method to release course notifications"""
    try:
        notified = notify_gradeable_scores()
        m = (f"[{datetime.datetime.now()}] Successfully released {notified} "
             f"gradeable notification{'s' if notified != 1 else ''}")
        LOG_FILE.write(f"{m}\n")
    except Exception as notification_error:  # pylint: disable=broad-except
        m = (f"[{datetime.datetime.now()}] Error Sending Notification(s): "
             f"{str(notification_error)}")
        LOG_FILE.write(f"{m}\n")
        print(m)


if __name__ == "__main__":
    main()
