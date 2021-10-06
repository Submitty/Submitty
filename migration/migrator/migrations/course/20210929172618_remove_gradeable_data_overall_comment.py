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
