def up(config, conn, semester, course):
    with conn.cursor() as cursor:
        cursor.execute("DO $$\
				BEGIN\
				    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'notifications_component') THEN\
				        CREATE TYPE notifications_component AS ENUM ('forum');\
				    END IF;\
				END$$;")
        cursor.execute("CREATE TABLE IF NOT EXISTS notifications (\
			    id serial NOT NULL PRIMARY KEY,\
			    component notifications_component NOT NULL,\
			    metadata TEXT NOT NULL,\
			    content TEXT NOT NULL,\
			    from_user_id VARCHAR(255),\
			    to_user_id VARCHAR(255) NOT NULL,\
			    created_at timestamp with time zone NOT NULL,\
			    seen_at timestamp with time zone\
			)")
        cursor.execute("ALTER TABLE ONLY notifications DROP CONSTRAINT IF EXISTS notifications_to_user_id_fkey")
        cursor.execute("ALTER TABLE ONLY notifications ADD CONSTRAINT notifications_to_user_id_fkey FOREIGN KEY (to_user_id) REFERENCES users(user_id) ON UPDATE CASCADE")
        cursor.execute("ALTER TABLE ONLY notifications DROP CONSTRAINT IF EXISTS notifications_from_user_id_fkey")
        cursor.execute("ALTER TABLE ONLY notifications ADD CONSTRAINT notifications_from_user_id_fkey FOREIGN KEY (from_user_id) REFERENCES users(user_id) ON UPDATE CASCADE")

def down(config, conn, semester, course):
    pass