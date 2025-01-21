import os
import grp
from pathlib import Path
import shutil

def up(config, database):
    community_events_dir = Path(config.submitty['submitty_data_dir'], 'community_events')

    # create the directories
    os.makedirs(str(community_events_dir), exist_ok=True)

    php_user = config.submitty_users['php_user']

    # set the owner/group/permissions
    os.system(f"chown -R {php_user}:{php_user} {str(community_events_dir)}")
    os.system(f"chmod -R u+rwx {str(community_events_dir)}")
    os.system(f"chmod -R g+rwxs {str(community_events_dir)}")
    os.system(f"chmod -R o-rwx {str(community_events_dir)}")
    database.execute("""
        CREATE TABLE IF NOT EXISTS community_events (
            id SERIAL PRIMARY KEY,
            community_path VARCHAR(255) NOT NULL,
            name VARCHAR(255) NOT NULL,
            folder_name VARCHAR(255) NOT NULL,
            extra_info VARCHAR(255),
            link_name VARCHAR(255),
            release_date timestamp(6),
            closing_date timestamp(6)
        )
    """)

def down(config, database):
    #sometimes have to drop the table if weird values get added in
    pass