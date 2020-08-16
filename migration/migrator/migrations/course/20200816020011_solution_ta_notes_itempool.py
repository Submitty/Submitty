"""Migration for a given Submitty course database."""

def up(config, database, semester, course):
    database.execute("ALTER TABLE IF EXISTS solution_ta_notes ADD CONSTRAINT solution_ta_notes_component_id_fk FOREIGN KEY (component_id) REFERENCES gradeable_component(gc_id)")
    database.execute("ALTER TABLE IF EXISTS solution_ta_notes ADD COLUMN IF NOT EXISTS itempool_item VARCHAR(100) NOT NULL DEFAULT ''")


def down(config, database, semester, course):
    pass
