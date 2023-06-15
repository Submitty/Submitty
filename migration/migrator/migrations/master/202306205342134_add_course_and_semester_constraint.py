def up(config, database):
    database.execute("ALTER TABLE courses ADD CONSTRAINT no_surrounding_spaces_course CHECK (course ~ '^[^ ].*[^ ]$')")
    database.execute("ALTER TABLE terms ADD CONSTRAINT no_surrounding_spaces_semester CHECK (term_id ~ '^[^ ].*[^ ]$')")

def down(config, database):
    database.execute("ALTER TABLE courses DROP CONSTRAINT no_surrounding_spaces_course")
    database.execute("ALTER TABLE terms DROP CONSTRAINT no_surrounding_spaces_semester")
 