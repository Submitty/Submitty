def up(config, database, semester, course):
    database.execute("ALTER TABLE gradeable ALTER COLUMN g_instructions_url TYPE VARCHAR")
