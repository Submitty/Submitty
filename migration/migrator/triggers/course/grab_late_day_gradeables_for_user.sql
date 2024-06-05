--
-- Name: grab_late_day_gradeables_for_user(text); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.grab_late_day_gradeables_for_user(user_id text) RETURNS SETOF public.late_day_cache
    LANGUAGE plpgsql
    AS $$
    #variable_conflict use_variable
    DECLARE
    latestDate timestamp with time zone ;
    var_row RECORD;
    returnrow late_day_cache%rowtype;
    BEGIN
        FOR var_row in (
            WITH valid_gradeables AS (
				SELECT g.g_id, g.g_title, eg.eg_submission_due_date, eg.eg_late_days
				FROM gradeable g
				JOIN electronic_gradeable eg
					ON eg.g_id=g.g_id
				WHERE 
					eg.eg_submission_due_date IS NOT NULL
					and eg.eg_has_due_date = TRUE
					and eg.eg_student_submit = TRUE
					and eg.eg_student_view = TRUE
					and g.g_gradeable_type = 0
					and eg.eg_allow_late_submission = TRUE
					and eg.eg_submission_open_date <= NOW()
			),
			submitted_gradeables AS (
				SELECT egd.g_id, u.user_id, t.team_id, egd.submission_time
				FROM electronic_gradeable_version egv
				JOIN electronic_gradeable_data egd
					ON egv.g_id=egd.g_id 
					AND egv.active_version=egd.g_version
					AND (
						CASE
							when egd.team_id IS NOT NULL THEN egv.team_id=egd.team_id
							else egv.user_id=egd.user_id
						END
					)
				LEFT JOIN teams t
					ON t.team_id=egd.team_id
				LEFT JOIN users u
					ON u.user_id=t.user_id
					OR u.user_id=egd.user_id
				WHERE u.user_id=user_id
			)
			SELECT
				vg.g_id,
				vg.g_title,
				COALESCE(sg.user_id, user_id) as user_id,
				sg.team_id,
				vg.eg_submission_due_date AS late_day_date,
				vg.eg_late_days AS late_days_allowed,
				calculate_submission_days_late(sg.submission_time, vg.eg_submission_due_date) AS submission_days_late,
				CASE
					WHEN lde.late_day_exceptions IS NULL THEN 0
					ELSE lde.late_day_exceptions
				END AS late_day_exceptions,
				lde.reason_for_exception
			FROM valid_gradeables vg
			LEFT JOIN submitted_gradeables sg
				ON vg.g_id=sg.g_id
			LEFT JOIN late_day_exceptions lde
				ON lde.user_id=user_id
				AND vg.g_id=lde.g_id
		ORDER BY late_day_date, g_id
	) LOOP
		returnrow.g_id = var_row.g_id;
		returnrow.team_id = var_row.team_id;
		returnrow.user_id = var_row.user_id;
		returnrow.late_days_allowed = var_row.late_days_allowed;
		returnrow.late_day_date = var_row.late_day_date;
		returnrow.submission_days_late = var_row.submission_days_late;
		returnrow.late_day_exceptions = var_row.late_day_exceptions;
		returnrow.reason_for_exception = var_row.reason_for_exception;
		RETURN NEXT returnrow;
        END LOOP;
        RETURN;	
    END;
    $$;