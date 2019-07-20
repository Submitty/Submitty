"""Migration for a given Submitty course database."""


def up(config, database, semester, course):
    if not database.table_has_column('posts', 'render_markdown'):
        database.execute('ALTER TABLE posts ADD COLUMN render_markdown BOOLEAN NOT NULL DEFAULT false')
    pass


def down(config, database, semester, course):
    pass
