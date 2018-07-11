def up(config, conn, semester, course):
	with conn.cursor() as cursor:
		cursor.execute("ALTER TABLE ONLY posts DROP COLUMN resolved")


def down(config, conn, semester, course):
	with conn.cursor() as cursor:
		cursor.execute("ALTER TABLE ONLY posts ADD COLUMN resolved BOOLEAN NOT NULL DEFAULT false")
