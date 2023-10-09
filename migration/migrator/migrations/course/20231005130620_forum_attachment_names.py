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
        ALTER TABLE posts ADD COLUMN IF NOT EXISTS attachment_name character varying(255) DEFAULT '';
        ALTER TABLE forum_posts_history ADD COLUMN IF NOT EXISTS has_attachment boolean DEFAULT false;
        ALTER TABLE forum_posts_history ADD COLUMN IF NOT EXISTS attachment_name character varying(255) DEFAULT '';
    """)

    #set attachment names of existing posts
    posts = database.execute("""
        SELECT * FROM posts WHERE has_attachment = true;
    """)
    for post in posts:
        post_id = post[0]
        thread_id = post[1]
        img_path = f"/var/local/submitty/courses/{semester}/{course}/forum_attachments/{thread_id}/{post_id}"
        attachment_name = ""
        all_imgs = os.listdir(img_path)
        for img in all_imgs:
            if attachment_name == "":
                attachment_name = img
            else:
                attachment_name = attachment_name + "\n" + img
        database.execute(f"""
                         UPDATE posts SET attachment_name = '{attachment_name}' WHERE id = {post_id};
                         UPDATE forum_posts_history SET has_attachment = true, attachment_name = '{attachment_name}' WHERE post_id = {post_id};
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
