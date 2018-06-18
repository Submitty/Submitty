def up(conn):
    with conn.cursor() as cursor:
        cursor.execute("CREATE TABLE regrade_requests (id serial NOT NULL PRIMARY KEY, gradeable_id VARCHAR(255) NOT NULL, timestamp TIMESTAMP NOT NULL, student_id VARCHAR(255) NOT NULL, status INTEGER DEFAULT 0 NOT NULL)")
        cursor.execute("CREATE TABLE regrade_discussion (id serial NOT NULL PRIMARY KEY, regrade_id INTEGER NOT NULL, timestamp TIMESTAMP NOT NULL, user_id VARCHAR(255) NOT NULL, content TEXT, deleted BOOLEAN default FALSE NOT NULL)")

        cursor.execute("ALTER TABLE regrade_discussion ADD CONSTRAINT regrade_discussion_regrade_requests_id_fk FOREIGN KEY (regrade_id) REFERENCES regrade_requests(id)")
        cursor.execute("ALTER TABLE regrade_requests ADD CONSTRAINT regrade_requests_fk0 FOREIGN KEY (gradeable_id) REFERENCES gradeable(g_id)")
        cursor.execute("ALTER TABLE regrade_requests ADD CONSTRAINT regrade_requests_fk1 FOREIGN KEY (student_id) REFERENCES users(user_id)")
        cursor.execute("ALTER TABLE regrade_discussion ADD CONSTRAINT regrade_discussion_fk0 FOREIGN KEY (regrade_id) REFERENCES regrade_requests(id)")
        cursor.execute("ALTER TABLE regrade_discussion ADD CONSTRAINT regrade_discussion_fk1 FOREIGN KEY (user_id) REFERENCES users(user_id)")

def down(conn):
    with conn.cursor() as cursor:
        cursor.execute("DROP TABLE regrade_discussion")
        cursor.execute("DROP TABLE regrade_requests")
