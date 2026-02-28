def up(config, database, semester, course):
    database.execute("ALTER TABLE gradeable_component_data ADD COLUMN IF NOT EXISTS gcd_verifier_id VARCHAR(255)")
    database.execute("ALTER TABLE gradeable_component_data ADD COLUMN IF NOT EXISTS gcd_verify_time TIMESTAMP")

    database.execute("ALTER TABLE gradeable_component_data DROP CONSTRAINT IF EXISTS gradeable_component_data_verifier_id_fkey")
    database.execute("ALTER TABLE gradeable_component_data ADD CONSTRAINT gradeable_component_data_verifier_id_fkey FOREIGN KEY (gcd_verifier_id) REFERENCES users(user_id)")


def down(config, database, semester, course):
    database.execute("ALTER TABLE ONLY gradeable_component_data DROP COLUMN gcd_verifier_id")
    database.execute("ALTER TABLE ONLY gradeable_component_data DROP COLUMN gcd_verify_time")
