"""Migration for a given Submitty course database."""

import json
from pathlib import Path
from sqlalchemy import insert

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
    # create tables here
    database.execute(
        """
        CREATE TABLE IF NOT EXISTS course_materials (
            id SERIAL NOT NULL PRIMARY KEY,
            type smallint NOT NULL,
            url TEXT,
            link_title varchar(255) DEFAULT NULL,
            link_url TEXT DEFAULT NULL,
            release_date timestamptz NOT NULL,
            hidden_from_students BOOL NOT NULL,
            priority integer NOT NULL,
            section_lock BOOL NOT NULL
        );
        """
    )
    database.execute(
        """
        CREATE TABLE IF NOT EXISTS course_materials_access (
            id SERIAL NOT NULL PRIMARY KEY,
            course_material_id integer NOT NULL,
            timestamp timestamptz NOT NULL,
            user_id varchar(255) NOT NULL
        );
        """
    )
    database.execute(
        """
        CREATE TABLE IF NOT EXISTS course_materials_sections (
            course_material_id integer NOT NULL,
            section_id varchar(255) NOT NULL,
            CONSTRAINT fk_course_material_id
                FOREIGN KEY(course_material_id)
                    REFERENCES course_materials(id)
                    ON DELETE CASCADE,
            CONSTRAINT fk_section_id
                FOREIGN KEY(section_id)
                    REFERENCES sections_registration(sections_registration_id)
                    ON DELETE CASCADE
        );
        """
    )

    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)
    json_file = Path(course_dir, 'uploads', 'course_materials_file_data.json')

    if json_file.is_file():
        with json_file.open('r') as file:
            data = json.load(file)
            if type(data) is dict:
                for itemkey, itemvalue in data.items():
                    material_type = 0
                    link_title = None
                    link_url = None
                    url = itemkey
                    if itemvalue['external_link'] is True:
                        material_type = 1
                        url = None
                        link_file = Path(itemkey)
                        with link_file.open('r') as link:
                            link_data = json.load(link)
                            link_title = link_data['name']
                            link_url = link_data['url']
                    sections = []
                    if 'sections' in itemvalue:
                        for section in itemvalue['sections']:
                            sections.append(section)
                    has_sections = len(sections) != 0
                    query =  """
                        INSERT INTO course_materials (
                            type,
                            url,
                            link_title,
                            link_url,
                            release_date,
                            hidden_from_students,
                            priority,
                            section_lock
                        )
                        VALUES (
                            :type, :url, :link_title, :link_url, :release_date, :hidden_from_students, :priority, :section_lock
                        ) RETURNING id
                        """
                    params = {
                        'type': material_type,
                        'url': url,
                        'link_title': link_title,
                        'link_url': link_url,
                        'release_date': itemvalue['release_datetime'],
                        'hidden_from_students': itemvalue['hide_from_students'],
                        'priority': itemvalue['sort_priority'],
                        'section_lock': has_sections
                    }
                    result = database.session.execute(query, params)
                    course_material_id = result.fetchone()[0]
                    for section in sections:
                        query = """
                            INSERT INTO course_materials_sections (
                                course_material_id,
                                section_id
                            )
                            VALUES (
                                :course_material_id, :section_id
                            )
                            """
                        params = {
                            'course_material_id': course_material_id,
                            'section_id': section
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
    database.execute("DROP TABLE course_materials CASCADE;")
    database.execute("DROP TABLE course_materials_access CASCADE;")
    database.execute("DROP TABLE course_materials_sections CASCADE;")
    pass
