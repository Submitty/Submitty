def up(config, conn, semester, course):
    with conn.cursor() as cursor:
        cursor.execute('ALTER TABLE electronic_gradeable DROP COLUMN eg_student_download')
        cursor.execute('ALTER TABLE electronic_gradeable DROP COLUMN eg_student_any_version')


def down(config, conn, semester, course):
    with conn.cursor() as cursor:
        cursor.execute('ALTER TABLE electronic_gradeable ADD COLUMN eg_student_download BOOLEAN NOT NULL DEFAULT TRUE')
        cursor.execute('ALTER TABLE electronic_gradeable ADD COLUMN eg_student_any_version BOOLEAN NOT NULL DEFAULT TRUE')
        cursor.execute('UPDATE electronic_gradeable SET eg_student_download=eg_student_view')
        cursor.execute('UPDATE electronic_gradeable SET eg_student_any_version=eg_student_submit')
