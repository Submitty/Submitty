def up(config, database, semester, course):
    database.execute("ALTER TABLE threads ADD COLUMN status int DEFAULT 0 NOT NULL")
    database.execute("ALTER TABLE threads ADD CONSTRAINT threads_status_check CHECK (status IN (-1,0,1))")
    database.execute("ALTER TABLE posts DROP COLUMN resolved")

def down(config, database, semester, course):
    database.execute("ALTER TABLE threads DROP COLUMN status")
    database.execute("ALTER TABLE posts ADD COLUMN resolved BOOLEAN DEFAULT false NOT NULL")
