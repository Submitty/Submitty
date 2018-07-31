def up(config, conn, semester, course):
    with conn.cursor() as cursor:
        cursor.execute("ALTER TABLE electronic_gradeable DROP COLUMN if exists eg_regrade_request_date")
        cursor.execute("ALTER TABLE electronic_gradeable ADD COLUMN eg_regrade_request_date timestamp(6) with time zone")

def down(config, conn, semester, course):
    pass    
