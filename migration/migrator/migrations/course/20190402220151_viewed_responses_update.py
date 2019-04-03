def up(config, database, semester, course):
    database.execute("ALTER TABLE viewed_responses ADD PRIMARY KEY(thread_id, user_id)")

def down(config, database, semester, course):
    pass
