def up(conn):
    with conn.cursor() as cursor:
        cursor.execute('CREATE TABLE forum_posts_history (\
			"post_id" int NOT NULL,\
			"edit_author" character varying NOT NULL,\
			"content" text NOT NULL,\
			"edit_timestamp" timestamp with time zone NOT NULL)')
        cursor.execute("ALTER TABLE ONLY forum_posts_history ADD CONSTRAINT forum_posts_history_post_id_fk FOREIGN KEY (post_id) REFERENCES posts(id)")
        cursor.execute("ALTER TABLE ONLY forum_posts_history ADD CONSTRAINT forum_posts_history_edit_author_fk FOREIGN KEY (edit_author) REFERENCES users(user_id)")

def down(conn):
    with conn.cursor() as cursor:
        cursor.execute("DROP TABLE forum_posts_history")
