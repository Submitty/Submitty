def up(config, database):
    sql = "ALTER TABLE courses ADD CONSTRAINT course_validate CHECK (course ~ '^[a-zA-Z0-9_-]*$');"
    sql += "ALTER TABLE terms ADD CONSTRAINT term_id_validate CHECK (term_id ~ '^[a-zA-Z0-9_-]*$');"
    database.execute(sql)

def down(config, database):
    sql = "ALTER TABLE courses DROP CONSTRAINT course_validate;"
    sql += "ALTER TABLE terms DROP CONSTRAINT term_id_validate;"
    database.execute(sql)