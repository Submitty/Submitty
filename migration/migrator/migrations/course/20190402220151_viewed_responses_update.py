def up(config, database, semester, course):
    database.execute("ALTER TABLE viewed_responses DROP CONSTRAINT IF EXISTS viewed_responses_pkey")
    database.execute("ALTER TABLE viewed_responses ADD PRIMARY KEY(thread_id, user_id)")
    pass

def down(config, database, semester, course):
    database.execute("ALTER TABLE viewed_responses DROP CONSTRAINT IF EXISTS viewed_responses_pkey")
    pass
