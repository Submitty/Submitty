def up(config, database, semester, course):
    database.execute('ALTER TABLE regrade_requests RENAME COLUMN gradeable_id TO g_id')
    database.execute('ALTER TABLE regrade_requests RENAME COLUMN student_id TO user_id')

    if not database.table_has_column('regrade_requests', 'team_id'):
        database.execute('ALTER TABLE regrade_requests ADD COLUMN team_id VARCHAR(255)')
        database.execute('ALTER TABLE regrade_requests ADD CONSTRAINT regrade_requests_fk2 FOREIGN KEY (team_id) REFERENCES gradeable_teams(team_id)')

    database.execute('ALTER TABLE regrade_requests ADD CONSTRAINT gradeable_user_unique UNIQUE(g_id, user_id)')
    database.execute('ALTER TABLE regrade_requests ADD CONSTRAINT gradeable_team_unique UNIQUE(g_id, team_id)')
    database.execute('ALTER TABLE regrade_requests ALTER COLUMN user_id DROP NOT NULL')


def down(config, database, semester, course):
    database.execute('ALTER TABLE regrade_requests RENAME COLUMN g_id TO gradeable_id')
    database.execute('ALTER TABLE regrade_requests RENAME COLUMN user_id TO student_id')

    database.execute('ALTER TABLE regrade_requests DROP CONSTRAINT gradeable_user_unique')
    database.execute('ALTER TABLE regrade_requests DROP CONSTRAINT gradeable_team_unique')
