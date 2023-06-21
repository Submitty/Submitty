def up(config, database):
    sql = "ALTER TABLE courses ADD CONSTRAINT no_surrounding_spaces_course CHECK (course ~ '^[^ ].*[^ ]$'); ALTER TABLE terms ADD CONSTRAINT no_surrounding_spaces_semester CHECK (term_id ~ '^[^ ].*[^ ]$');"
    database.execute(sql)

def down(config, database):
    sql = "ALTER TABLE courses DROP CONSTRAINT no_surrounding_spaces_course; ALTER TABLE terms DROP CONSTRAINT no_surrounding_spaces_semester;"
    database.execute(sql)
