"""Migration for the Submitty master database."""


def up(config, database):
    database.execute("ALTER TABLE emails DROP COLUMN IF EXISTS recipient")


def down(config, database):
    database.execute("ALTER TABLE emails ADD COLUMN IF NOT EXISTS recipient VARCHAR(255)")
