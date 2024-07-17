"""Migration for a given Submitty course database."""
import json
def course_config_location(semester, course):
    """
    Returns path to course config file for a given semester and course.
    
    :param semester: Semester of the course being migrated
    :type semester: str
    :param course: Code of course being migrated
    :type course: str
    """
    return f"/var/local/submitty/courses/{semester}/{course}/config/config.json"


def up(config, database, semester, course):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    :param semester: Semester of the course being migrated
    :type semester: str
    :param course: Code of course being migrated
    :type course: str
    """
    # Opening JSON file
    with open(course_config_location(semester, course), 'r') as file:
        data = file.read()
        if not data:
            return
        json_data = json.loads(data)
    if 'self_registration' not in json_data['course_details']:
        json_data['course_details']['self_registration'] = False
    file2 = open(course_config_location(semester, course), 'w')
    json.dump(json_data, file2, indent=4)

def down(config, database, semester, course):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    :param semester: Semester of the course being migrated
    :type semester: str
    :param course: Code of course being migrated
    :type course: str
    """
    pass
