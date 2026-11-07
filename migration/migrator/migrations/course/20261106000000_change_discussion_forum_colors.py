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
    UPDATE categories_list 
    SET color = 'var(--colorblind-friendly-red)' 
    WHERE(color = '#800000')""")

    database.execute("""
    UPDATE categories_list 
    SET color = 'var(--colorblind-friendly-red)' 
    WHERE(color = '#808000')""")

    database.execute("""
    UPDATE categories_list 
    SET color = 'var(--colorblind-friendly-green)' 
    WHERE(color = '#008000')""")

    database.execute("""
    UPDATE categories_list 
    SET color = 'var(--colorblind-friendly-green)' 
    WHERE(color = '#008080')""")

    database.execute("""
    UPDATE categories_list 
    SET color = 'var(--colorblind-friendly-blue)' 
    WHERE(color = '#000080')""")

    database.execute("""
    UPDATE categories_list 
    SET color = 'var(--colorblind-friendly-purple)' 
    WHERE(color = '#800080')""")

    database.execute("""
    UPDATE categories_list 
    SET color = 'var(--colorblind-friendly-purple)' 
    WHERE(color = '#808080')""")

    database.execute("""
    UPDATE categories_list 
    SET color = 'var(--colorblind-friendly-blue)' 
    WHERE(color = '#000000')""")


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
    # Note that this does lose some data as a result of going from 8 to 4 color options

    database.execute("""
    UPDATE categories_list 
    SET color = '#800000' 
    WHERE(color = 'var(--colorblind-friendly-red)')""")

    database.execute("""
    UPDATE categories_list 
    SET color = '#008080' 
    WHERE(color = 'var(--colorblind-friendly-green)')""")

    database.execute("""
    UPDATE categories_list 
    SET color = '#800080' 
    WHERE(color = 'var(--colorblind-friendly-purple)')""")

    database.execute("""
    UPDATE categories_list 
    SET color = '#000080' 
    WHERE(color = 'var(--colorblind-friendly-blue)')""")
