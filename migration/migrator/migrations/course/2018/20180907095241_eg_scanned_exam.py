def up(config, database, semester, course):
    if not database.table_has_column('electronic_gradeable', 'eg_scanned_exam'):
        database.execute('ALTER TABLE electronic_gradeable ADD COLUMN eg_scanned_exam BOOL NOT NULL DEFAULT FALSE')
    if not database.table_has_column('electronic_gradeable', 'eg_student_view_after_grades'):
        database.execute('ALTER TABLE electronic_gradeable ADD COLUMN eg_student_view_after_grades BOOL NOT NULL DEFAULT FALSE')
