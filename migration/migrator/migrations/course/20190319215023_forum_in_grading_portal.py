def up(config, database, semester, course):
    database.execute("ALTER TABLE gradeable ADD COLUMN IF NOT EXISTS thread_ids json DEFAULT '{}' NOT NULL")
    database.execute("ALTER TABLE gradeable ADD COLUMN IF NOT EXISTS has_dicussion boolean DEFAULT FALSE NOT NULL")

def down(config, database, semester, course):
    pass

