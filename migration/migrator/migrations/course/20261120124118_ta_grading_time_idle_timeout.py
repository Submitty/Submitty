"""Track activity for TA grading time sessions."""


def up(config, database, semester, course):
    database.execute("""
        ALTER TABLE ta_grading_time
        ADD COLUMN last_activity_at timestamp with time zone
    """)
    database.execute("""
        UPDATE ta_grading_time
        SET last_activity_at = COALESCE(closed_at, opened_at)
    """)
    database.execute("""
        ALTER TABLE ta_grading_time
        ALTER COLUMN last_activity_at SET NOT NULL
    """)


def down(config, database, semester, course):
    database.execute("""
        ALTER TABLE ta_grading_time
        DROP COLUMN last_activity_at
    """)
