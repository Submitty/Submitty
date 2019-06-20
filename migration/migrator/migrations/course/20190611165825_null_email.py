<<<<<<< HEAD
"""user_email is now an optional field"""


def up(config, database, semester, course):
    database.execute("ALTER TABLE ONLY users ALTER COLUMN user_email SET DATA TYPE character varying")
=======
"""user_email is now an optional field (but still non null)"""


def up(config, database, semester, course):
    database.execute("ALTER TABLE users ALTER COLUMN user_email TYPE character varying, ALTER COLUMN user_email SET NOT NULL")
>>>>>>> 3aa3b749165588b967eb76ba56b003b213a4ae07
    pass


def down(config, database, semester, course):
    pass
