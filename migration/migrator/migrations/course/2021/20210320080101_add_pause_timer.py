from datetime import datetime
native_utc_dt = datetime.utcnow();
utc_string = native_utc_dt.strftime("%Y-%m-%d %H:%M:%S") + " UTC";

def up(config, database, semester, course):
    database.execute('ALTER TABLE queue ADD COLUMN IF NOT EXISTS time_paused INTEGER NOT NULL DEFAULT 0;')
    database.execute('ALTER TABLE queue ADD COLUMN IF NOT EXISTS time_paused_start TIMESTAMP WITH TIME ZONE;')
    database.execute('UPDATE queue SET time_paused_start = \'' + utc_string + '\' WHERE paused = true AND time_paused_start IS NULL;')
