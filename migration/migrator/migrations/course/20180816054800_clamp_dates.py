def up(config, conn, semester, course):
    with conn.cursor() as cursor:
        cursor.execute("UPDATE gradeable SET g_ta_view_start_date = LEAST(g_ta_view_start_date, '9999-01-01 04:59:59.000000')")
        cursor.execute("UPDATE gradeable SET g_grade_start_date = LEAST(g_grade_start_date, '9999-01-01 04:59:59.000000')")
        cursor.execute("UPDATE gradeable SET g_grade_due_date = LEAST(g_grade_due_date, '9999-01-01 04:59:59.000000')")
        cursor.execute("UPDATE gradeable SET g_grade_released_date = LEAST(g_grade_released_date, '9999-01-01 04:59:59.000000')")
        cursor.execute("UPDATE gradeable SET g_grade_locked_date = LEAST(g_grade_locked_date, '9999-01-01 04:59:59.000000')")
                        
        cursor.execute("UPDATE electronic_gradeable SET eg_team_lock_date = LEAST(eg_team_lock_date, '9999-01-01 04:59:59.000000')")
        cursor.execute("UPDATE electronic_gradeable SET eg_submission_open_date = LEAST(eg_submission_open_date, '9999-01-01 04:59:59.000000')")
        cursor.execute("UPDATE electronic_gradeable SET eg_submission_due_date = LEAST(eg_submission_due_date, '9999-01-01 04:59:59.000000')")
        cursor.execute("UPDATE electronic_gradeable SET eg_regrade_request_date = LEAST(eg_regrade_request_date, '9999-01-01 04:59:59.000000')")

def down(config, conn, semester, course):
    pass
