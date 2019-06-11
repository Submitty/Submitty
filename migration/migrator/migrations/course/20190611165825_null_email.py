"""user_email is now an optional field"""


def up(config, database, semester, course):
    database.execute("ALTER TABLE ONLY users ALTER COLUMN user_email SET DATA TYPE character varying")
    pass


def down(config, database, semester, course):
    pass
