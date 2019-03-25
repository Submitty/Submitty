def up(config, database, semester, course):
    if not database.table_has_column('threads', 'lock_thread_date'):
        database.excecute('ALTER TABLE threads ADD COLUMN lock_thread_date timestamp with time zone')
