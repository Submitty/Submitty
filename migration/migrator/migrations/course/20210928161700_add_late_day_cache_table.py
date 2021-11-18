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
    database.execute('''
        CREATE TABLE IF NOT EXISTS late_day_cache (
            g_id VARCHAR(255),
            user_id VARCHAR(255), 
            team_id VARCHAR(255),
            late_day_date TIMESTAMP WITHOUT TIME zone NOT NULL,
            late_days_remaining INTEGER NOT NULL,
            late_days_allowed INTEGER,
            submission_days_late INTEGER,
            late_day_exceptions INTEGER, 
            late_day_status INTEGER,
            late_days_change INTEGER NOT NULL,
            CONSTRAINT ldc_user_team_id_check CHECK (((user_id IS NOT NULL) OR (team_id IS NOT NULL)))
        );
    ''')

    database.execute('ALTER TABLE late_day_cache ADD CONSTRAINT ldc_g_user_id_unique UNIQUE (g_id, user_id);')
    database.execute('ALTER TABLE late_day_cache ADD CONSTRAINT ldc_g_team_id_unique UNIQUE (g_id, team_id);')
    # Make sure that all gradeable info is present if g_id is not null
    database.execute('''ALTER TABLE late_day_cache ADD CONSTRAINT ldc_gradeable_info CHECK (
        g_id IS NULL
        OR
        ((submission_days_late IS NOT NULL) AND (late_day_exceptions IS NOT NULL))
    );''')
    database.execute('ALTER TABLE late_day_cache ADD CONSTRAINT late_day_cache_g_id FOREIGN KEY (g_id) REFERENCES gradeable(g_id) ON DELETE CASCADE;')
    database.execute('ALTER TABLE late_day_cache ADD CONSTRAINT late_day_cache_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE;')
    database.execute('ALTER TABLE late_day_cache ADD CONSTRAINT late_day_cache_team FOREIGN KEY (team_id) REFERENCES gradeable_teams(team_id) ON DELETE CASCADE ON UPDATE CASCADE;')
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
    database.execute('DROP TABLE IF EXISTS late_day_cache;')
    pass
