def up(config, conn, semester, course):
    with conn.cursor() as cursor:
        cursor.execute("SELECT * FROM electronic_gradeable LIMIT 0")
        colnames = [desc[0] for desc in cursor.description]
        if 'eg_regrade_request_date' not in colnames:
            cursor.execute("ALTER TABLE electronic_gradeable ADD COLUMN eg_regrade_request_date timestamp(6) with time zone")
        if 'eg_regrade_allowed' not in colnames:
            cursor.execute("ALTER TABLE electronic_gradeable ADD COLUMN eg_regrade_allowed boolean DEFAULT TRUE NOT NULL")
        cursor.execute("UPDATE electronic_gradeable SET eg_regrade_request_date='9999-08-02' WHERE eg_regrade_request_date IS NULL")
        cursor.execute("ALTER TABLE electronic_gradeable ALTER COLUMN eg_regrade_request_date SET NOT NULL")

def down(config, conn, semester, course):
    with conn.cursor() as cursor:
        cursor.execute("ALTER TABLE electronic_gradeable ALTER COLUMN eg_regrade_request_date DROP NOT NULL")    
