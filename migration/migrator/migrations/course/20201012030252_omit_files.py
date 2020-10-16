def up(config, database, semester, course):
    database.execute("""CREATE TABLE IF NOT EXISTS electronic_gradeable_hidden_files (g_id character varying(255) NOT NULL,
     file_wildcard character varying(255) NOT NULL, 
     lowest_access_group INTEGER NOT NULL DEFAULT 3, 
     CONSTRAINT electronic_gradeable_hidden_files_pk PRIMARY KEY (g_id, file_wildcard), 
     CONSTRAINT electronic_gradeable_hidden_files_fk FOREIGN KEY (g_id) REFERENCES electronic_gradeable(g_id) ON DELETE CASCADE);""")

def down(config, database, semester, course):
	pass