def up(config, conn, semester, course):
    with conn.cursor() as cursor:
        cursor.execute("ALTER TABLE ONLY gradeable_component_data DROP COLUMN IF EXISTS gcd_verifier_id")
        cursor.execute("ALTER TABLE ONLY gradeable_component_data ADD COLUMN gcd_verifier_id character varying(255) NULL")
        cursor.execute("ALTER TABLE ONLY gradeable_component_data DROP CONSTRAINT IF EXISTS gradeable_component_data_gcd_verifier_id_fkey")
        cursor.execute("ALTER TABLE ONLY gradeable_component_data ADD CONSTRAINT gradeable_component_data_gcd_verifier_id_fkey FOREIGN KEY (gcd_verifier_id) REFERENCES users(user_id) ON UPDATE CASCADE")

def down(config, conn, semester, course):
	pass