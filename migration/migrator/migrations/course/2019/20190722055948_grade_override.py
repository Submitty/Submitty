"""Migration for a given Submitty course database."""


def up(config, database, semester, course):
    if not database.has_table('grade_override'):
        database.execute("CREATE TABLE grade_override (user_id character varying(255) NOT NULL,g_id character varying(255) NOT NULL,marks float NOT NULL,comment character varying )")
        
        database.execute("ALTER TABLE ONLY grade_override ADD CONSTRAINT grade_override_pkey PRIMARY KEY (user_id, g_id);")
        database.execute("ALTER TABLE ONLY grade_override ADD CONSTRAINT grade_override_g_id_fkey FOREIGN KEY (g_id) REFERENCES gradeable(g_id) ON DELETE CASCADE;")
        database.execute("ALTER TABLE ONLY grade_override ADD CONSTRAINT grade_override_user_id_fkey FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE;")
    


def down(config, database, semester, course):
    pass
