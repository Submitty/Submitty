"""user_email is now an optional field"""


def up(config, database):
    database.execute("ALTER TABLE ONLY users ALTER COLUMN user_email SET DATA TYPE character varying")
    pass


def down(config, database):
    pass
