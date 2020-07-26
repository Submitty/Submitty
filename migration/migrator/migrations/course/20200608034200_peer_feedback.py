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
       CREATE TABLE IF NOT EXISTS peer_feedback (
            grader_id character varying(255) NOT NULL,
            user_id character varying(255) NOT NULL,
            g_id character varying(255) NOT NULL,
            feedback character varying(255)
        );
        """
    )
    
    database.execute("ALTER TABLE peer_feedback DROP CONSTRAINT IF EXISTS peer_feedback_pkey")
    database.execute(
        """
        ALTER TABLE ONLY peer_feedback
            ADD CONSTRAINT peer_feedback_pkey PRIMARY KEY (g_id, grader_id, user_id);
        """
    )
    
    database.execute("ALTER TABLE peer_feedback DROP CONSTRAINT IF EXISTS peer_feedback_g_id_fkey")
    database.execute(
        """
        ALTER TABLE ONLY peer_feedback
            ADD CONSTRAINT peer_feedback_g_id_fkey FOREIGN KEY (g_id) REFERENCES gradeable(g_id) ON DELETE CASCADE;
        """
    )

    database.execute("ALTER TABLE peer_feedback DROP CONSTRAINT IF EXISTS peer_feedback_user_id_fkey")
    database.execute(
        """
        ALTER TABLE ONLY peer_feedback
            ADD CONSTRAINT peer_feedback_user_id_fkey FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE;

        """
    )
    
    database.execute("ALTER TABLE peer_feedback DROP CONSTRAINT IF EXISTS peer_feedback_grader_id_fkey")
    database.execute(
        """
        ALTER TABLE ONLY peer_feedback
            ADD CONSTRAINT peer_feedback_grader_id_fkey FOREIGN KEY (grader_id) REFERENCES users(user_id) ON DELETE CASCADE;

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
