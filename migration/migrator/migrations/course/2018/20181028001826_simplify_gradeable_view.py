def up(config, database, semester, course):
    database.execute('ALTER TABLE electronic_gradeable DROP COLUMN IF EXISTS eg_student_download')
    database.execute('ALTER TABLE electronic_gradeable DROP COLUMN IF EXISTS eg_student_any_version')


def down(config, database, semester, course):
    database.execute('ALTER TABLE electronic_gradeable ADD COLUMN eg_student_download BOOLEAN NOT NULL DEFAULT TRUE')
    database.execute('ALTER TABLE electronic_gradeable ADD COLUMN eg_student_any_version BOOLEAN NOT NULL DEFAULT TRUE')
    database.execute('UPDATE electronic_gradeable SET eg_student_download=eg_student_view')
    database.execute('UPDATE electronic_gradeable SET eg_student_any_version=eg_student_submit')
