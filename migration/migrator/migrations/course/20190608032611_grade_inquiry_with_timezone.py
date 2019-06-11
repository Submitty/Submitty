"""Migration for a given Submitty course database."""


def up(config, database, semester, course):
    database.execute("ALTER TABLE regrade_discussion ALTER COLUMN timestamp TYPE TIMESTAMP WITH TIME ZONE;")
    database.execute("ALTER TABLE regrade_requests ALTER COLUMN timestamp TYPE TIMESTAMP WITH TIME ZONE;")


def down(config, database, semester, course):
    database.execute("ALTER TABLE regrade_discussion ALTER COLUMN timestamp TYPE TIMESTAMP;")
    database.execute("ALTER TABLE regrade_requests ALTER COLUMN timestamp TYPE TIMESTAMP;")
