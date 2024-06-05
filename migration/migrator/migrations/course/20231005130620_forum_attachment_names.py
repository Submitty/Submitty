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
        ALTER TABLE posts 
        ADD COLUMN IF NOT EXISTS version_id int DEFAULT 1;
        
        ALTER TABLE forum_posts_history 
        ADD COLUMN IF NOT EXISTS has_attachment boolean DEFAULT false;
        
        ALTER TABLE forum_posts_history 
        ADD COLUMN IF NOT EXISTS version_id int;
        WITH 
            versions AS (
                SELECT post_id, edit_timestamp,
                     ROW_NUMBER() OVER 
                     (
                        PARTITION BY post_id ORDER BY edit_timestamp
                     )
                    AS version_id 
                    FROM forum_posts_history
                )
                UPDATE forum_posts_history AS p 
                SET version_id = v.version_id 
                FROM versions AS v 
                WHERE p.post_id = v.post_id 
                AND p.edit_timestamp = v.edit_timestamp;
        
        UPDATE posts
        SET version_id = t.countVersionID
        FROM
        (
            SELECT fph.post_id, COUNT(fph.post_id) AS countVersionID
            FROM forum_posts_history fph 
            JOIN posts p ON p.id = fph.post_id 
            GROUP BY fph.post_id
        ) t
        WHERE posts.id = t.post_id;
                     
        CREATE TABLE IF NOT EXISTS forum_attachments ( 
                     post_id int NOT NULL,
                     file_name character varying NOT NULL,
                     version_added int NOT NULL DEFAULT 1,
                     version_deleted int NOT NULL DEFAULT 0
                    );
    """)

    #check if forum_attachments was already created
    img_table = database.execute("SELECT * FROM forum_attachments;").all()
    if (len(img_table) > 0):
        return
    #set attachment names of existing posts
    posts = database.execute("""
        SELECT * FROM posts WHERE has_attachment = true;
    """)
    for post in posts:
        post_id = str(post[0])
        thread_id = post[1]
        img_path = f"/var/local/submitty/courses/{semester}/{course}/forum_attachments/{thread_id}/{post_id}"
        all_imgs = os.listdir(img_path) if (os.path.isdir(img_path)) else []
        for img in all_imgs:
            database.session.execute("""
                         INSERT INTO forum_attachments (post_id, file_name, version_added, version_deleted) VALUES (:id, :name, 1, 0);
                         UPDATE forum_posts_history SET has_attachment = true WHERE post_id = :id;
                         """, {"id": post_id, "name": img})


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
