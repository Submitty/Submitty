"""Migration for a given Submitty course database."""


def up(config, database, semester, course):
    database.execute('ALTER TABLE posts ADD COLUMN IF NOT EXISTS render_markdown BOOLEAN NOT NULL DEFAULT false')


def down(config, database, semester, course):
    pass
