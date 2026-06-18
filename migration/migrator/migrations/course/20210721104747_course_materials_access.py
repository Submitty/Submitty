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
    database.execute(
    """
        CREATE TABLE IF NOT EXISTS course_materials_access (
            id serial NOT NULL,
            course_material_id integer NOT NULL,
            user_id varchar(255) NOT NULL,
            timestamp timestamptz NOT NULL,
            CONSTRAINT fk_course_material_id
                FOREIGN KEY(course_material_id)
                    REFERENCES course_materials(id),
            CONSTRAINT fk_user_id
                FOREIGN KEY(user_id)
                    REFERENCES users(user_id)
        );
    """
    )


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
