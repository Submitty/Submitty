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
    # find all empty team with submission
    '''
    gradeable_teams
    "00000_student"	"team"	"YVRuMjkn7BmInOT"	"1"		"joe"
    "00001_aphacker"	"team"	"SVvlJ7mt8MpYTwX"	"1"		"ap"
    "00003_student"	"team2"	"yT3gtWZ4Xbk4Fm1"	"1"		
    "00002_aphacker"	"team2"	"vgVpKZrzAZonQ9B"	"1"		"apt2"
    "00004_student"	"team2"	"VbCIJ3crHZDJk3u"	"1"		"joet2"

    teams
    "00000_student"	"student"	1	
    "00000_student"	"aphacker"	1	
    "00002_aphacker"	"aphacker"	1	
    "00002_aphacker"	"student"	1	
    '''
    
    empty_submission =  database.execute(
        """
        SELECT gradeable_teams.team_id
        FROM gradeable_teams
        JOIN electronic_gradeable_data ON gradeable_teams.team_id = electronic_gradeable_data.team_id
        LEFT JOIN teams ON gradeable_teams.team_id = teams.team_id
        WHERE teams.team_id IS NULL;
        """
    )
    # need update the table when 
        # electronic_gradeable_data: new submission ???but need be in teams to have a submission???
        # teams: new_empty team, ???empty_team become non_empty???
        # gradeable_teams: new team(???how???),  delete team(fkey)
    '''
    "00001_aphacker"
    "00004_student"
    '''
    
    database.execute("""
        CREATE TABLE IF NOT EXISTS teams_empty (
        team_id character varying(255) NOT NULL,
        user_id character varying(255),
        state integer NOT NULL,
        last_viewed_time timestamp(6) with time zone DEFAULT NULL::timestamp with time zone);
        """
    )

    database.execute("ALTER TABLE teams_empty DROP CONSTRAINT IF EXISTS teams_empty_pkey")
    database.execute(
        """
        ALTER TABLE ONLY teams_empty #???
            ADD CONSTRAINT teams_empty_pkey PRIMARY KEY (team_id);
        """
    )

    database.execute("ALTER TABLE teams_empty DROP CONSTRAINT IF EXISTS teams_empty_team_id_fkey")
    database.execute(
        """
        ALTER TABLE ONLY teams_empty
        ADD CONSTRAINT teams_empty_team_id_fkey FOREIGN KEY (team_id) REFERENCES gradeable_teams(team_id) ON DELETE CASCADE;
        """
    )

    #CREATE TRIGGER add_course_user AFTER INSERT OR UPDATE ON public.users FOR EACH ROW EXECUTE PROCEDURE public.add_course_user();
  

    database.execute(
        """
        CREATE OR REPLACE FUNCTION teams_empty_team_changes() RETURNS TRIGGER 
            LANGUAGE plpgsql;
            AS $$
        BEGIN
            IF (TG_OP = 'DELETE') THEN
                #if delete from teams, check if there is a submission(team_id in electronic_gradeable_data)
                #if do add to empty team

                IF EXISTS (
                    SELECT 1 
                    FROM electronic_gradeable_data
                    WHERE electronic_gradeable_data.team_id = OLD.team_id
                ) 
                THEN
                    INSERT INTO teams_empty (team_id) VALUES (OLD.team_id)
                    ON CONFLICT (team_id) DO NOTHING;
                END IF;
            END IF;
           
            RETURN NULL;
        END;
        $$;
        """
    )

    database.execute(
        """
        CREATE TRIGGER  teams_empty_team_changes AFTER DELETE ON teams FOR EACH ROW EXECUTE FUNCTION teams_empty_team_changes();
        """
    )
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
    database.execute("DROP TABLE IF EXISTS teams_empty")
