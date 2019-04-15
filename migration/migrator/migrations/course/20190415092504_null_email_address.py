def up(config, database, semester, course):
    database.excecute("ALTER TABLE users ALTER COLUMN user_email DROP NOT NULL")
    pass


def down(config, database, semester, course):
    pass
