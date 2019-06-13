def up(config, database, semester, course):
    database.execute("ALTER TABLE gradeable RENAME g_grade_by_registration TO g_grade_method;")
    database.execute("ALTER TABLE gradeable ALTER COLUMN g_grade_method TYPE INT USING g_grade_method::integer;")


def down(config, database, semester, course):
    database.execute("ALTER TABLE gradeable RENAME g_grade_method TO g_grade_by_registration;")
