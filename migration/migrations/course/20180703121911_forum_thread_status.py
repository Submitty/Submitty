def up(config, conn, semester, course):
    with conn.cursor() as cursor:
        cursor.execute("ALTER TABLE ONLY threads ADD COLUMN status int  DEFAULT 0 NOT NULL")
        cursor.execute("ALTER TABLE threads ADD CONSTRAINT threads_status_check CHECK (status IN (-1,0,1))")

def down(config, conn, semester, course):
    with conn.cursor() as cursor:
        cursor.execute("ALTER TABLE ONLY threads DROP COLUMN status")
