""" For a number of years the sessions table at the course level has
been empty.  This critical data is instead stored in a similarly named
table in the master database.  This migration removes this empty,
legacy table.
"""


def up(config, database, semester, course):
    database.execute('DROP TABLE IF EXISTS sessions')
    pass


def down(config, database, semester, course):
    # not necessary to restore -- has not been used in several years
    pass
