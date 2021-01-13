
def up(config, database, semester, course):
    database.execute('ALTER TABLE users ADD COLUMN registration_subsection character varying(255)')


def down(config, database, semester, course):
    database.execute('ALTER TABLE users DROP IF EXISTS registration_subsection')
