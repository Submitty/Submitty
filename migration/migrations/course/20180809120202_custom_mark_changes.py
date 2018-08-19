def up(config, conn, semester, course):
    with conn.cursor() as cursor:
        cursor.execute("SELECT * FROM gradeable_component_data LIMIT 0")
        colnames = [desc[0] for desc in cursor.description]
        if 'gcd_has_custom' not in colnames:
            cursor.execute("ALTER TABLE gradeable_component_data ADD COLUMN gcd_has_custom boolean DEFAULT FALSE NOT NULL")
def down(config, conn, semester, course):
    pass