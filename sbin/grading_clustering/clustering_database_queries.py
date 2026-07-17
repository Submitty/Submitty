import json
import os
import datetime
from sqlalchemy import create_engine, text
import sys

try:
    CONFIG_PATH = os.path.join(
        os.path.dirname(os.path.realpath(__file__)), '..', '..', 'config')
    with open(os.path.join(CONFIG_PATH, 'database.json')) as open_file:
        DATABASE_CONFIG = json.load(open_file)

    DB_HOST = DATABASE_CONFIG['database_host']
    DB_USER = DATABASE_CONFIG['database_user']
    DB_PASSWORD = DATABASE_CONFIG['database_password']
except Exception as config_fail_error:
    print(f"[{datetime.datetime.now()}] ERROR: Database Configuration Failed {config_fail_error}")
    sys.exit(1)


def setup_course_db(db_name):
    """Set up a connection with a specific course database."""
    # pylint: disable=duplicate-code
    if os.path.isdir(DB_HOST):
        conn_string = f"postgresql://{DB_USER}:{DB_PASSWORD}@/{db_name}?host={DB_HOST}"
    else:
        conn_string = f"postgresql://{DB_USER}:{DB_PASSWORD}@{DB_HOST}/{db_name}"

    engine = create_engine(conn_string)
    return engine.connect()


def get_active_submitters(conn, gradeable_id):
    """Fetch active submitters for a gradeable to pass to the clustering algorithm."""
    query = text("""
        SELECT DISTINCT user_id, team_id, active_version
        FROM electronic_gradeable_version
        WHERE g_id = :gradeable_id AND active_version > 0
    """)
    result = conn.execute(query, {"gradeable_id": gradeable_id})
    submitters = []
    for row in result:
        try:
            user_id = row.user_id
            team_id = row.team_id
            active_version = row.active_version
        except AttributeError:
            user_id = row[0]
            team_id = row[1]
            active_version = row[2]

        submitters.append({
            'user_id': user_id,
            'team_id': team_id,
            'active_version': active_version
        })
    return submitters


def bulk_insert_clustering(conn, gradeable_id, algorithm, cluster_groups):
    """Insert clustering results back into the database."""
    # delete old configuration (cascades to clusters and members)
    delete_query = text("DELETE FROM ta_grading_clustering_configs WHERE g_id = :gradeable_id")
    conn.execute(delete_query, {"gradeable_id": gradeable_id})

    # insert new configuration
    config_query = text("""
        INSERT INTO ta_grading_clustering_configs (g_id, algorithm, created_at)
        VALUES (:gradeable_id, :algorithm, NOW())
        RETURNING id
    """)
    result = conn.execute(config_query, {"gradeable_id": gradeable_id, "algorithm": algorithm})
    row = result.fetchone()
    config_id = row.id if hasattr(row, 'id') else row[0]

    # Bulk insert clusters to prevent excessive queries
    cluster_names = [name for name, members in cluster_groups.items() if members]
    if not cluster_names:
        if hasattr(conn, 'commit'):
            conn.commit()
        return

    # Use a parameterized VALUES clause for clusters
    cluster_values = []
    cluster_params = {"config_id": config_id}
    for i, name in enumerate(cluster_names):
        cluster_values.append(f"(:config_id, :name_{i})")
        cluster_params[f"name_{i}"] = name

    cluster_insert_sql = "INSERT INTO ta_grading_clusters (config_id, cluster_name) VALUES " + ", ".join(cluster_values) + " RETURNING id, cluster_name"
    cluster_query = text(cluster_insert_sql)
    result = conn.execute(cluster_query, cluster_params)

    cluster_id_map = {}
    for row in result:
        cluster_id = row.id if hasattr(row, 'id') else row[0]
        cluster_name = row.cluster_name if hasattr(row, 'cluster_name') else row[1]
        cluster_id_map[cluster_name] = cluster_id

    # Bulk insert members
    member_values = []
    member_params = {}
    m_idx = 0
    for name, members in cluster_groups.items():
        if not members:
            continue
        c_id = cluster_id_map[name]
        for m in members:
            member_values.append(f"(:cid_{m_idx}, :uid_{m_idx}, :tid_{m_idx}, :av_{m_idx})")
            member_params[f"cid_{m_idx}"] = c_id
            member_params[f"uid_{m_idx}"] = m['user_id']
            member_params[f"tid_{m_idx}"] = m['team_id']
            member_params[f"av_{m_idx}"] = m['active_version']
            m_idx += 1

    if member_values:
        member_insert_sql = "INSERT INTO ta_grading_clusters_members (cluster_id, user_id, team_id, active_version) VALUES " + ", ".join(member_values)
        member_query = text(member_insert_sql)
        conn.execute(member_query, member_params)

    if hasattr(conn, 'commit'):
        conn.commit()
