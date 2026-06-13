"""Migration to create tables for AI-assisted grading clusters."""


def up(config, database, semester, course):
    database.execute("""
        CREATE TABLE IF NOT EXISTS grading_cluster (
            id SERIAL PRIMARY KEY,
            g_id CHARACTER VARYING(255) NOT NULL REFERENCES gradeable(g_id) ON DELETE CASCADE,
            label CHARACTER VARYING(255),
            algorithm CHARACTER VARYING(255) NOT NULL,
            created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW() NOT NULL
        )
    """)

    database.execute("""
        CREATE INDEX IF NOT EXISTS grading_cluster_g_id_idx ON grading_cluster(g_id)
    """)

    database.execute("""
        CREATE TABLE IF NOT EXISTS grading_cluster_members (
            id SERIAL PRIMARY KEY,
            cluster_id INTEGER NOT NULL REFERENCES grading_cluster(id) ON DELETE CASCADE,
            user_id CHARACTER VARYING(255) DEFAULT NULL,
            team_id CHARACTER VARYING(255) DEFAULT NULL,
            CONSTRAINT cluster_member_check CHECK (
                (user_id IS NOT NULL) OR (team_id IS NOT NULL)
            )
        )
    """)

    database.execute("""
        CREATE INDEX IF NOT EXISTS grading_cluster_members_cluster_id_idx
        ON grading_cluster_members(cluster_id)
    """)


def down(config, database, semester, course):
    database.execute("DROP TABLE IF EXISTS grading_cluster_members")
    database.execute("DROP TABLE IF EXISTS grading_cluster")
