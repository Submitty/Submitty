"""Migration for a given Submitty course database."""


def up(config, database, semester, course):
    if not database.has_table('socket_connections'):
        database.execute("CREATE TABLE socket_connections ( connection_id SERIAL NOT NULL, user_id VARCHAR(500), client_id VARCHAR(100), last_active TIMESTAMP, CONSTRAINT socket_connections_pk PRIMARY KEY (connection_id), CONSTRAINT socket_connections_users_user_id_fk FOREIGN KEY (user_id) REFERENCES users ON UPDATE CASCADE ON DELETE CASCADE )")

def down(config, database, semester, course):
    pass