"""Migration for a given Submitty course database."""


def up(config, database, semester, course):
    # add gc_id (gradeable compoenent id) foriegn key column to regrade discussion table
    database.execute("ALTER TABLE regrade_discussion ADD COLUMN IF NOT EXISTS gc_id integer")
    database.execute("""
        ALTER TABLE regrade_discussion DROP CONSTRAINT IF EXISTS gradeable_component_id_fk;
        ALTER TABLE regrade_discussion ADD CONSTRAINT gradeable_component_id_fk FOREIGN KEY (gc_id) REFERENCES gradeable_component (gc_id);
        """)

    # add eg_grade_inquiry_per_component_allowed column to electronic_gradeable table
    database.execute("ALTER TABLE electronic_gradeable ADD COLUMN IF NOT EXISTS eg_grade_inquiry_per_component_allowed boolean DEFAULT false NOT NULL")
    # add a check constraint to make sure that eg_regrade_allowed is true
    database.execute("""
        ALTER TABLE electronic_gradeable DROP CONSTRAINT IF EXISTS eg_regrade_allowed_true_check;
        ALTER TABLE electronic_gradeable ADD CONSTRAINT eg_regrade_allowed_true_check CHECK (eg_regrade_allowed is true or eg_grade_inquiry_per_component_allowed is false);
    """)

def down(config, database, semester, course):
    pass
