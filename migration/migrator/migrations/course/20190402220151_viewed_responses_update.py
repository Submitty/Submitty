def up(config, database, semester, course):
    database.execute("DELETE FROM viewed_responses AS a USING viewed_responses AS b WHERE a.timestamp < b.timestamp AND a.thread_id = b.thread_id AND a.user_id = b.user_id")
    database.execute("ALTER TABLE viewed_responses DROP CONSTRAINT IF EXISTS viewed_responses_pkey")
    database.execute("ALTER TABLE viewed_responses ADD PRIMARY KEY(thread_id, user_id)")
    pass

def down(config, database, semester, course):
    database.execute("ALTER TABLE viewed_responses DROP CONSTRAINT IF EXISTS viewed_responses_pkey")
    pass
