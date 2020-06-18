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
       DROP TABLE IF EXISTS peer_feedback
        """
    )

    # Create overall comment table
    database.execute(
        """
       CREATE TABLE IF NOT EXISTS peer_feedback (
            pf_id integer NOT NULL,
            grader_id character varying(255) NOT NULL,
            user_id character varying(255),
            team_id character varying(255),
            g_id character varying(255) NOT NULL,
            feedback_full character varying(255),
            feedback_id character varying(255),
            CONSTRAINT user_team_id_check CHECK (user_id IS NOT NULL OR team_id IS NOT NULL)
        );
        """
    )


    database.execute("ALTER TABLE peer_feedback DROP CONSTRAINT IF EXISTS peer_feedback_pkey")
    database.execute(
        """
        ALTER TABLE ONLY peer_feedback
            ADD CONSTRAINT peer_feedback_pkey PRIMARY KEY (pf_id);
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

    database.execute("ALTER TABLE peer_feedback DROP CONSTRAINT IF EXISTS peer_feedback_team_id_fkey")
    database.execute(
        """
        ALTER TABLE ONLY peer_feedback
            ADD CONSTRAINT peer_feedback_team_id_fkey FOREIGN KEY (team_id) REFERENCES gradeable_teams(team_id) ON DELETE CASCADE;

        """
    )

    database.execute(
        """
        CREATE SEQUENCE IF NOT EXISTS peer_feedback_pf_id_seq
            START WITH 1
            INCREMENT BY 1
            NO MINVALUE
            NO MAXVALUE
            CACHE 1;
        """)

    database.execute("ALTER SEQUENCE peer_feedback_pf_id_seq OWNED BY peer_feedback.pf_id;")
    database.execute("ALTER TABLE ONLY peer_feedback ALTER COLUMN pf_id SET DEFAULT nextval('peer_feedback_pf_id_seq'::regclass);")


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
