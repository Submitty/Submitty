def up(config, conn, semester, course):
    with conn.cursor() as cursor:
        cursor.execute('ALTER TABLE regrade_requests RENAME COLUMN gradeable_id TO g_id')
        cursor.execute('ALTER TABLE regrade_requests RENAME COLUMN student_id TO user_id')

        cursor.execute("SELECT * FROM regrade_requests LIMIT 0")
        colnames = [desc[0] for desc in cursor.description]

        if 'team_id' not in colnames:
            cursor.execute('ALTER TABLE regrade_requests ADD COLUMN team_id VARCHAR(255)')
            cursor.execute('ALTER TABLE regrade_requests ADD CONSTRAINT regrade_requests_fk2 FOREIGN KEY (team_id) REFERENCES gradeable_teams(team_id)')

        cursor.execute('ALTER TABLE regrade_requests ADD CONSTRAINT gradeable_user_unique UNIQUE(g_id, user_id)')
        cursor.execute('ALTER TABLE regrade_requests ADD CONSTRAINT gradeable_team_unique UNIQUE(g_id, team_id)')
        cursor.execute('ALTER TABLE regrade_requests ALTER COLUMN user_id DROP NOT NULL')



def down(config, conn, semester, course):
    with conn.cursor() as cursor:
        cursor.execute('ALTER TABLE regrade_requests RENAME COLUMN g_id TO gradeable_id')
        cursor.execute('ALTER TABLE regrade_requests RENAME COLUMN user_id TO student_id')

        cursor.execute('ALTER TABLE regrade_requests DROP CONSTRAINT gradeable_user_unique')
        cursor.execute('ALTER TABLE regrade_requests DROP CONSTRAINT gradeable_team_unique')
