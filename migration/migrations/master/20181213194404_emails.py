def up(config, conn):
    with conn.cursor() as cursor:
        cursor.execute("""
CREATE TABLE IF NOT EXISTS emails (
    id serial NOT NULL PRIMARY KEY,
    recipient  varchar(255) NOT NULL,
    subject TEXT NOT NULL,
    body TEXT NOT NULL,
    created TIMESTAMP WITHOUT TIME zone NOT NULL,
    sent TIMESTAMP WITHOUT TIME zone)""")
        
def down(config, conn):
    pass