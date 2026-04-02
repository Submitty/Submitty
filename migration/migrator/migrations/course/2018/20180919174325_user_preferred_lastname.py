def up(config, database, semester, course):
    if not database.table_has_column('users', 'user_preferred_lastname'):
        database.execute('ALTER TABLE users ADD COLUMN user_preferred_lastname character varyings')
