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
    database.execute("""
        ALTER TABLE chatrooms
        ADD COLUMN IF NOT EXISTS allow_read_only_after_end BOOLEAN NOT NULL DEFAULT FALSE;
    """)

    database.execute("""
        ALTER TABLE course_materials_sections RENAME CONSTRAINT fk_course_material_id TO fk_course_materials_sections_material_id;
        ALTER TABLE course_materials_access RENAME CONSTRAINT fk_course_material_id TO fk_course_materials_access_material_id;
    """)

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
    database.execute("""
        ALTER TABLE course_materials_sections 
        RENAME CONSTRAINT fk_course_materials_sections_material_id TO fk_course_material_id;
        
        ALTER TABLE course_materials_access 
        RENAME CONSTRAINT fk_course_materials_access_material_id TO fk_course_material_id;
    """)

    database.execute("""
        ALTER TABLE chatrooms 
        DROP COLUMN IF EXISTS allow_read_only_after_end;
    """)
