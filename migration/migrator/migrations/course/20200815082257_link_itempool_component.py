"""Migration for a given Submitty course database."""

def up(config, database, semester, course):
    database.execute("ALTER TABLE IF EXISTS gradeable_component ADD COLUMN IF NOT EXISTS gc_is_itempool_linked BOOL NOT NULL DEFAULT FALSE")
    database.execute("ALTER TABLE IF EXISTS gradeable_component ADD COLUMN IF NOT EXISTS gc_itempool varchar(100) NOT NULL DEFAULT ''")


def down(config, database, semester, course):
    pass
