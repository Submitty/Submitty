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
        CREATE TABLE IF NOT EXISTS lichen (
            id serial PRIMARY KEY,
            gradeable_id varchar(255) NOT NULL,
            config_id smallint CHECK (config_id > 0) NOT NULL,
            version varchar(255) NOT NULL,
            regex text,
            regex_dirs text,
            language varchar(255) NOT NULL,
            threshold smallint CHECK (threshold > 1) NOT NULL,
            sequence_length smallint CHECK (sequence_length > 1) NOT NULL,
            ignore_submissions text,
            UNIQUE(gradeable_id, config_id),
            CONSTRAINT fk_gradeable_id
                FOREIGN KEY(gradeable_id)
                    REFERENCES gradeable(g_id)
                    ON DELETE CASCADE
        );
        """
    )

    database.execute(
        """
        CREATE TABLE IF NOT EXISTS lichen_prior_term_gradeables (
            lichen_run_id int NOT NULL,
            prior_semester varchar(255) NOT NULL,
            prior_course varchar(255) NOT NULL,
            prior_gradeable varchar(255) NOT NULL,
            CONSTRAINT fk_lichen_run_id
                FOREIGN KEY(lichen_run_id)
                    REFERENCES lichen(id)
                    ON DELETE CASCADE
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
    database.execute(
        """
        DROP TABLE lichen CASCADE; DROP TABLE lichen_prior_term_gradeables CASCADE;
        """
    )
