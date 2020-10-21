def up(config, database, semester, course):
    database.execute("""ALTER TABLE electronic_gradeable ADD eg_hidden_files character varying(1024);""")

def down(config, database, semester, course):
	database.execute("""ALTER TABLE electronic_gradeable DROP COLUMN eg_hidden_files;""")
