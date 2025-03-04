"""Migration for a given Submitty course database."""


def up(config, database, semester, course):
    database.execute("""
        ALTER TABLE public.course_materials
        DROP COLUMN IF EXISTS calendar_date;
    """)
    
    database.execute("""
        ALTER TABLE public.course_materials
        ADD COLUMN calendar_date TIMESTAMPTZ DEFAULT NULL;
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
    pass
