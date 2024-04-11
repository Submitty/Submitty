import json
import os
import datetime
from sqlalchemy import create_engine
import sys
import psutil

my_program_name = sys.argv[0]

my_pid = os.getpid()

# Loop over all active processes on the server
for p in psutil.pids():
    try:
        cmdline = psutil.Process(p).cmdline()
        if (len(cmdline) < 2):
            continue
        # If anything on the command line matches the name of the program
        if cmdline[0].find("python") != -1 and cmdline[1].find(my_program_name) != -1:
            if p != my_pid:
                print("ERROR!  Another copy of '" + my_program_name +
                      "' is already running on the server.  Exiting.")
                sys.exit(1)
    except psutil.NoSuchProcess:
        # Whoops, the process ended before we could look at it. But that's ok!
        pass

try:
    CONFIG_PATH = os.path.join(
        os.path.dirname(os.path.realpath(__file__)), '..', 'config')

    with open(os.path.join(CONFIG_PATH, 'submitty.json')) as open_file:
        SUBMITTY_CONFIG = json.load(open_file)

    with open(os.path.join(CONFIG_PATH, 'database.json')) as open_file:
        DATABASE_CONFIG = json.load(open_file)

except Exception as config_fail_error:
    print("[{}] ERROR: CORE SUBMITTY CONFIGURATION ERROR {}".format(
        str(datetime.datetime.now()), str(config_fail_error)))
    sys.exit(1)


DATA_DIR_PATH = SUBMITTY_CONFIG['submitty_data_dir']
BASE_URL_PATH = SUBMITTY_CONFIG['submission_url']
NOTIFICATION_LOG_PATH = os.path.join(DATA_DIR_PATH, "logs", "notifications")
TODAY = datetime.datetime.now()
LOG_FILE = open(os.path.join(
    NOTIFICATION_LOG_PATH, "{:04d}{:02d}{:02d}.txt".format(TODAY.year, TODAY.month,
                                                    TODAY.day)), 'a')
try:
    DB_HOST = DATABASE_CONFIG['database_host']
    DB_USER = DATABASE_CONFIG['database_user']
    DB_PASSWORD = DATABASE_CONFIG['database_password']
except Exception as config_fail_error:
    e = "[{}] ERROR: Database Configuration Failed {}".format(
        str(datetime.datetime.now()), str(config_fail_error))
    LOG_FILE.write(e+"\n")
    print(e)
    sys.exit(1)

def connect_db(db_name):
    """Set up a connection with the database."""
    # If using a UNIX socket, have to specify a slightly different connection string
    if os.path.isdir(DB_HOST):
        conn_string = "postgresql://{}:{}@/{}?host={}".format(
            DB_USER, DB_PASSWORD, db_name, DB_HOST)
    else:
        conn_string = "postgresql://{}:{}@{}/{}".format(
            DB_USER, DB_PASSWORD, DB_HOST, db_name)

    engine = create_engine(conn_string)
    db = engine.connect()
    return db

def notifyPendingGradeables():
    master_db = connect_db("submitty")
    term = master_db.execute("SELECT term_id FROM terms WHERE start_date < NOW() AND end_date > NOW();")
    courses =  master_db.execute("SELECT term, course FROM courses WHERE term = '{}';".format(term.first()[0]))

    for term, course in courses:
        notified_gradeables = []
        
        course_db = connect_db("submitty_{}_{}".format(term, course))
        gradeables = course_db.execute("SELECT g_id, g_title FROM gradeable WHERE g_grade_released_date > NOW() AND g_notification_state = false;")

        for row in gradeables:
            gradeable_notifications = { "id": row[0], "title": row[1] }
            
            # subject: New Grade Released: [ title ]
            # content: Instructor in [course]  has released scores for [title]
            # metadata : {"url": BASE_URL_PATH/course/gradeable/id }
            # component: grading
            # from_user_id: System
            # to_user_id: [ recipients ]
            # created_at: NOW()

            # Construct notification recipient list
            notification_list_query = "SELECT user_id FROM notification_settings WHERE all_released_grades = true;" 
            
            # Send notifications ... 
            send_notification_query = "INSERT INTO notifications(component, metadata, content, created_at, from_user_id, to_user_id) VALUES ?" 
      
            # Construct email recipient list
            email_list_query = """
                SELECT users.user_email, users.user_id 
                FROM users
                JOIN notification_settings ON notification_settings.user_id = users.user_id 
                WHERE notification_settings.all_released_grades_email = true;
            """ 
            
            # Send emails ... 
            # Should use formatSubject and formatBody in Email.php...
            send_email_query = "INSERT INTO emails(subject, body, created, user_id, to_name, email_address, term, course) VALUES ?"
            
            # Add successfully notified gradeables to eventually update notification state
            notified_gradeables.append(gradeable_notifications["id"])
            
        # Update all successfully sent notifications for the potential queued gradeables)...
        update_gradeables_query = "UPDATE gradeable SET g_notification_state = true WHERE g_id in ?"
        
        course_db.close()


def main():
    try:
        notifyPendingGradeables()
    except Exception as notification_send_error:
        e = "[{}] Error Sending Notification(s): {}".format(
            str(datetime.datetime.now()), str(notification_send_error))
        LOG_FILE.write(e+"\n")
        print(e)

if __name__ == "__main__":
    main()
