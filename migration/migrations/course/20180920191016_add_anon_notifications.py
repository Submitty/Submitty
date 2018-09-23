def up(config, conn, semester, course):
	with conn.cursor() as cursor:
		cursor.execute('ALTER TABLE notifications ADD COLUMN IF NOT EXISTS anonymous BOOLEAN DEFAULT FALSE NOT NULL')

def down(config, conn, semester, course):
	pass
