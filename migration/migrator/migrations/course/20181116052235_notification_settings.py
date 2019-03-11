def up(config, database, semester, course):
    database.execute("""CREATE TABLE IF NOT EXISTS notification_settings (
                        user_id character varying NOT NULL,
                        merge_threads BOOLEAN DEFAULT FALSE NOT NULL,
                        all_new_threads BOOLEAN DEFAULT FALSE NOT NULL,
                        all_new_posts BOOLEAN DEFAULT FALSE NOT NULL,
                        all_modifications_forum BOOLEAN DEFAULT FALSE NOT NULL,
                        reply_in_post_thread BOOLEAN DEFAULT FALSE NOT NULL);""")
    database.execute("ALTER TABLE ONLY notification_settings DROP CONSTRAINT IF EXISTS notification_settings_pkey;")
    database.execute("ALTER TABLE ONLY notification_settings DROP CONSTRAINT IF EXISTS notification_settings_fkey;")
    database.execute("ALTER TABLE ONLY notification_settings ADD CONSTRAINT notification_settings_pkey PRIMARY KEY (user_id);")
    database.execute("ALTER TABLE ONLY notification_settings ADD CONSTRAINT notification_settings_fkey FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE;")
    database.execute("INSERT INTO notification_settings SELECT user_id from users ON CONFLICT DO NOTHING")
