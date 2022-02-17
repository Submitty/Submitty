"""Migration for the Submitty system."""


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

    # Update late_day_cache table
    database.execute('ALTER TABLE late_day_cache ALTER COLUMN user_id SET NOT NULL;')
    database.execute('ALTER TABLE late_day_cache ALTER COLUMN late_days_change SET NOT NULL;')
    database.execute('ALTER TABLE late_day_cache ALTER COLUMN late_day_date TYPE TIMESTAMP WITH TIME zone;')
    database.execute('ALTER TABLE late_day_cache DROP CONSTRAINT IF EXISTS ldc_user_team_id_check')
    database.execute('ALTER TABLE late_day_cache DROP CONSTRAINT IF EXISTS ldc_g_user_id_unique')
    database.execute('ALTER TABLE late_day_cache DROP CONSTRAINT IF EXISTS ldc_g_team_id_unique')
    database.execute('CREATE UNIQUE INDEX ldc_g_user_id_unique ON late_day_cache(g_id, user_id) WHERE team_id IS NULL;')
    database.execute('ALTER TABLE late_day_cache ADD CONSTRAINT ldc_g_team_id_unique UNIQUE (g_id, user_id, team_id);')

    # Drop triggers
    database.execute("DROP TRIGGER IF EXISTS gradeable_version_change ON electronic_gradeable_version;")
    database.execute("DROP TRIGGER IF EXISTS late_days_allowed_change ON late_days;")
    database.execute("DROP TRIGGER IF EXISTS late_day_extension_change ON late_day_exceptions;")
    database.execute("DROP TRIGGER IF EXISTS electronic_gradeable_change ON electronic_gradeable;")
    database.execute("DROP TRIGGER IF EXISTS gradeable_delete ON gradeable;")

    # Create functions
    database.execute("""
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
				CASE
					WHEN sg.submission_time IS NULL THEN 0
					WHEN DATE_PART('day', sg.submission_time - vg.eg_submission_due_date) < 0 THEN 0
					WHEN DATE_PART('hour', sg.submission_time - vg.eg_submission_due_date) > 0
						OR DATE_PART('minute', sg.submission_time - vg.eg_submission_due_date) > 0
						OR DATE_PART('second', sg.submission_time - vg.eg_submission_due_date) > 0
						THEN DATE_PART('day', sg.submission_time - vg.eg_submission_due_date) + 1
					ELSE DATE_PART('day', sg.submission_time - vg.eg_submission_due_date)
				END AS submission_days_late,
				CASE
					WHEN lde.late_day_exceptions IS NULL THEN 0
					ELSE lde.late_day_exceptions
				END AS late_day_exceptions
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
		RETURN NEXT returnrow;
        END LOOP;
        RETURN;	
    END;
    $$;
    """)

    database.execute("""
    CREATE OR REPLACE FUNCTION grab_late_day_updates_for_user(user_id text) RETURNS SETOF late_day_cache
        LANGUAGE plpgsql
        AS $$
    #variable_conflict use_variable
    DECLARE
    latestDate timestamp with time zone ;
    var_row RECORD;
    returnrow late_day_cache%rowtype;
    BEGIN
        FOR var_row in (
            SELECT
                ld.user_id,
                ld.since_timestamp AS late_day_date,
                ld.allowed_late_days AS late_days_allowed
            FROM late_days ld
            WHERE 
                ld.user_id = user_id
            ORDER BY late_day_date
        ) LOOP
            returnrow.user_id = var_row.user_id;
            returnrow.late_day_date = var_row.late_day_date;
            returnrow.late_days_allowed = var_row.late_days_allowed;
            RETURN NEXT returnrow;
        END LOOP;
        RETURN;	
    END;
    $$;
    """)

    database.execute("""
    CREATE OR REPLACE FUNCTION calculate_remaining_cache_for_user(user_id text, default_late_days int) RETURNS SETOF late_day_cache
        LANGUAGE plpgsql
        AS $$
    #variable_conflict use_variable
    DECLARE
        var_row RECORD;
        return_cache late_day_cache%rowtype;
        latestDate timestamp with time zone;
        late_days_remaining integer;
        late_days_change integer;
        assignment_budget integer;
        late_days_used integer;
        returnrow late_day_cache%rowtype;
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
        late_days_used = (SELECT COALESCE(SUM(ldc.late_days_change), 0)
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
                late_days_change = 0;
                assignment_budget = LEAST(var_row.late_days_allowed, late_days_remaining) + var_row.late_day_exceptions;
                IF var_row.submission_days_late <= assignment_budget THEN
                    -- clamp the days charged to be the days late minus exceptions above zero.
                    late_days_change = -GREATEST(0, LEAST(var_row.submission_days_late, assignment_budget) - var_row.late_day_exceptions);
                END IF;
                late_days_remaining = late_days_remaining + late_days_change;
                late_days_used = late_days_used - late_days_change;
                return_cache = var_row;
                return_cache.late_days_change = late_days_change;
                return_cache.late_days_remaining = late_days_remaining;
            END IF;
            RETURN NEXT return_cache;
        END LOOP;
        RETURN;
    END;
    $$;
    """)

    database.execute("""
    CREATE OR REPLACE FUNCTION late_days_allowed_change() RETURNS trigger
        LANGUAGE plpgsql
        AS $$
    BEGIN
        --- Update or Insert
        IF NEW.since_timestamp IS NOT NULL THEN
            DELETE FROM late_day_cache ldc WHERE ldc.late_day_date >= NEW.since_timestamp AND ldc.user_id = NEW.user_id;
        --- Delete
        ELSE
            DELETE FROM late_day_cache ldc WHERE ldc.late_day_date >= OLD.since_timestamp AND ldc.user_id = OLD.user_id;
        END IF ;
        RETURN NEW;
    END;
    $$;
    """)

    database.execute("""
    CREATE OR REPLACE FUNCTION gradeable_version_change() RETURNS trigger AS $$
        BEGIN
            --- Update or Insert
            IF NEW.g_id IS NOT NULL THEN
                DELETE FROM late_day_cache WHERE late_day_date >= (SELECT eg_submission_due_date 
                                                            FROM electronic_gradeable 
                                                            WHERE g_id = NEW.g_id)
                                            AND ((user_id = NEW.user_id AND NEW.team_id IS NULL)
                                                OR (NEW.user_id IS NULL AND team_id = NEW.team_id));
            --- Delete
            ELSE
                DELETE FROM late_day_cache WHERE late_day_date >= (SELECT eg_submission_due_date 
                                                            FROM electronic_gradeable 
                                                            WHERE g_id = OLD.g_id)
                                            AND ((user_id = OLD.user_id AND OLD.team_id IS NULL)
                                                OR (OLD.user_id IS NULL AND team_id = OLD.team_id));
            END IF ;
            RETURN NEW;
        END;
    $$ LANGUAGE plpgsql;
    """)

    database.execute("""
    CREATE OR REPLACE FUNCTION late_day_extension_change() RETURNS trigger AS $$
        #variable_conflict use_variable
        DECLARE
            g_id varchar;
            user_id varchar;
        BEGIN
            -- Grab values for delete
            g_id = OLD.g_id; 
            user_id = OLD.user_id;

            -- Change values for update/insert
            IF NEW.g_id IS NOT NULL THEN
                g_id = NEW.g_id;
                user_id = NEW.user_id;
            END IF;

            DELETE FROM late_day_cache ldc 
            WHERE ldc.late_day_date >= (SELECT eg_submission_due_date 
                                        FROM electronic_gradeable eg 
                                        WHERE eg.g_id = g_id)
            AND ldc.user_id = user_id;
            RETURN NEW;
        END;
    $$ LANGUAGE plpgsql;
    """)

    database.execute("""
    CREATE OR REPLACE FUNCTION electronic_gradeable_change() RETURNS trigger AS $$
        #variable_conflict use_variable
        DECLARE
            g_id varchar ;
            due_date timestamp;
        BEGIN
            -- Check for any important changes
            IF NEW.eg_submission_due_date = OLD.eg_submission_due_date
            AND NEW.eg_has_due_date = OLD.eg_has_due_date
            AND NEW.eg_allow_late_submission = OLD.eg_allow_late_submission
            AND NEW.eg_late_days = OLD.eg_late_days THEN
            RETURN NEW;
            END IF;
            
            -- Get effective g_id
            g_id = NEW.g_id;
            
            -- Grab submission due date
            due_date = NEW.eg_submission_due_date;
            
            -- If submission due date was updated, use the earliest date
            IF OLD.eg_submission_due_date IS NOT NULL
            AND NEW.eg_submission_due_date != OLD.eg_submission_due_date THEN
                due_date = LEAST(NEW.eg_submission_due_date, OLD.eg_submission_due_date);
            END IF;
            
            DELETE FROM late_day_cache WHERE late_day_date >= due_date;
            RETURN NEW;
        END;
    $$ LANGUAGE plpgsql;
    """)

    database.execute("""
    CREATE OR REPLACE FUNCTION gradeable_delete() RETURNS trigger AS $$
        BEGIN
            DELETE FROM late_day_cache WHERE late_day_date >= (SELECT eg_submission_due_date 
                                                                FROM electronic_gradeable 
                                                                WHERE g_id = OLD.g_id);
            RETURN OLD;
        END;
    $$ LANGUAGE plpgsql;
    """)

    # Create triggers
    database.execute("""
    CREATE TRIGGER gradeable_version_change AFTER INSERT OR UPDATE OR DELETE ON electronic_gradeable_version
        FOR EACH ROW EXECUTE FUNCTION gradeable_version_change();
    """)

    database.execute("""
    CREATE TRIGGER late_days_allowed_change AFTER INSERT OR UPDATE OR DELETE ON late_days
        FOR EACH ROW EXECUTE FUNCTION late_days_allowed_change();
    """)

    database.execute("""
    CREATE TRIGGER late_day_extension_change AFTER INSERT OR UPDATE OR DELETE ON late_day_exceptions
        FOR EACH ROW EXECUTE FUNCTION late_day_extension_change();
    """)

    database.execute("""
    CREATE TRIGGER electronic_gradeable_change AFTER INSERT OR UPDATE OF eg_submission_due_date, eg_has_due_date, eg_allow_late_submission, eg_late_days ON electronic_gradeable
        FOR EACH ROW EXECUTE FUNCTION electronic_gradeable_change();
    """)

    database.execute("""
    CREATE TRIGGER gradeable_delete BEFORE DELETE ON gradeable
        FOR EACH ROW EXECUTE FUNCTION gradeable_delete();
    """)
    pass


def down(config, database, semester, course):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    :param semester: Semester of the course being migrated
    :type semester: str
    :param course: Code of course being migrated
    :type course: str
    """
    # Remove all cache
    database.execute("DELETE FROM late_day_cache")

    # Drop triggers
    database.execute("DROP TRIGGER IF EXISTS gradeable_version_change ON electronic_gradeable_version;")
    database.execute("DROP TRIGGER IF EXISTS late_days_allowed_change ON late_days;")
    database.execute("DROP TRIGGER IF EXISTS late_day_extension_change ON late_day_exceptions;")
    database.execute("DROP TRIGGER IF EXISTS electronic_gradeable_change ON electronic_gradeable;")
    database.execute("DROP TRIGGER IF EXISTS gradeable_delete ON gradeable;")

    # Drop functions
    database.execute("DROP FUNCTION IF EXISTS grab_late_day_gradeables_for_user(text);")
    database.execute("DROP FUNCTION IF EXISTS grab_late_day_updates_for_user(text);")
    database.execute("DROP FUNCTION IF EXISTS calculate_remaining_cache_for_user(text, int);")
    database.execute("DROP FUNCTION IF EXISTS late_days_allowed_change();")
    database.execute("DROP FUNCTION IF EXISTS gradeable_version_change();")
    database.execute("DROP FUNCTION IF EXISTS late_day_extension_change();")
    database.execute("DROP FUNCTION IF EXISTS electronic_gradeable_change();")
    database.execute("DROP FUNCTION IF EXISTS gradeable_delete();")
