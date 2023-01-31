"""Migration for a given Submitty course database."""

def up(config, database, term, course):
    if not database.table_has_column('teams', 'last_viewed_time'):
        database.execute('ALTER TABLE teams ADD COLUMN last_viewed_time timestamp with time zone')

def down(config, database, term, course):
    pass
