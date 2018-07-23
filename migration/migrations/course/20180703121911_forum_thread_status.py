def up(config, conn, semester, course):
    with conn.cursor() as cursor:
        cursor.execute("ALTER TABLE threads DROP COLUMN if exists status")
        cursor.execute("ALTER TABLE threads ADD COLUMN status int DEFAULT 0 NOT NULL")
        cursor.execute("ALTER TABLE threads DROP CONSTRAINT if exists threads_status_check")
        cursor.execute("ALTER TABLE threads ADD CONSTRAINT threads_status_check CHECK (status IN (-1,0,1))")
        cursor.execute("ALTER TABLE posts DROP COLUMN if exists resolved")

def down(config, conn, semester, course):
    with conn.cursor() as cursor:
        cursor.execute("ALTER TABLE posts ADD COLUMN resolved BOOLEAN DEFAULT false NOT NULL")