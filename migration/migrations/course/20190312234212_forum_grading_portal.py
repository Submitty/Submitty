def up(config, conn, semester, course):
    with conn.cursor() as cursor:
        cursor.execute("ALTER TABLE gradeable ADD COLUMN thread_ids json DEFAULT '{}' ")
        cursor.execute("ALTER TABLE gradeable ADD COLUMN has_dicussion boolean DEFAULT FALSE")

def down(config, conn, semester, course):
    with conn.cursor() as cursor:
        cursor.execute("ALTER TABLE gradeable DROP COLUMN thread_ids")
        #cursor.execute("ALTER TABLE gradeable DROP COLUMN has_dicussion")
