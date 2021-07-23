"""Migration for a given Submitty course database."""

import json
from pathlib import Path

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
    database.execute("ALTER TABLE course_materials ADD COLUMN url TEXT;")
    database.execute("ALTER TABLE course_materials ADD COLUMN url_title varchar(255);")

    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)
    json_file = Path(course_dir, 'uploads', 'course_materials_file_data.json')
    course_materials_dir = Path(course_dir, 'uploads', 'course_materials')

    if json_file.is_file():
        with json_file.open('r') as file:
            data = json.load(file)
            if isinstance(data, dict):
                for itemkey, itemvalue in data.items():
                    url = None
                    url_title = None
                    if itemvalue['external_link'] is True:
                        f = open(itemkey)
                        data = json.load(f)
                        url = data['url']
                        url_title = data['name']
                    query = """
                        UPDATE course_materials SET
                        url = :url, url_title = :url_title
                        WHERE path = :path
                    """
                    params = {
                        'url': url,
                        'url_title': url_title,
                        'path': itemkey
                    }
                    database.session.execute(query, params)


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
