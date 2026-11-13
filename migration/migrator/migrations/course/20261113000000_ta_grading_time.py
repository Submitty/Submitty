"""Migration for a given Submitty course database."""


def up(config, database, semester, course):
    database.execute("""
        CREATE TABLE ta_grading_time (
            session_id character varying(64) PRIMARY KEY,
            g_id character varying(255) NOT NULL REFERENCES gradeable (g_id) ON DELETE CASCADE,
            submitter_id character varying(255) NOT NULL,
            grader_id character varying(255) NOT NULL REFERENCES users (user_id) ON DELETE CASCADE,
            opened_at timestamp with time zone NOT NULL,
            closed_at timestamp with time zone
        )
    """)
    database.execute("""
        CREATE INDEX ta_grading_time_g_id_submitter_id_idx
            ON ta_grading_time (g_id, submitter_id)
            WHERE closed_at IS NOT NULL
    """)


def down(config, database, semester, course):
    database.execute("DROP TABLE IF EXISTS ta_grading_time")
