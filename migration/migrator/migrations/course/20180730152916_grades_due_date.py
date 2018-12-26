def up(config, database, semester, course):
    if not database.table_has_column('gradeable', 'g_grade_due_date'):
        database.execute('ALTER TABLE gradeable ADD COLUMN g_grade_due_date timestamp(6) with time zone')

    database.execute('UPDATE gradeable SET g_grade_due_date = g_grade_released_date WHERE g_grade_due_date IS NULL')
    database.execute('ALTER TABLE gradeable ALTER COLUMN g_grade_due_date SET NOT NULL')

    database.execute('ALTER TABLE gradeable DROP CONSTRAINT IF EXISTS g_grade_start_date')
    database.execute('ALTER TABLE gradeable DROP CONSTRAINT IF EXISTS g_grade_due_date')
    database.execute('ALTER TABLE gradeable ADD CONSTRAINT g_grade_start_date CHECK ((g_grade_start_date <= g_grade_due_date))')
    database.execute('ALTER TABLE gradeable ADD CONSTRAINT g_grade_due_date CHECK ((g_grade_due_date <= g_grade_released_date))')


def down(config, database, semester, course):
    database.execute('ALTER TABLE gradeable ALTER COLUMN g_grade_due_date DROP NOT NULL')
    database.execute('ALTER TABLE gradeable DROP CONSTRAINT IF EXISTS g_grade_due_date')
    database.execute('ALTER TABLE gradeable DROP CONSTRAINT IF EXISTS g_grade_start_date')
    database.execute('ALTER TABLE gradeable ADD CONSTRAINT g_grade_start_date CHECK ((g_grade_start_date <= g_grade_released_date))')
