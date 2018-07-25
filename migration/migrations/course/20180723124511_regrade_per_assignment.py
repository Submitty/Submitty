def up(config, conn, semester, course):
    with conn.cursor() as cursor:
        cursor.execute("ALTER TABLE electronic_gradeable DROP COLUMN if exists eg_regrade_request_date")
        cursor.execute("ALTER TABLE electronic_gradeable ADD COLUMN eg_regrade_request_date timestamp(6) with time zone")
        cursor.execute("ALTER TABLE electronic_gradeable DROP COLUMN if exists eg_is_regrade_allowed")
        cursor.execute("ALTER TABLE electronic_gradeable ADD COLUMN eg_is_regrade_allowed boolean DEFAULT TRUE")


def down(config, conn, semester, course):
    pass    
