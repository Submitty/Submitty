def up(config, conn, semester, course):
    with conn.cursor() as cursor:
        cursor.execute("ALTER TABLE ONLY gradeable_component_data ADD COLUMN verifier_id character varying(255) NULL")
        cursor.execute("ALTER TABLE ONLY gradeable_component_data ADD CONSTRAINT gradeable_component_data_verifier_id_fkey FOREIGN KEY (verifier_id) REFERENCES users(user_id) ON UPDATE CASCADE")

def down(config, conn, semester, course):
    with conn.cursor() as cursor:
        cursor.execute("ALTER TABLE ONLY gradeable_component_data DROP COLUMN verifier_id")