"""Migration for a given Submitty course database."""
import os

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
    database.execute("""
        ALTER TABLE posts ADD COLUMN IF NOT EXISTS version_id int DEFAULT 1;
        ALTER TABLE forum_posts_history ADD COLUMN IF NOT EXISTS has_attachment boolean DEFAULT false;
        ALTER TABLE forum_posts_history ADD COLUMN IF NOT EXISTS version_id int DEFAULT 1;
        CREATE TABLE IF NOT EXISTS forum_attachments ( 
                     post_id int NOT NULL,
                     file_name character varying NOT NULL,
                     version_added int NOT NULL DEFAULT 1,
                     version_deleted int NOT NULL DEFAULT 0,
        )
    """)

    #update forum_posts_history with version_id
    modified_posts = database.execute("SELECT DISTINCT post_id FROM forum_posts_history;")
    for post in modified_posts:
        all_versions = database.execute("SELECT edit_timestamp FROM forum_posts_history WHERE post_id = {post} ORDER BY edit_timestamp;")
        i = 0
        for version in all_versions:
            i += 1
            database.execute("UPDATE forum_posts_history SET version_id = {i} WHERE post_id = {post} AND edit_timestamp = {version};")
        database.execute("UPDATE posts SET version_id = {i} WHERE post_id = {post};")

    #set attachment names of existing posts
    posts = database.execute("""
        SELECT * FROM posts WHERE has_attachment = true;
    """)
    for post in posts:
        post_id = post[0]
        thread_id = post[1]
        img_path = f"/var/local/submitty/courses/{semester}/{course}/forum_attachments/{thread_id}/{post_id}"
        all_imgs = os.listdir(img_path)
        for img in all_imgs:
            database.execute(f"""
                         INSERT INTO forum_attachments (post_id, file_name, version_added, version_deleted) VALUES ({post_id}, {img}, 1, 0)
                         UPDATE forum_posts_history SET has_attachment = true WHERE post_id = {post_id};
                         """)


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
