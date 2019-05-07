def up(config, database, semester, course):
    database.execute("ALTER TABLE electronic_gradeable ADD COLUMN IF NOT EXISTS eg_vcs_host_type INTEGER DEFAULT 0")


def down(config, database, semester, course):
    pass
