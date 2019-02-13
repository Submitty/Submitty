def up(config, conn, semester, course):
    with conn.cursor() as cursor:
        cursor.execute("ALTER TABLE gradeable_component_data ADD COLUMN gcd_verifier_id VARCHAR(255)")
        cursor.execute("ALTER TABLE gradeable_component_data ADD COLUMN gcd_verify_time TIMESTAMP")

        cursor.execute("ALTER TABLE gradeable_component_data ADD CONSTRAINT gradeable_component_data_verifier_id_fkey FOREIGN KEY (gcd_verifier_id) REFERENCES users(user_id)")


def down(config, conn, semester, course):
    with conn.cursor() as cursor:    
        cursor.execute("ALTER TABLE ONLY gradeable_component_data DROP COLUMN gcd_verifier_id")
        cursor.execute("ALTER TABLE ONLY gradeable_component_data DROP COLUMN gcd_verify_time")
