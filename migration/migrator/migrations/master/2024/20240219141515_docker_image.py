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
        CREATE TABLE IF NOT EXISTS public.docker_images (
            image_name character varying UNIQUE NOT NULL,
            user_id character varying REFERENCES users (user_id)
        );
    """)

    # Add existing containers to db with no owner
    container_file_path = config.config_path / 'autograding_containers.json'
    with open(container_file_path) as container_file:
        container_data = json.load(container_file)
    images = set()
    for capability in container_data:
        for image in container_data[capability]:
            images.add(image)
    existing_images = set()
    for row in database.execute("SELECT image_name FROM docker_images").all():
        existing_images.add(row[0])
    for image in images:
        if image not in existing_images:
            database.session.execute("INSERT INTO docker_images (image_name, user_id) VALUES (:name, NULL);", {"name": image})


def down(config, database):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    pass
