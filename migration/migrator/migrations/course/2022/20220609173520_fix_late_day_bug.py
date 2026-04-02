"""Migration for a given Submitty course database."""


def up(config, database, semester, course):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    :param semester: Semester of the course being migrated
    :type semester: str
    :param course: Code of course being migrated
    :type course: str
    """
    database.execute("""
CREATE OR REPLACE FUNCTION public.calculate_remaining_cache_for_user(user_id text, default_late_days integer) RETURNS SETOF public.late_day_cache
    LANGUAGE plpgsql
    AS $$
    #variable_conflict use_variable
    DECLARE
        var_row RECORD;
        return_cache late_day_cache%rowtype;
        latestDate timestamp with time zone;
        late_days_remaining integer;
        late_days_change integer;
        late_days_used integer;
        returnedrow late_day_cache%rowtype;
    BEGIN
        -- Grab latest row of data available
        FOR var_row IN (
            SELECT * 
            FROM late_day_cache ldc 
            WHERE ldc.user_id = user_id
            ORDER BY ldc.late_day_date DESC, ldc.g_id DESC NULLS LAST
            LIMIT 1
        ) LOOP
            late_days_remaining = var_row.late_days_remaining;
            latestDate = var_row.late_day_date;
        END LOOP;
        
        -- Get the number of late days charged up to this point
        late_days_used = (SELECT COALESCE(SUM(-ldc.late_days_change), 0)
            FROM late_day_cache ldc
            WHERE (latestDate is NULL OR ldc.late_day_date <= latestDate)
                AND ldc.user_id = user_id AND ldc.g_id IS NOT NULL
        );
        
        -- if there is no cache in the table, the starting point
        -- should be the course default late days
        IF late_days_remaining IS NULL THEN
            late_days_remaining = default_late_days;
            late_days_used = 0;
        END IF;
        
        -- For every event after the cache's latest entry, calculate the 
        -- late days remaining and the late day change (increase or decrease)
        FOR var_row IN (
            SELECT * FROM (
                SELECT * FROM grab_late_day_gradeables_for_user (user_id := user_id)
                UNION
                SELECT * FROM grab_late_day_updates_for_user (user_id := user_id)
            ) as combined
            WHERE latestDate is NULL OR late_day_date > latestDate
            ORDER BY late_day_date NULLS LAST, g_id NULLS FIRST
        ) LOOP
            --is late day update
            IF var_row.g_id IS NULL THEN
                late_days_change = var_row.late_days_allowed - (late_days_remaining + late_days_used);
                late_days_remaining = GREATEST(0, late_days_remaining + late_days_change);
                return_cache = var_row;
                return_cache.late_days_change = late_days_change;
                return_cache.late_days_remaining = late_days_remaining;
            --is gradeable event
            ELSE
                returnedrow = get_late_day_info_from_previous(var_row.submission_days_late, var_row.late_days_allowed, var_row.late_day_exceptions, late_days_remaining);
                late_days_used = late_days_used - returnedrow.late_days_change;
				late_days_remaining = late_days_remaining + returnedrow.late_days_change;
                return_cache = var_row;
                return_cache.late_days_change = returnedrow.late_days_change;
                return_cache.late_days_remaining = returnedrow.late_days_remaining;
            END IF;
            RETURN NEXT return_cache;
        END LOOP;
        RETURN;
    END;
    $$;
""")
