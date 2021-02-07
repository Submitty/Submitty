
def up(config, database, semester, course):
    database.execute('ALTER TABLE users ALTER COLUMN registration_subsection SET DEFAULT '' NOT NULL')
