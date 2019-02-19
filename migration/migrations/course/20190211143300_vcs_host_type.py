def up(config, conn, semester, course):
    with conn.cursor() as cursor:
        cursor.execute("ALTER TABLE electronic_gradeable ADD COLUMN eg_vcs_host_type INTEGER DEFAULT 0")


def down(config, conn, semester, course):
    with conn.cursor() as cursor:
        cursor.execute("ALTER TABLE ONLY electronic_gradeable DROP COLUMN eg_vcs_host_type")
