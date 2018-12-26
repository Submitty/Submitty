def up(config, conn, semester, course):
    with conn.cursor() as cursor:
        cursor.execute("UPDATE threads SET deleted = false WHERE merged_thread_id <> -1")

def down(config, conn, semester, course):
	pass
