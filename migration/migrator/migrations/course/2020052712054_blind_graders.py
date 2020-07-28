def up(config, database, semester, course):
    
    database.execute('ALTER TABLE electronic_gradeable ADD COLUMN IF NOT EXISTS eg_limited_access_blind INTEGER DEFAULT 1')
    database.execute('ALTER TABLE electronic_gradeable ADD COLUMN IF NOT EXISTS eg_peer_blind INTEGER DEFAULT 3')


def down(config, database, semester, course):
    
    pass
