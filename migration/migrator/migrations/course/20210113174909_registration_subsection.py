
def up(config, database, term, course):
    database.execute('ALTER TABLE users ADD COLUMN IF NOT EXISTS registration_subsection character varying(255)')
