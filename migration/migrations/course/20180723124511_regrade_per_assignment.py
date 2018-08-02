def up(config, conn, semester, course):
    with conn.cursor() as cursor:
        cursor.execute("ALTER TABLE electronic_gradeable DROP COLUMN IF EXISTS eg_regrade_request_date")
        cursor.execute("ALTER TABLE electronic_gradeable ADD COLUMN eg_regrade_request_date timestamp(6) with time zone")
        cursor.execute("UPDATE electronic_gradeable SET eg_regrade_request_date='9999-08-02'")
        cursor.execute("ALTER TABLE electronic_gradeable ALTER COLUMN eg_regrade_request_date SET NOT NULL")
        cursor.execute("ALTER TABLE electronic_gradeable DROP COLUMN IF EXISTS eg_is_regrade_allowed")
        cursor.execute("ALTER TABLE electronic_gradeable ADD COLUMN eg_is_regrade_allowed boolean DEFAULT TRUE")


def down(config, conn, semester, course):
    pass    
