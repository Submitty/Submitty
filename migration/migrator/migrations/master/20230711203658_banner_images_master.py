import os
import grp
from pathlib import Path
import shutil

def up(config, database):
    banner_images_dir = Path(config.submitty['submitty_data_dir'], 'banner_images')

    # create the directories
    os.makedirs(str(banner_images_dir), exist_ok=True)

    php_user = config.submitty_users['php_user']

    # set the owner/group/permissions
    os.system("chown -R "+php_user+":"+php_user+" "+str(banner_images_dir))
    os.system("chmod -R u+rwx  "+str(banner_images_dir))
    os.system("chmod -R g+rwxs "+str(banner_images_dir))
    os.system("chmod -R o-rwx  "+str(banner_images_dir))

    query = """
        CREATE TABLE banner_images (
            id SERIAL PRIMARY KEY,
            release_date DATE NOT NULL,
            closing_date DATE NOT NULL,
            name VARCHAR(255) NOT NULL,
            extra_info VARCHAR(255)
        )
    """

    database.execute(query)

    # Associate the sequence with the id column
    alter_query = "ALTER TABLE banner_images ALTER COLUMN id SET DEFAULT nextval('banner_images_id_seq'::regclass)"
    database.execute(alter_query)

def down(config, database):
    banner_images_dir = Path(config.submitty['submitty_data_dir'], 'banner_images')

    if os.path.exists(str(banner_images_dir)):
        # Delete all contents within banner_images directory
        shutil.rmtree(str(banner_images_dir))

    query = "DROP TABLE IF EXISTS banner_images"
    database.execute(query)
