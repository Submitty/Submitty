def up(config, database, term, course):
    database.execute("ALTER TABLE electronic_gradeable ADD COLUMN IF NOT EXISTS eg_thread_ids json DEFAULT '{}' NOT NULL")
    database.execute("ALTER TABLE electronic_gradeable ADD COLUMN IF NOT EXISTS eg_has_discussion boolean DEFAULT FALSE NOT NULL")

def down(config, database, term, course):
    pass

