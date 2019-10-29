def up(config, database, semester, course):
    database.execute("ALTER TABLE gradeable RENAME g_grade_by_registration TO g_grader_assignment_method;")
    database.execute("ALTER TABLE gradeable ALTER COLUMN g_grader_assignment_method TYPE INT USING g_grader_assignment_method::integer;")


def down(config, database, semester, course):
    database.execute("ALTER TABLE gradeable ALTER COLUMN g_grader_assignment_method TYPE bool USING g_grader_assignment_method::boolean;")
    database.execute("ALTER TABLE gradeable RENAME g_grader_assignment_method TO g_grade_by_registration;")
