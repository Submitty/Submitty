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
    rows = database.execute('SELECT team_id FROM gradeable_teams WHERE team_id NOT IN (SELECT team_id FROM teams)')

    empty_submission =  database.execute('''
        SELECT gradeable_teams.team_id
        FROM gradeable_teams
        JOIN electronic_gradeable_data ON gradeable_teams.team_id = electronic_gradeable_data.team_id
        LEFT JOIN teams ON gradeable_teams.team_id = teams.team_id
        WHERE teams.team_id IS NULL;
                                         ''')


    '''
    rows
    "00001_aphacker"
    "00003_student"
    "00004_student"
    '''
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
    pass
