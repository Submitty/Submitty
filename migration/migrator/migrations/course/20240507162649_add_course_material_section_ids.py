"""Migration for a given Submitty course database."""


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
    database.execute("ALTER TABLE course_materials_sections DROP CONSTRAINT pk_course_material_section")
    database.execute("ALTER TABLE course_materials_sections ADD CONSTRAINT unique_course_material_section UNIQUE (course_material_id, section_id)")
    database.execute("ALTER TABLE course_materials_sections ADD COLUMN IF NOT EXISTS id serial PRIMARY KEY")


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
    database.execute("ALTER TABLE course_materials_sections DROP CONSTRAINT course_materials_sections_pkey")
    database.execute("ALTER TABLE course_materials_sections DROP COLUMN id")
    database.execute("ALTER TABLE course_materials_sections DROP CONSTRAINT unique_course_material_section")
    database.execute("ALTER TABLE course_materials_sections ADD CONSTRAINT pk_course_material_section PRIMARY KEY (course_material_id, section_id)")
