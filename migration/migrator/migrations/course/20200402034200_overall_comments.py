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

    # Create overall comment table
    database.execute(
        """
       CREATE TABLE IF NOT EXISTS gradeable_overall_comment (
            g_id character varying(255) NOT NULL,
            goc_user_id character varying(255),
            goc_team_id character varying(255),
            goc_grader_id character varying(255) NOT NULL,
            goc_overall_comment character varying NOT NULL
        );
        """
    )


    database.execute("ALTER TABLE gradeable_overall_comment DROP CONSTRAINT IF EXISTS gradeable_overall_comment_pkey")
    database.execute(
        """
        ALTER TABLE ONLY gradeable_overall_comment
            ADD CONSTRAINT gradeable_overall_comment_pkey PRIMARY KEY (g_id, goc_user_id, goc_team_id, goc_grader_id);
        """
    )
    
    database.execute("ALTER TABLE gradeable_overall_comment DROP CONSTRAINT IF EXISTS gradeable_overall_comment_g_id_fkey")
    database.execute(
        """
        ALTER TABLE ONLY gradeable_overall_comment
            ADD CONSTRAINT gradeable_overall_comment_g_id_fkey FOREIGN KEY (g_id) REFERENCES gradeable(g_id) ON DELETE CASCADE;
        """
    )

    database.execute("ALTER TABLE gradeable_overall_comment DROP CONSTRAINT IF EXISTS gradeable_overall_comment_goc_user_id_fkey")
    database.execute(
        """
        ALTER TABLE ONLY gradeable_overall_comment
            ADD CONSTRAINT gradeable_overall_comment_goc_user_id_fkey FOREIGN KEY (goc_user_id) REFERENCES users(user_id) ON DELETE CASCADE;

        """
    )

    database.execute("ALTER TABLE gradeable_overall_comment DROP CONSTRAINT IF EXISTS gradeable_overall_comment_goc_team_id_fkey")
    database.execute(
        """
        ALTER TABLE ONLY gradeable_overall_comment
            ADD CONSTRAINT gradeable_overall_comment_goc_team_id_fkey FOREIGN KEY (goc_team_id) REFERENCES gradeable_teams(team_id) ON DELETE CASCADE;

        """
    )

    database.execute("ALTER TABLE gradeable_overall_comment DROP CONSTRAINT IF EXISTS gradeable_overall_comment_goc_grader_id")
    database.execute(
        """
        ALTER TABLE ONLY gradeable_overall_comment
            ADD CONSTRAINT gradeable_overall_comment_goc_grader_id FOREIGN KEY (goc_grader_id) REFERENCES users(user_id) ON DELETE CASCADE;
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
