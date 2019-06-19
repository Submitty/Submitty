"""user_email is now an optional field (but still non null)"""


def up(config, database, semester, course):
    database.execute("ALTER TABLE users ALTER COLUMN user_email TYPE character varying, ALTER COLUMN user_email SET NOT NULL")
    pass


def down(config, database, semester, course):
    pass
