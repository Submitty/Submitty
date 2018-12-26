def up(config, database, semester, course):
    if not database.table_has_column('electronic_gradeable', 'eg_has_due_date'):
        database.execute('ALTER TABLE electronic_gradeable ADD COLUMN eg_has_due_date BOOL NOT NULL DEFAULT TRUE')
