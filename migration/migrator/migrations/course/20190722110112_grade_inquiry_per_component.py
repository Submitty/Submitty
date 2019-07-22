"""Migration for a given Submitty course database."""


def up(config, database, semester, course):
    # add gc_id (gradeable compoenent id) foriegn key column to regrade discussion table
    database.execute("ALTER TABLE regrade_discussion ADD COLUMN gc_id integer")
    database.execute("ALTER TABLE regrade_discussion ADD CONSTRAINT gradeable_component_id_fk FOREIGN KEY (gc_id) REFERENCES gradeable_component (gc_id)")

    # add eg_is_gi_per_component column to electronic_gradeable table
    database.execute("ALTER TABLE electronic_gradeable ADD COLUMN eg_is_gi_per_component boolean DEFAULT false NOT NULL")
    # add a check constraint to make sure that eg_regrade_allowed is true
    database.execute("ALTER TABLE electronic_gradeable ADD CONSTRAINT regrade_allowed_true_check CHECK (eg_regrade_allowed is true or eg_is_gi_per_component is false)")

def down(config, database, semester, course):
    # remove gc_id column from regrade_discusion table
    database.execute("ALTER TABLE regrade_discussion DROP CONSTRAINT gradeable_component_id_fk")
    database.execute("ALTER TABLE regrade_discussion DROP COLUMN gc_id")

    # remove eg_is_gi_per_component column from electronic_gradeable table
    database.execute("ALTER TABLE electronic_gradeable DROP CONSTRAINT regrade_allowed_true_check")
    database.execute("ALTER TABLE electronic_gradeable DROP COLUMN eg_is_gi_per_component")
