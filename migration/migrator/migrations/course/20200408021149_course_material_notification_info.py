def up(config, database, semester, course):
    database.execute('CREATE TABLE IF NOT EXISTS course_material_info (user_id character varying(255) NOT NULL,course_file_path TEXT NOT NULL,release_date timestamp with time zone NOT NULL,seen BOOLEAN DEFAULT FALSE NOT NULL)')
    database.execute('ALTER TABLE course_material_info DROP CONSTRAINT IF EXISTS course_material_info_pkey')
    database.execute('ALTER TABLE course_material_info ADD CONSTRAINT course_material_info_pkey PRIMARY KEY (user_id, course_file_path)')
    database.execute('ALTER TABLE course_material_info DROP CONSTRAINT IF EXISTS course_material_info_fkey')
    database.execute('ALTER TABLE course_material_info ADD CONSTRAINT course_material_info_fkey FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE')

def down(config, database, semester, course):
    pass
