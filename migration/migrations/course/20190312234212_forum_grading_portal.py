def up(config, conn, semester, course):
    with conn.cursor() as cursor:
        cursor.execute("ALTER TABLE gradeable ADD COLUMN thread_ids json DEFAULT '{}' ")

def down(config, conn, semester, course):
    with conn.cursor() as cursor:
        pass