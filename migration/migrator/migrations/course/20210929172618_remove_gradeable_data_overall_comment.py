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
    result = database.execute("SELECT 1 FROM gradeable_data WHERE gd_overall_comment <> '' LIMIT 1").first()
    if result is not None:
        result = database.execute("SELECT 1 FROM users WHERE user_group=1 LIMIT 1").first()
        if result is None:
            raise Exception("ERROR: semester \'{}\' course \'{}\' has no instructors, but needs at least one to move overall comment data.".format(semester, course))
        database.execute("INSERT INTO gradeable_data_overall_comment (g_id, goc_team_id, goc_grader_id, goc_overall_comment) SELECT g_id, gd_team_id, (SELECT user_id FROM users WHERE user_group=1 LIMIT 1), gd_overall_comment FROM gradeable_data WHERE gd_overall_comment <> '' AND gd_team_id IS NOT NULL ON CONFLICT ON CONSTRAINT gradeable_data_overall_comment_team_unique DO UPDATE SET goc_overall_comment = gradeable_data_overall_comment.goc_overall_comment || E'\nPrevious Overall Comment: ' || excluded.goc_overall_comment")
        database.execute("INSERT INTO gradeable_data_overall_comment (g_id, goc_user_id, goc_grader_id, goc_overall_comment) SELECT g_id, gd_user_id, (SELECT user_id FROM users WHERE user_group=1 LIMIT 1), gd_overall_comment FROM gradeable_data WHERE gd_overall_comment <> '' AND gd_user_id IS NOT NULL ON CONFLICT ON CONSTRAINT gradeable_data_overall_comment_user_unique DO UPDATE SET goc_overall_comment = gradeable_data_overall_comment.goc_overall_comment || E'\nPrevious Overall Comment: ' || excluded.goc_overall_comment")
    database.execute("ALTER TABLE gradeable_data DROP COLUMN IF EXISTS gd_overall_comment")
    database.execute("""CREATE OR REPLACE FUNCTION public.csv_to_numeric_gradeable(vcode text[], gradeable_id text, grader_id text) RETURNS boolean
        LANGUAGE plpgsql
        AS $$
    DECLARE
        -- Size of first array after splitting
        size INTEGER;
        -- Array of individual line after splitting
        line TEXT[];
        -- Variable to store each line in the array
        i TEXT;
        -- Array of gc_ids for this gradeable
        gcids INTEGER[];
        -- gradeable_data id for this gradeable for this student
        gdid INTEGER;
        -- Array counter
        j INTEGER;
        -- Is this gradeable component text?
        istext BOOLEAN[];
        --Score to be inserted
        score NUMERIC;
    BEGIN
        gcids := ARRAY(SELECT gc_id FROM gradeable_component WHERE g_id = gradeable_id);
        istext := ARRAY(SELECT gc_is_text FROM gradeable_component WHERE g_id = gradeable_id);
        -- Get the number of gradeable components for this gradeable. Will be used to test
        -- for uniform sized arrays
        size := array_length(gcids, 1);
        FOREACH i IN ARRAY vcode
        LOOP
            -- Split the current line
            line := string_to_array(i, ',');
            -- Check for uniform size
            IF array_length(line, 1) <> size + 1 THEN
            RAISE EXCEPTION 'INVALID SIZE: Arrays are jagged.';
            END IF;

            -- Remove any existing record for this student for this gradeable
            DELETE FROM gradeable_data WHERE gd_user_id = line[1] AND g_id = gradeable_id;

            INSERT INTO gradeable_data(g_id, gd_user_id) VALUES (gradeable_id, line[1]);

            SELECT gd_id INTO gdid FROM gradeable_data WHERE g_id = gradeable_id AND gd_user_id = line[1];

            FOR j IN 1..size
            LOOP
            IF istext[j] THEN
            --COME BACK AND FIX: need to put in gcd_grade_time...double check to see that CSV upload still works for numeric/text
                INSERT INTO gradeable_component_data(gc_id, gd_id, gcd_component_comment, gcd_grader_id, gcd_graded_version, gcd_grade_time) VALUES (gcids[j], gdid, line[j+1], grader_id, NULL);
            ELSE
                score := CAST(line[j+1] AS NUMERIC);
                INSERT INTO gradeable_component_data(gc_id, gd_id, gcd_score, gcd_grader_id, gcd_graded_version, gcd_grade_time) VALUES (gcids[j], gdid, score, grader_id, NULL);
            END IF;
            END LOOP;

        END LOOP;
        RETURN TRUE ;
    END;
    $$;""")
    



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
    database.execute("ALTER TABLE gradeable_data ADD COLUMN IF NOT EXISTS gd_overall_comment character varying NOT NULL DEFAULT ''")
    database.execute("""CREATE OR REPLACE FUNCTION public.csv_to_numeric_gradeable(vcode text[], gradeable_id text, grader_id text) RETURNS boolean
        LANGUAGE plpgsql
        AS $$
    DECLARE
        -- Size of first array after splitting
        size INTEGER;
        -- Array of individual line after splitting
        line TEXT[];
        -- Variable to store each line in the array
        i TEXT;
        -- Array of gc_ids for this gradeable
        gcids INTEGER[];
        -- gradeable_data id for this gradeable for this student
        gdid INTEGER;
        -- Array counter
        j INTEGER;
        -- Is this gradeable component text?
        istext BOOLEAN[];
        --Score to be inserted
        score NUMERIC;
    BEGIN
        gcids := ARRAY(SELECT gc_id FROM gradeable_component WHERE g_id = gradeable_id);
        istext := ARRAY(SELECT gc_is_text FROM gradeable_component WHERE g_id = gradeable_id);
        -- Get the number of gradeable components for this gradeable. Will be used to test
        -- for uniform sized arrays
        size := array_length(gcids, 1);
        FOREACH i IN ARRAY vcode
        LOOP
            -- Split the current line
            line := string_to_array(i, ',');
            -- Check for uniform size
            IF array_length(line, 1) <> size + 1 THEN
            RAISE EXCEPTION 'INVALID SIZE: Arrays are jagged.';
            END IF;

            -- Remove any existing record for this student for this gradeable
            DELETE FROM gradeable_data WHERE gd_user_id = line[1] AND g_id = gradeable_id;

            INSERT INTO gradeable_data(g_id, gd_user_id, gd_overall_comment) VALUES (gradeable_id, line[1], '');

            SELECT gd_id INTO gdid FROM gradeable_data WHERE g_id = gradeable_id AND gd_user_id = line[1];

            FOR j IN 1..size
            LOOP
            IF istext[j] THEN
            --COME BACK AND FIX: need to put in gcd_grade_time...double check to see that CSV upload still works for numeric/text
                INSERT INTO gradeable_component_data(gc_id, gd_id, gcd_component_comment, gcd_grader_id, gcd_graded_version, gcd_grade_time) VALUES (gcids[j], gdid, line[j+1], grader_id, NULL);
            ELSE
                score := CAST(line[j+1] AS NUMERIC);
                INSERT INTO gradeable_component_data(gc_id, gd_id, gcd_score, gcd_grader_id, gcd_graded_version, gcd_grade_time) VALUES (gcids[j], gdid, score, grader_id, NULL);
            END IF;
            END LOOP;

        END LOOP;
        RETURN TRUE ;
    END;
    $$;""")
