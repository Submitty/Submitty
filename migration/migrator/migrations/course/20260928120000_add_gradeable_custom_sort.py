"""Add custom grading sort support to course databases."""


def up(config, database, semester, course):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    :param semester: Semester of the course being migrated
    :type semester: str
    :param course: Name of course being migrated
    :type course: str
    """
    database.execute("""
        ALTER TABLE gradeable
        ADD COLUMN IF NOT EXISTS g_use_custom_grading_order BOOLEAN NOT NULL DEFAULT FALSE
    """)

    database.execute("""
        CREATE TABLE IF NOT EXISTS gradeable_custom_grading_order (
            g_id character varying(255) NOT NULL,
            user_id character varying(255),
            team_id character varying(255),
            sort_order integer NOT NULL,

            CONSTRAINT gradeable_custom_grading_order_submitter_check
                CHECK (
                    ((user_id IS NOT NULL) AND (team_id IS NULL))
                    OR
                    ((user_id IS NULL) AND (team_id IS NOT NULL))
                ),

            CONSTRAINT gradeable_custom_grading_order_position_unique
                UNIQUE (g_id, sort_order),

            CONSTRAINT gradeable_custom_grading_order_user_unique
                UNIQUE (g_id, user_id),

            CONSTRAINT gradeable_custom_grading_order_team_unique
                UNIQUE (g_id, team_id),

            CONSTRAINT gradeable_custom_grading_order_gradeable_fk
                FOREIGN KEY (g_id)
                REFERENCES gradeable(g_id)
                ON DELETE CASCADE,

            CONSTRAINT gradeable_custom_grading_order_user_fk
                FOREIGN KEY (user_id)
                REFERENCES users(user_id)
                ON DELETE CASCADE,

            CONSTRAINT gradeable_custom_grading_order_team_fk
                FOREIGN KEY (team_id)
                REFERENCES gradeable_teams(team_id)
                ON DELETE CASCADE
        )
    """)
    database.execute("""
        ALTER TABLE gradeable
        ADD COLUMN IF NOT EXISTS g_enable_custom_sort BOOLEAN NOT NULL DEFAULT FALSE
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
    :param course: Name of course being migrated
    :type course: str
    """
    pass
