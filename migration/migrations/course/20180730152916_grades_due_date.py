def up(config, conn, semester, course):
    with conn.cursor() as cursor:
        cursor.execute("SELECT * FROM gradeable LIMIT 0")
        colnames = [desc[0] for desc in cursor.description]
        if 'g_grade_due_date' not in colnames:
            cursor.execute('ALTER TABLE gradeable ADD COLUMN g_grade_due_date timestamp(6) with time zone')

        cursor.execute('UPDATE gradeable SET g_grade_due_date = g_grade_released_date WHERE g_grade_due_date IS NULL')
        cursor.execute('ALTER TABLE gradeable ALTER COLUMN g_grade_due_date SET NOT NULL')
        
        cursor.execute('ALTER TABLE gradeable DROP CONSTRAINT IF EXISTS g_grade_start_date')
        cursor.execute('ALTER TABLE gradeable DROP CONSTRAINT IF EXISTS g_grade_due_date')
        cursor.execute('ALTER TABLE gradeable ADD CONSTRAINT g_grade_start_date CHECK ((g_grade_start_date <= g_grade_due_date))')
        cursor.execute('ALTER TABLE gradeable ADD CONSTRAINT g_grade_due_date CHECK ((g_grade_due_date <= g_grade_released_date))')


def down(config, conn, semester, course):
    with conn.cursor() as cursor:
        cursor.execute('ALTER TABLE gradeable ALTER COLUMN g_grade_due_date DROP NOT NULL')
        cursor.execute('ALTER TABLE gradeable DROP CONSTRAINT IF EXISTS g_grade_due_date')
        cursor.execute('ALTER TABLE gradeable DROP CONSTRAINT IF EXISTS g_grade_start_date')
        cursor.execute('ALTER TABLE gradeable ADD CONSTRAINT g_grade_start_date CHECK ((g_grade_start_date <= g_grade_released_date))')
