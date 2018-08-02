def up(config, conn, semester, course):
    with conn.cursor() as cursor:
        cursor.execute("ALTER TABLE regrade_requests DROP COLUMN if exists g_id");
        cursor.execute("ALTER TABLE regrade_requests DROP COLUMN if exists gradeable_id");
        cursor.execute("ALTER TABLE regrade_requests ADD COLUMN eg_regrade_request_date timestamp(6) with time zone")

def down(config, conn, semester, course):
    pass    
