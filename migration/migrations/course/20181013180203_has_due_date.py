def up(config, conn, semester, course):
    with conn.cursor() as cursor:
        cursor.execute("SELECT * FROM electronic_gradeable LIMIT 0")
        colnames = [desc[0] for desc in cursor.description]
        if 'eg_has_due_date' not in colnames:
            cursor.execute('ALTER TABLE electronic_gradeable ADD COLUMN eg_has_due_date BOOL NOT NULL DEFAULT TRUE')


def down(config, conn, semester, course):
    pass
