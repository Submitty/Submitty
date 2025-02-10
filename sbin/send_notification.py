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
        general_list, email_list = [], []
        notified = []
        course_config_path = os.path.join(
            COURSES_PATH, term, course, 'config', 'config.json')
        course_name = course.strip().upper()

        available = course_db.execute(
            """
            WITH gradeables AS (
                SELECT
                    g.g_id AS g_id,
                    g.g_title AS g_title,
                    egv.active_version as egv_active_version,
                    gcd.gcd_graded_version AS gcd_graded_version,
                    COALESCE(egv.user_id, t.user_id) AS user_id,
                    eg.eg_use_ta_grading AS eg_use_ta_grading,
                    egd.autograding_complete AS autograding_complete,
                    COUNT(gcd.gcd_graded_version) OVER(PARTITION BY g.g_id, egv.user_id)
                        AS graded_components,
                    COUNT(gc.gc_id) OVER(PARTITION BY g.g_id, egv.user_id)
                        AS total_components
                FROM gradeable AS g
                INNER JOIN electronic_gradeable AS eg
                    ON g.g_id = eg.g_id
                    AND eg.eg_student_view = TRUE
                    AND g.g_grade_released_date <= NOW()
                INNER JOIN electronic_gradeable_version AS egv
                    ON g.g_id = egv.g_id
                    AND egv.active_version != '0'
                    AND egv.g_notification_sent = FALSE
                INNER JOIN gradeable_component AS gc
                    ON g.g_id = gc.g_id
                INNER JOIN gradeable_data AS gd
                    ON g.g_id = gd.g_id
                    AND (
                        (NOT eg.eg_team_assignment AND egv.user_id = gd.gd_user_id)
                        OR
                        (eg.eg_team_assignment AND egv.team_id = gd.gd_team_id)
                    )
                LEFT JOIN gradeable_teams AS gt
                    ON gd.gd_team_id = gt.team_id
                    AND gd.g_id = gt.g_id
                LEFT JOIN teams AS t
                    ON gt.team_id = t.team_id
                LEFT JOIN electronic_gradeable_data AS egd
                    ON g.g_id = egd.g_id
                    AND (
                        (NOT eg.eg_team_assignment AND egv.user_id = gd.gd_user_id)
                        OR
                        (eg.eg_team_assignment AND egv.team_id = gd.gd_team_id)
                    )
                    AND egv.active_version = egd.g_version
                LEFT JOIN gradeable_component_data AS gcd
                    ON gd.gd_id = gcd.gd_id
                    AND gc.gc_id = gcd.gc_id
                    AND (eg.eg_use_ta_grading = FALSE OR gcd.gcd_graded_version IS NOT NULL)
            )
            SELECT DISTINCT
                g_id,
                g_title,
                gradeables.user_id,
                u.user_email AS user_email,
                ns.all_released_grades AS general_enabled,
                ns.all_released_grades_email AS email_enabled
            FROM gradeables
            INNER JOIN users AS u
                ON gradeables.user_id = u.user_id
            LEFT JOIN notification_settings AS ns
                ON gradeables.user_id = ns.user_id
            WHERE (
                (eg_use_ta_grading = FALSE AND autograding_complete = TRUE)
                OR
                (graded_components = total_components)
            )
                AND u.user_id IS NOT NULL
            GROUP BY g_id, g_title, gcd_graded_version, egv_active_version,
                eg_use_ta_grading, gradeables.user_id, u.user_email, ns.all_released_grades,
                ns.all_released_grades_email
            HAVING (eg_use_ta_grading = FALSE OR egv_active_version = gcd_graded_version);
            """
        )

        if available:
            # Retrieve the full course name from the course config.json
            with open(course_config_path, 'r') as f:
                data = json.load(f)

                if 'course_name' in data['course_details']:
                    full_name = data['course_details']['course_name'].strip()

                    if len(full_name) > 0:
                        course_name += ": " + full_name

        for g in available:
            gradeable = {
                "id": g[0],
                "title": g[1],
                "user_id": g[2],
                "user_email": g[3],
                "general": g[4],
                "email": g[5]
            }
            timestamp = str(datetime.datetime.now())

            # Construct gradeable URL into a valid JSON format
            gradeable_url = (f"{BASE_URL_PATH}/courses/{term}/{course}"
                             f"/gradeable/{gradeable['id']}")
            metadata = json.dumps({"url": gradeable_url})

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

            if gradeable["general"]:
                general_list.append(
                    f"('grading','{metadata}','{notification_content}',"
                    f"'{timestamp}','submitty-admin','{gradeable['user_id']}')"
                )

            if gradeable["email"]:
                email_list.append(
                    f"('{email_subject}', '{email_body}', '{timestamp}', "
                    f"'{gradeable["user_id"]}', '{gradeable['user_email']}',"
                    f"'{term}', '{course}')"
                )

            notified.append(f"({gradeable['id']}, {gradeable['user_id']})")

        # Send notifications via a transaction
        try:
            course_db.begin()
            master_db.begin()

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

            # Update all successfully sent notifications for current course
            if len(notified) > 0:
                course_db.execute(
                    f"""
                    UPDATE electronic_gradeable_version
                    SET g_notification_sent = TRUE
                    WHERE (g_id, user_id) IN ({", ".join(notified)});
                    """
                )

            course_db.commit()
            master_db.commit()

            m = (f"[{timestamp}] ({course}) {gradeable['title']}: "
                 f"{len(general_list)} general, {len(email_list)} "
                 f"email\n")
            LOG_FILE.write(m)
        except Exception as notification_error:  # pylint: disable=broad-except
            # Rollback the transaction if an error occurs
            course_db.rollback()
            master_db.rollback()

            m = (f"[{timestamp}] ({course}) Error Sending Notification(s): "
                 f"{str(notification_error)}\n")
            LOG_FILE.write(m)
            print(m)

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
