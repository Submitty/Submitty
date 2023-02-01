def up(config, database, term, course):
    database.execute("UPDATE threads SET deleted = false WHERE merged_thread_id <> -1")
