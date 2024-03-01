"""Migration for the Submitty master database."""
import json

def up(config, database):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    database.execute("""
CREATE TABLE IF NOT EXISTS public.docker_image (
    image_name character varying UNIQUE NOT NULL,
    user_id character varying NOT NULL);
    """)

    # Add existing containers to db with no owner
    container_file_path = config.config_path / 'autograding_containers.json'
    container_file = open(container_file_path)
    container_data = json.load(container_file)
    images = set()
    for capability in container_data:
        for image in container_data[capability]:
            images.add(image)
    existing_images = database.execute("SELECT image_name FROM docker_image").all()[0]
    for image in images:
        if (image not in existing_images):
            database.session.execute("INSERT INTO docker_image (image_name, user_id) VALUES (:name, '');", {"name": image})


def down(config, database):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    pass
