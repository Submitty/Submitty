
def up(config, database, semester, course):
    database.execute("UPDATE users SET registration_subsection = '' WHERE registration_subsection IS NULL")
    database.execute('ALTER TABLE users ALTER COLUMN registration_subsection SET DEFAULT '' NOT NULL')

def down(config, database, semester, course):
    database.execute('ALTER TABLE users ALTER COLUMN registration_subsection SET DEFAULT null')
