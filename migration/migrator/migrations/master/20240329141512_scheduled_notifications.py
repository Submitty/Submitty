"""Migration for the Submitty master database."""
from sqlalchemy import create_engine
from sqlalchemy.engine.url import URL

def connect_course_db(db, course):
    """Set up a connection with the given course database name."""
    engine_url = db.engine.url

    conn_string = URL(
        drivername=engine_url.drivername,
        username=engine_url.username,
        password=engine_url.password,
        host=engine_url.host,
        port=engine_url.port,
        database=course
    )

    engine = create_engine(conn_string)
    course_db = engine.connect()
    return course_db

def seed(db):
    """Insert current gradeables into scheduled_notifications with a release date in the future for term s24"""
    courses =  db.execute("SELECT term, course FROM courses WHERE term = 's24';")
    gradeable_notifications_values = []

    for term, course in courses:
        if term == "s24":
            course_db = connect_course_db(db, "submitty_{}_{}".format(term, course))
            gradeables = course_db.execute("SELECT g_id, g_grade_released_date FROM gradeable WHERE g_grade_released_date > NOW();")

            for id, release_date in gradeables:
                gradeable_notifications_values.append("('{}', '{}',' {}', '{}', '{}')".format(id, "gradeable", term, course, release_date))

            course_db.close()

    db.execute("INSERT INTO scheduled_notifications (reference_id, type, term, course, date) VALUES {};".format(",".join(gradeable_notifications_values)))

def up(config, database):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    database.execute(
        """
            CREATE TABLE scheduled_notifications (
                id SERIAL PRIMARY KEY,
                reference_id character varying(255) NOT NULL,
                type character varying(255) NOT NULL,
                term character varying NOT NULL,
                course character varying NOT NULL,
                date timestamp(6) with time zone NOT NULL,
                notification_state boolean DEFAULT false NOT NULL
            );
        """
    )

    seed(database)

def down(config, database):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    database.execute(
        """
            DROP TABLE scheduled_notifications;
        """
    )
