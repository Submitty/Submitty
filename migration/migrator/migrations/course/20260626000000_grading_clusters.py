"""Migration to create tables for student Submission Clustering"""


def up(config, database, semester, course):
    database.execute("""
        CREATE TABLE IF NOT EXISTS ta_grading_clustering_configs (
            id integer GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
            g_id CHARACTER VARYING(255) NOT NULL UNIQUE REFERENCES gradeable(g_id) ON DELETE CASCADE,
            algorithm CHARACTER VARYING(255) NOT NULL,
            created_at timestamp(0) with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    """)


    database.execute("""
        CREATE TABLE IF NOT EXISTS ta_grading_clusters (
            id integer GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
            config_id INTEGER NOT NULL REFERENCES ta_grading_clustering_configs(id) ON DELETE CASCADE,
            cluster_name CHARACTER VARYING(255)
        )
    """)

    database.execute("""
        CREATE INDEX IF NOT EXISTS ta_grading_clusters_config_id_idx ON ta_grading_clusters(config_id)
    """)

    database.execute("""
        CREATE TABLE IF NOT EXISTS ta_grading_clusters_members (
            id integer GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
            cluster_id INTEGER NOT NULL REFERENCES ta_grading_clusters(id) ON DELETE CASCADE,
            user_id CHARACTER VARYING(255) DEFAULT NULL REFERENCES users(user_id) ON DELETE CASCADE,
            team_id CHARACTER VARYING(255) DEFAULT NULL REFERENCES gradeable_teams(team_id) ON DELETE CASCADE,
            active_version integer NOT NULL,
            CONSTRAINT cluster_member_check CHECK (
                (user_id IS NOT NULL AND team_id IS NULL) OR (user_id IS NULL AND team_id IS NOT NULL)
            )
        )
    """)

    database.execute("""
        CREATE INDEX IF NOT EXISTS ta_grading_clusters_members_cluster_id_idx
        ON ta_grading_clusters_members(cluster_id)
    """)

    database.execute("""
        CREATE INDEX IF NOT EXISTS ta_grading_clusters_members_user_id_idx
        ON ta_grading_clusters_members(user_id)
    """)

    database.execute("""
        CREATE INDEX IF NOT EXISTS ta_grading_clusters_members_team_id_idx
        ON ta_grading_clusters_members(team_id)
    """)


def down(config, database, semester, course):
    pass
