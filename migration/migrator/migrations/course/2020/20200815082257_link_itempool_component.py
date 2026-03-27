"""Migration for a given Submitty course database."""

def up(config, database, semester, course):
    # Link Itempool with the rubric-component
    database.execute("ALTER TABLE IF EXISTS gradeable_component ADD COLUMN IF NOT EXISTS gc_is_itempool_linked BOOL NOT NULL DEFAULT FALSE")
    database.execute("ALTER TABLE IF EXISTS gradeable_component ADD COLUMN IF NOT EXISTS gc_itempool varchar(100) NOT NULL DEFAULT ''")
    # Link Itempool with Solution/Ta notes panels
    database.execute("ALTER TABLE IF EXISTS solution_ta_notes ADD COLUMN IF NOT EXISTS itempool_item VARCHAR(100) NOT NULL DEFAULT ''")


def down(config, database, semester, course):
    pass
