def up(conn):
    with conn.cursor() as cursor:
        cursor.execute("ALTER TABLE ONLY categories_list ADD COLUMN rank int")
        cursor.execute("ALTER TABLE ONLY categories_list ADD COLUMN color varchar DEFAULT '#000080' NOT NULL")
    pass


def down(conn):
    with conn.cursor() as cursor:
        cursor.execute("ALTER TABLE ONLY categories_list DROP COLUMN rank")
        cursor.execute("ALTER TABLE ONLY categories_list DROP COLUMN color")
    pass
