"""user_email is now an optional field (but still non null)"""


def up(config, database, term, course):
    database.execute("ALTER TABLE users ALTER COLUMN user_email TYPE character varying, ALTER COLUMN user_email SET NOT NULL")
    pass


def down(config, database, term, course):
    pass
