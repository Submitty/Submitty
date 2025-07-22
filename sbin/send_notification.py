"""
Handles generating course-related notifications in Submitty.

This is done by scanning each course database for pending notifications,
such as releasing available released grade notifications.
"""
import json
import os
import datetime
import sys
import getpass
from json import JSONDecodeError
from sqlalchemy import create_engine  # pylint: disable=import-error
from sqlalchemy.orm import Session  # pylint: disable=import-error
from sqlalchemy.exc import DatabaseError  # pylint: disable=import-error

try:
    CONFIG_PATH = os.path.join(
        os.path.dirname(os.path.realpath(__file__)), "..", "config"
    )

    # Authenticate submitty_daemon user
    with open(os.path.join(CONFIG_PATH, "submitty_users.json"),
              "r", encoding="utf-8") as file:
        USER_DATA = json.load(file)

        if USER_DATA["daemon_user"] != getpass.getuser():
            raise RuntimeError("- script must be run by the daemon user")

    # Retrieve submitty database configurations
    with open(os.path.join(CONFIG_PATH, "submitty.json"),
              encoding="utf-8") as open_file:
        SUBMITTY_CONFIG = json.load(open_file)

    with open(os.path.join(CONFIG_PATH, "database.json"),
              encoding="utf-8") as open_file:
        DATABASE_CONFIG = json.load(open_file)

    DB_HOST = DATABASE_CONFIG["database_host"]
    DB_USER = DATABASE_CONFIG["database_user"]
    DB_PASSWORD = DATABASE_CONFIG["database_password"]
except (JSONDecodeError, RuntimeError, IOError) as config_fail_error:
    print(
        f"[{datetime.datetime.now()}] ERROR: CORE SUBMITTY CONFIGURATION ERROR"
        + f"{config_fail_error}"
    )
    sys.exit(1)

BASE_URL_PATH = SUBMITTY_CONFIG["submission_url"]
DATA_DIR_PATH = SUBMITTY_CONFIG["submitty_data_dir"]
COURSE_DIR_PATH = os.path.join(DATA_DIR_PATH, 'courses')
NOTIFICATION_LOG_PATH = os.path.join(DATA_DIR_PATH, "logs", "notifications")

DATE = datetime.datetime.now()
LOG_FILE_PATH = os.path.join(
    NOTIFICATION_LOG_PATH, f"{DATE.year:04d}{DATE.month:02d}{DATE.day:02d}.txt"
)


try:
    # open() is required to ensure the log file can be used globally
    LOG_FILE = open(LOG_FILE_PATH, "a", encoding="utf-8")  # pylint: disable=consider-using-with
except IOError as log_file_error:
    print(
        f"[{datetime.datetime.now()}] ERROR: CORE SUBMITTY CONFIGURATION ERROR"
        + f"{log_file_error}"
    )
    sys.exit(1)


def get_full_course_name(term, course):
    """Retrieve the full course name from the course configuration file."""
    course_config_path = os.path.join(
        COURSE_DIR_PATH, term, course, 'config', 'config.json')
    course_name = course.strip().upper()

    with open(course_config_path, 'r', encoding="utf-8") as f:
        data = json.load(f)

        if 'course_name' in data['course_details']:
            full_name = data['course_details']['course_name'].strip()

            if len(full_name) > 0:
                course_name += ": " + full_name

    return course_name


def get_late_day_defaults(term, course):
    """Retrieve default late day values from the course config file."""
    course_config_path = os.path.join(
        COURSE_DIR_PATH, term, course, 'config', 'config.json')

    with open(course_config_path, 'r', encoding="utf-8") as f:
        data = json.load(f)
        course_details = data.get('course_details', {})
        default_hw_late_days = course_details.get('default_hw_late_days', 0)
        default_student_late_days = course_details.get('default_student_late_days', 0)

        return (default_hw_late_days, default_student_late_days)


def connect_db(db_name):
    """Set up a connection with the specific database."""
    if os.path.isdir(DB_HOST):
        connection = (f"postgresql://{DB_USER}:{DB_PASSWORD}@/{db_name}"
                      f"?host={DB_HOST}")
    else:
        connection = (f"postgresql://{DB_USER}:{DB_PASSWORD}@{DB_HOST}"
                      f"/{db_name}")

    engine = create_engine(connection)
    db = Session(engine.connect())

    return db


def parse_column(row, column):
    """Parse a database column from a row for variable notification types."""
    # pylint: disable=protected-access
    return row._mapping.get(column, None)


def format_timestamp(timestamp):
    """Format a timestamp into a readable string."""
    return timestamp.strftime("%Y-%m-%d %I:%M:%S %p") if timestamp else "TBA"


def format_late_days(late_days):
    """Format a late day value into a readable string."""
    return f"{late_days} late day{'s' if late_days != 1 else ''}"


def construct_notifications(term, course, pending, notification_type):
    """Construct pending gradeable notifications for the current course."""
    timestamps = {}
    gradeables, site, email = [], [], []

    for notification in pending:
        gradeable = {
            "id": parse_column(notification, 'g_id'),
            "title": parse_column(notification, 'g_title'),
            "submission_due_date": parse_column(notification, 'submission_due_date'),
            "team_id": parse_column(notification, 'team_id'),
            "user_id": parse_column(notification, 'user_id'),
            "user_email": parse_column(notification, 'user_email'),
            # Potentially send via the notification page
            "site_enabled": parse_column(notification, 'site_enabled'),
            # Potentially send via email
            "email_enabled": parse_column(notification, 'email_enabled'),
            # Late day info for gradeable available notifications
            "max_late_days": parse_column(notification, 'max_late_days'),
            "remaining_late_days": parse_column(notification, 'remaining_late_days')
        }

        timestamp = timestamps.setdefault(
            gradeable['id'], datetime.datetime.now()
        )

        # Metadata-related content
        gradeable_url = (f"{BASE_URL_PATH}/courses/{term}/{course}"
                         f"/gradeable/{gradeable['id']}")
        metadata = json.dumps({"url": gradeable_url})

        # Notification-related content
        if notification_type == "gradeable_release":
            subject = f"Submission open for {gradeable['title']}"
            body = (
                f"Submissions are now being accepted for {gradeable['title']}, "
                f"where the final deadline is {format_timestamp(gradeable['submission_due_date'])} "
                f"with up to {format_late_days(gradeable['max_late_days'])} allowed, "
                f"and you have {format_late_days(gradeable['remaining_late_days'])} available "
            )
        else:
            subject = f"Grade Released: {gradeable['title']}"
            body = f"Your grade is now available for {gradeable['title']} "

        body += (
            f"in course \n{get_full_course_name(term, course)}.\n\n"
            f"Click here for more info: {gradeable_url}"
        )

        if gradeable["site_enabled"] is True:
            site.append({
                "component": "grading",
                "metadata": metadata,
                "content": subject,
                "created_at": timestamp,
                "from_user_id": "submitty-admin",
                "to_user_id": gradeable['user_id']
            })

        if gradeable["email_enabled"] is True:
            email.append({
                "subject": subject,
                "body": body,
                "created": timestamp,
                "user_id": gradeable['user_id'],
                "email_address": gradeable['user_email'],
                "term": term,
                "course": course
            })

        gradeables.append({
            "g_id": gradeable['id'],
            "user_id": gradeable['user_id'],
            "team_id": gradeable['team_id']
        })

    return gradeables, site, email


def send_notifications(course, course_db, master_db, lists, notification_type):
    """Send pending gradeable notifications for the current course."""
    gradeables, site, email = lists
    timestamp = datetime.datetime.now()

    try:
        if site:
            course_db.execute(
                """
                INSERT INTO notifications
                (component, metadata, content, created_at,
                 from_user_id, to_user_id)
                VALUES (:component, :metadata, :content,
                        :created_at, :from_user_id, :to_user_id);
                """, site
            )

        if email:
            master_db.execute(
                """
                INSERT INTO emails
                (subject, body, created, user_id, email_address,
                 term, course)
                 VALUES (:subject, :body, :created, :user_id,
                         :email_address, :term, :course);
                """, email
            )

        if gradeables:
            if notification_type == "gradeable_release":
                course_db.execute(
                    """
                    UPDATE electronic_gradeable
                    SET eg_release_notifications_sent = TRUE
                    WHERE g_id = :g_id;
                    """, gradeables
                )
            else:
                course_db.execute(
                    """
                    UPDATE electronic_gradeable_version
                    SET g_notification_sent = TRUE
                    WHERE (g_id = :g_id AND user_id = :user_id)
                    OR (g_id = :g_id AND team_id = :team_id);
                    """, gradeables
                )

            m = (f"[{timestamp}] ({course}): Sent {len(site)} site, "
                 f"{len(email)} email notifications\n")
            LOG_FILE.write(m)

            # Commit the changes to the individual databases
            course_db.commit()
            master_db.commit()
    except DatabaseError as notification_error:
        # Rollback the changes if an error occurs
        course_db.rollback()
        master_db.rollback()

        m = (f"[{timestamp}] ({course}) Error Sending Notification(s): "
             f"{str(notification_error)}\n")
        LOG_FILE.write(m)
        print(m)


def send_pending_notifications():
    """Send pending gradeable notifications for all active courses."""
    notified = 0
    master_db = connect_db("submitty")
    active_courses = "SELECT term, course FROM courses WHERE status = '1';"
    courses = master_db.execute(active_courses)

    for term, course in courses:
        course_db = connect_db(f"submitty_{term}_{course}")
        default_hw_late_days, default_student_late_days = get_late_day_defaults(term, course)

        # Retrieve all fully graded gradeables with pending notifications
        grades_available = course_db.execute(
            """
            WITH gradeables AS (
                SELECT DISTINCT
                    g.g_id AS g_id,
                    g.g_title AS g_title,
                    t.team_id AS team_id,
                    COALESCE(egv.user_id, t.user_id) AS user_id,
                    eg.eg_use_ta_grading AS eg_use_ta_grading,
                    egd.autograding_complete AS autograding_complete,
                    gc.gc_id AS component,
                    CONCAT(gcd.gd_id, '-', gcd.gc_id, '-', gcd.gcd_grader_id)
                        AS graded_component
                FROM gradeable AS g
                INNER JOIN electronic_gradeable AS eg
                    ON g.g_id = eg.g_id
                    AND eg.eg_student_view IS TRUE
                    AND g.g_grade_released_date <= NOW()
                INNER JOIN electronic_gradeable_version AS egv
                    ON g.g_id = egv.g_id
                    AND egv.active_version != '0'
                    AND egv.g_notification_sent IS FALSE
                INNER JOIN gradeable_component AS gc
                    ON g.g_id = gc.g_id
                INNER JOIN gradeable_data AS gd
                    ON g.g_id = gd.g_id
                    AND COALESCE(egv.user_id, egv.team_id)
                        = COALESCE(gd.gd_user_id, gd.gd_team_id)
                LEFT JOIN gradeable_teams AS gt
                    ON gd.g_id = gt.g_id
                    AND gd.gd_team_id = gt.team_id
                LEFT JOIN teams AS t
                    ON gt.team_id = t.team_id
                LEFT JOIN electronic_gradeable_data AS egd
                    ON g.g_id = egd.g_id
                    AND COALESCE(egv.user_id, egv.team_id)
                        = COALESCE(gd.gd_user_id, gd.gd_team_id)
                    AND egv.active_version = egd.g_version
                LEFT JOIN gradeable_component_data AS gcd
                    ON gd.gd_id = gcd.gd_id
                    AND gc.gc_id = gcd.gc_id
                    AND gcd.gcd_grader_id IS NOT NULL
                    AND egv.active_version = gcd.gcd_graded_version
            )
            SELECT DISTINCT
                g_id,
                g_title,
                team_id,
                u.user_id AS user_id,
                u.user_email AS user_email,
                COALESCE(ns.all_released_grades, TRUE) AS site_enabled,
                COALESCE(ns.all_released_grades_email, TRUE) AS email_enabled
            FROM gradeables AS g
            INNER JOIN users AS u
                ON g.user_id = u.user_id
            LEFT JOIN notification_settings AS ns
                ON u.user_id = ns.user_id
            GROUP BY g_id, g_title, u.user_id, u.user_email, team_id,
                ns.all_released_grades, ns.all_released_grades_email,
                eg_use_ta_grading,autograding_complete
            HAVING (
                eg_use_ta_grading IS FALSE AND autograding_complete IS TRUE
                OR
                COUNT(component) = COUNT(graded_component)
            );
            """
        )

        if grades_available:
            lists = construct_notifications(term, course, grades_available, "grades_release")
            send_notifications(course, course_db, master_db, lists, "grades_release")
            notified += len(lists[0])

        release_available = course_db.execute(
            """
            SELECT DISTINCT
                g.g_id AS g_id,
                g.g_title AS g_title,
                eg.eg_submission_due_date AS submission_due_date,
                GREATEST(
                    0, COALESCE(NULLIF(eg.eg_late_days, -1), :default_hw_late_days)
                ) AS max_late_days,
                COALESCE(ld.allowed_late_days, :default_student_late_days) AS remaining_late_days,
                u.user_id AS user_id,
                u.user_email AS user_email,
                COALESCE(ns.all_gradeable_releases, TRUE) AS site_enabled,
                COALESCE(ns.all_gradeable_releases_email, FALSE) AS email_enabled
            FROM electronic_gradeable eg
            INNER JOIN gradeable AS g
                ON eg.g_id = g.g_id
            INNER JOIN users AS u
                ON u.registration_type <> 'withdrawn'
            LEFT JOIN notification_settings AS ns
                ON u.user_id = ns.user_id
            LEFT JOIN late_days ld
                ON u.user_id = ld.user_id
                AND ld.since_timestamp <= eg.eg_submission_due_date
            WHERE eg.eg_release_notifications_sent IS FALSE
                AND eg.eg_submission_open_date <= NOW()
            GROUP BY g.g_id, g.g_title, eg.eg_submission_due_date, u.user_id,
                u.user_email, ns.all_gradeable_releases, ns.all_gradeable_releases_email,
                eg.eg_late_days, ld.allowed_late_days
            """, {
                "default_hw_late_days": default_hw_late_days,
                "default_student_late_days": default_student_late_days
            }
        )

        if release_available:
            lists = construct_notifications(term, course, release_available, "gradeable_release")
            send_notifications(course, course_db, master_db, lists, "gradeable_release")
            notified += len(lists[0])

        course_db.close()

    master_db.close()

    return notified


def main():
    """Driver method to release course notifications"""
    try:
        notified = send_pending_notifications()
        m = (f"[{datetime.datetime.now()}] Successfully released "
             f"{notified} notification{'s' if notified != 1 else ''}")
        LOG_FILE.write(f"{m}\n\n")
        LOG_FILE.close()
    except (IOError, DatabaseError) as notification_error:
        m = (f"[{datetime.datetime.now()}] Error Sending Notification(s): "
             f"{str(notification_error)}")
        LOG_FILE.write(f"{m}\n")
        print(m)
    finally:
        # Manually close the log file to avoid resource leaks
        if LOG_FILE and not LOG_FILE.closed:
            LOG_FILE.close()


if __name__ == "__main__":
    main()
