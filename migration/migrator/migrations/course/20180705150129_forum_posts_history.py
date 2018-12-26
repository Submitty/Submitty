def up(config, conn, semester, course):
    with conn.cursor() as cursor:
        cursor.execute('CREATE TABLE IF NOT EXISTS forum_posts_history (\
            "post_id" int NOT NULL,\
            "edit_author" character varying NOT NULL,\
            "content" text NOT NULL,\
            "edit_timestamp" timestamp with time zone NOT NULL\
            )')
        cursor.execute('ALTER TABLE "forum_posts_history" DROP CONSTRAINT IF EXISTS "forum_posts_history_post_id_fk"')
        cursor.execute('ALTER TABLE "forum_posts_history" ADD CONSTRAINT "forum_posts_history_post_id_fk" FOREIGN KEY ("post_id") REFERENCES "posts"("id")')
        cursor.execute('ALTER TABLE "forum_posts_history" DROP CONSTRAINT IF EXISTS "forum_posts_history_edit_author_fk"')
        cursor.execute('ALTER TABLE "forum_posts_history" ADD CONSTRAINT "forum_posts_history_edit_author_fk" FOREIGN KEY ("edit_author") REFERENCES "users"("user_id")')
        cursor.execute('CREATE INDEX IF NOT EXISTS "forum_posts_history_post_id_index" ON "forum_posts_history" ("post_id")')
        cursor.execute('CREATE INDEX IF NOT EXISTS "forum_posts_history_edit_timestamp_index" ON "forum_posts_history" ("edit_timestamp" DESC)')

def down(config, conn, semester, course):
    pass
