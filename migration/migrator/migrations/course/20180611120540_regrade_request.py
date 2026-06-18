def up(config, database, semester, course):
    database.execute("CREATE TABLE regrade_requests (id serial NOT NULL PRIMARY KEY, gradeable_id VARCHAR(255) NOT NULL, timestamp TIMESTAMP NOT NULL, student_id VARCHAR(255) NOT NULL, status INTEGER DEFAULT 0 NOT NULL)")
    database.execute("CREATE TABLE regrade_discussion (id serial NOT NULL PRIMARY KEY, regrade_id INTEGER NOT NULL, timestamp TIMESTAMP NOT NULL, user_id VARCHAR(255) NOT NULL, content TEXT, deleted BOOLEAN default FALSE NOT NULL)")

    database.execute("ALTER TABLE regrade_discussion ADD CONSTRAINT regrade_discussion_regrade_requests_id_fk FOREIGN KEY (regrade_id) REFERENCES regrade_requests(id)")
    database.execute("ALTER TABLE regrade_requests ADD CONSTRAINT regrade_requests_fk0 FOREIGN KEY (gradeable_id) REFERENCES gradeable(g_id)")
    database.execute("ALTER TABLE regrade_requests ADD CONSTRAINT regrade_requests_fk1 FOREIGN KEY (student_id) REFERENCES users(user_id)")
    database.execute("ALTER TABLE regrade_discussion ADD CONSTRAINT regrade_discussion_fk0 FOREIGN KEY (regrade_id) REFERENCES regrade_requests(id)")
    database.execute("ALTER TABLE regrade_discussion ADD CONSTRAINT regrade_discussion_fk1 FOREIGN KEY (user_id) REFERENCES users(user_id)")


def down(config, database, semester, course):
    database.execute("DROP TABLE regrade_discussion")
    database.execute("DROP TABLE regrade_requests")
