def up(config, conn, semester, course):
    with conn.cursor() as cursor:
        cursor.execute("SELECT * FROM users LIMIT 0")
        colnames = [desc[0] for desc in cursor.description]
        if 'user_preferred_lastname' not in colnames:
            cursor.execute('ALTER TABLE users ADD COLUMN user_preferred_lastname character varyings')

def down(config, conn, semester, course):
    pass
