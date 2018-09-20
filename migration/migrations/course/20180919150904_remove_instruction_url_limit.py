def up(config, conn, semester, course):
    with conn.cursor() as cursor:
        cursor.execute("ALTER TABLE gradeable ALTER COLUMN g_instructions_url TYPE VARCHAR")


def down(config, conn, semester, course):
    pass
