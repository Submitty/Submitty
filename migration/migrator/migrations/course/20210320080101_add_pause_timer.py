
def up(config, database, semester, course):
    database.execute('ALTER TABLE queue ADD COLUMN IF NOT EXISTS time_paused INTEGER;')
    database.execute('ALTER TABLE queue ADD COLUMN IF NOT EXISTS time_paused_start TIMESTAMP WITH TIME ZONE;')

def down(config, database, semester, course):
    database.execute('ALTER TABLE queue DROP COLUMN IF EXISTS time_paused;')
    database.execute('ALTER TABLE queue DROP COLUMN IF EXISTS time_paused_start;')
