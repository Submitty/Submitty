"""Migration for a given Submitty course database."""


def up(config, database, semester, course):
    # add gc_id (gradeable compoenent id) column to regrade_requests table
    #
    # add constraint to make sure gc_id exists in gradeable_component
    #
    # drop existing regrade_requests uniqueness check for user_id or team_id and g_id
    #
    # create an unique index to make sure no two rows can have user_id or team_id as the same
    # if the regrade_request is not referencing a gradeable component
    #
    # add constraints to make sure no two rows have the user_id or team_id, g_id and gc_id
    #
    # this is all done in single transaction to avoid the possibility of an insert making a change that does not follow
    # a constraint for the small amount of time the constraint is dropped or not yet implemented
    database.execute("""
        ALTER TABLE regrade_requests ADD COLUMN IF NOT EXISTS gc_id integer;

        ALTER TABLE regrade_requests DROP CONSTRAINT IF EXISTS regrade_requests_fk3;
        ALTER TABLE regrade_requests ADD CONSTRAINT regrade_requests_fk3 FOREIGN KEY (gc_id) REFERENCES gradeable_component (gc_id);

        ALTER TABLE regrade_requests DROP CONSTRAINT IF EXISTS gradeable_user_unique;
        ALTER TABLE regrade_requests DROP CONSTRAINT IF EXISTS gradeable_team_unique;

        CREATE UNIQUE INDEX IF NOT EXISTS gradeable_user_unique ON regrade_requests(user_id, g_id) WHERE gc_id IS NULL;
        CREATE UNIQUE INDEX IF NOT EXISTS gradeable_team_unique ON regrade_requests(team_id, g_id) WHERE gc_id IS NULL;

        ALTER TABLE regrade_requests DROP CONSTRAINT IF EXISTS gradeable_user_gc_id_unique;
        ALTER TABLE ONLY regrade_requests ADD CONSTRAINT gradeable_user_gc_id_unique UNIQUE (user_id, g_id, gc_id);
        ALTER TABLE regrade_requests DROP CONSTRAINT IF EXISTS gradeable_team_gc_id_unique;
        ALTER TABLE ONLY regrade_requests ADD CONSTRAINT gradeable_team_gc_id_unique UNIQUE (team_id, g_id, gc_id);
    """)

    # add eg_grade_inquiry_per_component_allowed column to electronic_gradeable table
    #
    # add a check constraint to make sure that eg_regrade_allowed is true
    database.execute("""
        ALTER TABLE electronic_gradeable ADD COLUMN IF NOT EXISTS eg_grade_inquiry_per_component_allowed boolean DEFAULT false NOT NULL;

        ALTER TABLE electronic_gradeable DROP CONSTRAINT IF EXISTS eg_regrade_allowed_true_check;
        ALTER TABLE electronic_gradeable ADD CONSTRAINT eg_regrade_allowed_true_check CHECK (eg_regrade_allowed is true or eg_grade_inquiry_per_component_allowed is false);
    """)

def down(config, database, semester, course):
    pass
