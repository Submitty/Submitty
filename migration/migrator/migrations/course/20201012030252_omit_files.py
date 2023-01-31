def up(config, database, term, course):
    database.execute("""ALTER TABLE electronic_gradeable ADD eg_hidden_files character varying(1024);""")
    pass

def down(config, database, term, course):
	database.execute("""ALTER TABLE electronic_gradeable DROP COLUMN eg_hidden_files;""")
	pass
