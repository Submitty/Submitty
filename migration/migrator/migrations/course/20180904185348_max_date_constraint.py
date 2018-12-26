def up(config, conn, semester, course):
    with conn.cursor() as cursor:
        # First, re-clamp the dates similar to the "clamp_dates" migration
        cursor.execute("UPDATE gradeable SET g_ta_view_start_date = LEAST(g_ta_view_start_date, '9999-02-01 00:00:00.000000')")
        cursor.execute("UPDATE gradeable SET g_grade_start_date = LEAST(g_grade_start_date, '9999-02-01 00:00:00.000000')")
        cursor.execute("UPDATE gradeable SET g_grade_due_date = LEAST(g_grade_due_date, '9999-02-01 00:00:00.000000')")
        cursor.execute("UPDATE gradeable SET g_grade_released_date = LEAST(g_grade_released_date, '9999-02-01 00:00:00.000000')")
        cursor.execute("UPDATE gradeable SET g_grade_locked_date = LEAST(g_grade_locked_date, '9999-02-01 00:00:00.000000')")

        cursor.execute("UPDATE electronic_gradeable SET eg_team_lock_date = LEAST(eg_team_lock_date, '9999-02-01 00:00:00.000000')")
        cursor.execute("UPDATE electronic_gradeable SET eg_submission_open_date = LEAST(eg_submission_open_date, '9999-02-01 00:00:00.000000')")
        cursor.execute("UPDATE electronic_gradeable SET eg_submission_due_date = LEAST(eg_submission_due_date, '9999-02-01 00:00:00.000000')")
        cursor.execute("UPDATE electronic_gradeable SET eg_regrade_request_date = LEAST(eg_regrade_request_date, '9999-02-01 00:00:00.000000')")

        # Now, add constraints to prevent them from being this high again
        # Note: due to existing constraints, only a few of the columns need the new constraint
        cursor.execute("ALTER TABLE gradeable ADD CONSTRAINT g_grade_locked_date_max CHECK(g_grade_locked_date <= '9999-03-01 00:00:00.000000')")

        cursor.execute("ALTER TABLE electronic_gradeable ADD CONSTRAINT eg_team_lock_date_max CHECK(eg_team_lock_date <= '9999-03-01 00:00:00.000000')")
        cursor.execute("ALTER TABLE electronic_gradeable ADD CONSTRAINT eg_submission_due_date_max CHECK(eg_submission_due_date <= '9999-03-01 00:00:00.000000')")
        cursor.execute("ALTER TABLE electronic_gradeable ADD CONSTRAINT eg_regrade_request_date_max CHECK(eg_regrade_request_date <= '9999-03-01 00:00:00.000000')")


def down(config, conn, semester, course):
    with conn.cursor() as cursor:
        cursor.execute("ALTER TABLE gradeable DROP CONSTRAINT g_grade_locked_date_max")

        cursor.execute("ALTER TABLE electronic_gradeable DROP CONSTRAINT eg_team_lock_date_max")
        cursor.execute("ALTER TABLE electronic_gradeable DROP CONSTRAINT eg_submission_due_date_max")
        cursor.execute("ALTER TABLE electronic_gradeable DROP CONSTRAINT eg_regrade_request_date_max")
