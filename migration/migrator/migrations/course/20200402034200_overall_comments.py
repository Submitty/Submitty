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
       CREATE TABLE IF NOT EXISTS gradeable_data_overall_comment (
            goc_id integer NOT NULL,
            g_id character varying(255) NOT NULL,
            goc_user_id character varying(255),
            goc_team_id character varying(255),
            goc_grader_id character varying(255) NOT NULL,
            goc_overall_comment character varying NOT NULL,
            CONSTRAINT goc_user_team_id_check CHECK (goc_user_id IS NOT NULL OR goc_team_id IS NOT NULL)
        );
        """
    )


    database.execute("ALTER TABLE gradeable_data_overall_comment DROP CONSTRAINT IF EXISTS gradeable_data_overall_comment_pkey")
    database.execute(
        """
        ALTER TABLE ONLY gradeable_data_overall_comment
            ADD CONSTRAINT gradeable_data_overall_comment_pkey PRIMARY KEY (goc_id);
        """
    )
    
    database.execute("ALTER TABLE gradeable_data_overall_comment DROP CONSTRAINT IF EXISTS gradeable_data_overall_comment_g_id_fkey")
    database.execute(
        """
        ALTER TABLE ONLY gradeable_data_overall_comment
            ADD CONSTRAINT gradeable_data_overall_comment_g_id_fkey FOREIGN KEY (g_id) REFERENCES gradeable(g_id) ON DELETE CASCADE;
        """
    )

    database.execute("ALTER TABLE gradeable_data_overall_comment DROP CONSTRAINT IF EXISTS gradeable_data_overall_comment_goc_user_id_fkey")
    database.execute(
        """
        ALTER TABLE ONLY gradeable_data_overall_comment
            ADD CONSTRAINT gradeable_data_overall_comment_goc_user_id_fkey FOREIGN KEY (goc_user_id) REFERENCES users(user_id) ON DELETE CASCADE;

        """
    )

    database.execute("ALTER TABLE gradeable_data_overall_comment DROP CONSTRAINT IF EXISTS gradeable_data_overall_comment_goc_team_id_fkey")
    database.execute(
        """
        ALTER TABLE ONLY gradeable_data_overall_comment
            ADD CONSTRAINT gradeable_data_overall_comment_goc_team_id_fkey FOREIGN KEY (goc_team_id) REFERENCES gradeable_teams(team_id) ON DELETE CASCADE;

        """
    )

    database.execute("ALTER TABLE gradeable_data_overall_comment DROP CONSTRAINT IF EXISTS gradeable_data_overall_comment_goc_grader_id")
    database.execute(
        """
        ALTER TABLE ONLY gradeable_data_overall_comment
            ADD CONSTRAINT gradeable_data_overall_comment_goc_grader_id FOREIGN KEY (goc_grader_id) REFERENCES users(user_id) ON DELETE CASCADE;
        """
    )

    database.execute("ALTER TABLE gradeable_data_overall_comment DROP CONSTRAINT IF EXISTS gradeable_data_overall_comment_user_unique")
    database.execute("ALTER TABLE ONLY gradeable_data_overall_comment ADD CONSTRAINT gradeable_data_overall_comment_user_unique UNIQUE (g_id, goc_user_id, goc_grader_id);")

    database.execute("ALTER TABLE gradeable_data_overall_comment DROP CONSTRAINT IF EXISTS gradeable_data_overall_comment_team_unique")
    database.execute("ALTER TABLE ONLY gradeable_data_overall_comment ADD CONSTRAINT gradeable_data_overall_comment_team_unique UNIQUE (g_id, goc_team_id, goc_grader_id);")







    database.execute(
        """
        CREATE SEQUENCE IF NOT EXISTS gradeable_data_overall_comment_goc_id_seq
            START WITH 1
            INCREMENT BY 1
            NO MINVALUE
            NO MAXVALUE
            CACHE 1;
        """)

    database.execute("ALTER SEQUENCE gradeable_data_overall_comment_goc_id_seq OWNED BY gradeable_data_overall_comment.goc_id;")
    database.execute("ALTER TABLE ONLY gradeable_data_overall_comment ALTER COLUMN goc_id SET DEFAULT nextval('gradeable_data_overall_comment_goc_id_seq'::regclass);")




    # All old overall comments belong to the instructor
    instructor_id = database.execute("SELECT user_id FROM users WHERE user_group = 1;").first()[0]
    rows = database.execute("""
        SELECT
            g_id,
            gd_user_id,
            gd_team_id,
            gd_overall_comment
        FROM
            gradeable_data;
        """
    )

    for g_id, user_id, team_id, comment in rows:
        query = '''
            INSERT INTO gradeable_data_overall_comment
                (
                    g_id,
                    goc_user_id,
                    goc_team_id,
                    goc_grader_id,
                    goc_overall_comment
                ) VALUES (
                    :g_id, :user_id, :team_id, :grader_id, :comment
                )
            ON CONFLICT
                DO NOTHING;
            '''
        params = {
            'g_id':g_id,
            'user_id':user_id,
            'team_id':team_id,
            'grader_id':instructor_id,
            'comment':comment
        }
        database.session.execute(query, params)


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
