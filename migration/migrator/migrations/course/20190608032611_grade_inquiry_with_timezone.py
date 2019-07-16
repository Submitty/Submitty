"""Migration for a given Submitty course database"""
"""adds time zone info so PostgreSQL can convert grade inquiry submitted time to correct time"""

def up(config, database, semester, course):
    database.execute("ALTER TABLE regrade_discussion ALTER COLUMN timestamp TYPE TIMESTAMP WITH TIME ZONE;")
    database.execute("ALTER TABLE regrade_requests ALTER COLUMN timestamp TYPE TIMESTAMP WITH TIME ZONE;")
